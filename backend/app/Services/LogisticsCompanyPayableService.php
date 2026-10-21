<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\FinancialJournalEntry;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class LogisticsCompanyPayableService
{
    /**
     * Admin-only commercial override. Runs under an order lock, records the
     * original/default and final amount, and posts balanced journal adjustments.
     * It is deliberately blocked after verified customer handover: post-handover
     * changes require a separate recovery/adjustment workflow linked to payout state.
     */
    public function override(Order $order, User $admin, string $amount, string $reason): array
    {
        if ($admin->status !== 'active' || $admin->user_type !== 'super_admin') {
            throw new AuthorizationException('Only an active Super Admin may override a logistics-company payable.');
        }
        if (!is_numeric($amount) || BcMathService::comp($amount, '0.00') < 0) {
            throw new InvalidArgumentException('The approved logistics-company amount must be zero or greater.');
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw new InvalidArgumentException('A reason of 1–2000 characters is required for every logistics-company payable override.');
        }
        $amount = bcadd($amount, '0', 2);

        return DB::transaction(function () use ($order, $admin, $amount, $reason) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new RuntimeException('Order not found.');
            }
            if (!$locked->retailer_model_order || $locked->fulfillment_type !== 'doorstep_delivery') {
                throw new InvalidArgumentException('Only new retailer-model doorstep-delivery orders can use this override.');
            }
            if ($locked->payment_status !== 'paid') {
                throw new InvalidArgumentException('The customer payment must be verified before setting a logistics-company payable.');
            }
            if (in_array($locked->order_status, ['delivered', 'returned', 'cancelled', 'canceled', 'refunded'], true)
                || $locked->received_at !== null) {
                throw new InvalidArgumentException('The payable cannot be overridden after customer handover or a terminal order outcome.');
            }
            if (!$locked->logistics_company_id) {
                throw new InvalidArgumentException('Assign an approved logistics company before overriding its payable.');
            }
            $company = LogisticsCompanyProfile::query()
                ->whereKey($locked->logistics_company_id)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->first();
            if (!$company) {
                throw new InvalidArgumentException('The assigned logistics company must be active and approved.');
            }

            $fee = bcadd((string) $locked->delivery_fee, '0', 2);
            if (BcMathService::comp($fee, '0.00') <= 0) {
                throw new InvalidArgumentException('A zero-fee order cannot have a logistics-company payable.');
            }

            $default = $locked->logistics_company_payable_default_amount;
            if ($default === null) {
                $default = BcMathService::mul($fee, '0.90');
                $locked->logistics_company_payable_default_amount = $default;
            }
            $oldAmount = bcadd((string) ($locked->logistics_company_payable_amount ?? $default), '0', 2);
            $revision = (int) $locked->logistics_company_payable_override_revision;

            if (BcMathService::comp($oldAmount, $amount) === 0
                && trim((string) $locked->logistics_company_payable_override_reason) === $reason
                && (int) $locked->logistics_company_payable_override_by === (int) $admin->id) {
                return ['success' => true, 'replayed' => true, 'order' => $locked];
            }

            $oldMargin = BcMathService::comp($fee, $oldAmount) > 0 ? BcMathService::sub($fee, $oldAmount) : '0.00';
            $newMargin = BcMathService::comp($fee, $amount) > 0 ? BcMathService::sub($fee, $amount) : '0.00';
            $oldSubsidy = BcMathService::comp($oldAmount, $fee) > 0 ? BcMathService::sub($oldAmount, $fee) : '0.00';
            $newSubsidy = BcMathService::comp($amount, $fee) > 0 ? BcMathService::sub($amount, $fee) : '0.00';
            $revision++;

            // A payment-settled order must already have the original company-payable credit.
            // Do not fabricate a financial baseline if the journal is missing.
            $baseline = FinancialJournalEntry::query()
                ->where('order_id', $locked->id)
                ->where('account_name', 'logistics_company_payable')
                ->where('entry_type', 'credit')
                ->orderBy('id')
                ->first();
            if (!$baseline) {
                throw new RuntimeException('The original logistics-company payable journal is missing; reconcile before overriding.');
            }

            // Reclassify a suspense payable to the actual company after dispatch assignment,
            // without editing the original immutable journal entry.
            $baselineCompanyId = $baseline->beneficiary_id;
            if ((int) ($baselineCompanyId ?? 0) !== (int) $company->id) {
                $this->journal($locked, $admin, $revision, 'company-reclass-debit', 'logistics_company_payable', 'debit', (string) $baseline->amount, null, 'Reclassify unsettled logistics payable from suspense/previous company.');
                $this->journal($locked, $admin, $revision, 'company-reclass-credit', 'logistics_company_payable', 'credit', (string) $baseline->amount, $company->id, 'Assign unsettled logistics payable to the approved logistics company.');
            }

            $companyDelta = BcMathService::sub($amount, $oldAmount);
            if (BcMathService::comp($companyDelta, '0.00') > 0) {
                $this->journal($locked, $admin, $revision, 'company-increase', 'logistics_company_payable', 'credit', $companyDelta, $company->id, 'Increase approved logistics-company payable.');
            } elseif (BcMathService::comp($companyDelta, '0.00') < 0) {
                $this->journal($locked, $admin, $revision, 'company-decrease', 'logistics_company_payable', 'debit', BcMathService::sub('0.00', $companyDelta), $company->id, 'Reduce approved logistics-company payable.');
            }

            $marginDelta = BcMathService::sub($newMargin, $oldMargin);
            if (BcMathService::comp($marginDelta, '0.00') > 0) {
                $this->journal($locked, $admin, $revision, 'margin-increase', 'platform_delivery_revenue', 'credit', $marginDelta, null, 'Increase VM delivery margin after company-payable override.');
            } elseif (BcMathService::comp($marginDelta, '0.00') < 0) {
                $this->journal($locked, $admin, $revision, 'margin-decrease', 'platform_delivery_revenue', 'debit', BcMathService::sub('0.00', $marginDelta), null, 'Reduce VM delivery margin after company-payable override.');
            }

            $subsidyDelta = BcMathService::sub($newSubsidy, $oldSubsidy);
            if (BcMathService::comp($subsidyDelta, '0.00') > 0) {
                $this->journal($locked, $admin, $revision, 'subsidy-increase', 'delivery_subsidy_expense', 'debit', $subsidyDelta, null, 'Record VM-funded delivery subsidy above customer delivery fee.');
            } elseif (BcMathService::comp($subsidyDelta, '0.00') < 0) {
                $this->journal($locked, $admin, $revision, 'subsidy-decrease', 'delivery_subsidy_expense', 'credit', BcMathService::sub('0.00', $subsidyDelta), null, 'Reverse excess delivery subsidy after company-payable override.');
            }

            $oldValues = [
                'default_amount' => $default,
                'logistics_company_payable_amount' => $oldAmount,
                'override_reason' => $locked->logistics_company_payable_override_reason,
                'override_by' => $locked->logistics_company_payable_override_by,
                'override_at' => optional($locked->logistics_company_payable_override_at)->toIso8601String(),
                'company_id' => $locked->logistics_company_id,
            ];

            $locked->logistics_company_payable_amount = $amount;
            $locked->logistics_company_payable_override_reason = $reason;
            $locked->logistics_company_payable_override_by = $admin->id;
            $locked->logistics_company_payable_override_at = now();
            $locked->logistics_company_payable_override_revision = $revision;
            $locked->save();

            AdminAuditLog::create([
                'user_id' => $admin->id,
                'action' => 'logistics_company_payable_overridden',
                'target_type' => Order::class,
                'target_id' => $locked->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'default_amount' => $default,
                    'logistics_company_payable_amount' => $amount,
                    'override_reason' => $reason,
                    'override_by' => $admin->id,
                    'override_at' => $locked->logistics_company_payable_override_at->toIso8601String(),
                    'revision' => $revision,
                    'delivery_fee' => $fee,
                    'vm_delivery_margin' => $newMargin,
                    'vm_delivery_subsidy' => $newSubsidy,
                    'company_id' => $locked->logistics_company_id,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return ['success' => true, 'replayed' => false, 'order' => $locked];
        });
    }

    private function journal(
        Order $order,
        User $admin,
        int $revision,
        string $suffix,
        string $account,
        string $entryType,
        string $amount,
        ?int $beneficiaryId,
        string $description
    ): void {
        if (BcMathService::comp($amount, '0.00') <= 0) {
            return;
        }
        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'transaction_reference' => $order->payment_reference,
            'event_id' => 'EVT-LOG-COMPANY-OVERRIDE-' . $order->id . '-R' . $revision . '-' . $suffix,
            'beneficiary_type' => $beneficiaryId ? LogisticsCompanyProfile::class : null,
            'beneficiary_id' => $beneficiaryId,
            'account_name' => $account,
            'entry_type' => $entryType,
            'amount' => $amount,
            'currency' => 'NGN',
            'description' => $description . ' Approved by admin #' . $admin->id . '.',
        ]);
    }
}
