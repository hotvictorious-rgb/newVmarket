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
     * Assign an approved logistics company after supplier availability is confirmed.
     * If payment settlement already posted the default payable to suspense, reclassify
     * it to the selected company with balanced immutable journal entries.
     */
    public function assignCompany(Order $order, User $admin, int $companyId, string $reason): array
    {
        if ($admin->status !== 'active' || $admin->user_type !== 'super_admin') {
            throw new AuthorizationException('Only an active Super Admin may assign a logistics company.');
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw new InvalidArgumentException('A reason of 1–2000 characters is required for logistics assignment.');
        }

        return DB::transaction(function () use ($order, $admin, $companyId, $reason) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new RuntimeException('Order not found.');
            }
            if (!$locked->retailer_model_order || $locked->fulfillment_type !== 'doorstep_delivery') {
                throw new InvalidArgumentException('Only retailer-model doorstep orders can be assigned to a logistics company.');
            }
            if ($locked->payment_status !== 'paid' || $locked->sourcing_status !== 'supplier_confirmed') {
                throw new InvalidArgumentException('Verified customer payment and confirmed supplier availability are required before logistics assignment.');
            }
            if (in_array($locked->order_status, ['delivered', 'returned', 'cancelled', 'canceled', 'refunded'], true)
                || $locked->received_at !== null || $locked->rider_id !== null) {
                throw new InvalidArgumentException('The logistics company must be assigned before rider assignment, customer handover, or a terminal order outcome.');
            }
            $company = LogisticsCompanyProfile::query()
                ->whereKey($companyId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->first();
            if (!$company) {
                throw new InvalidArgumentException('The selected logistics company must be active and approved.');
            }
            $companyHqLga = $company->hqLga()->with('state')->first();
            if (strtolower(trim((string) ($companyHqLga->name ?? ''))) !== 'uyo'
                || strtolower(trim((string) ($companyHqLga->state->name ?? ''))) !== 'akwa ibom') {
                throw new InvalidArgumentException('All approved Victorious MARKET logistics companies must be physically based in Uyo, Akwa Ibom State.');
            }

            $oldCompanyId = $locked->logistics_company_id;
            if ((int) ($oldCompanyId ?? 0) === (int) $company->id
                && (int) ($locked->logistics_company_payable_ledger_company_id ?? 0) === (int) $company->id) {
                return ['success' => true, 'replayed' => true, 'order' => $locked];
            }
            if ((int) ($oldCompanyId ?? 0) === (int) $company->id
                && $locked->logistics_company_payable_ledger_company_id === null
                && $locked->logistics_company_payable_amount === null
                && $locked->logistics_company_payable_default_amount === null
                && FinancialJournalEntry::query()
                    ->where('order_id', $locked->id)
                    ->where('account_name', 'logistics_payable_suspense')
                    ->where('entry_type', 'credit')
                    ->exists()) {
                return ['success' => true, 'replayed' => true, 'payable_held_for_tariff' => true, 'order' => $locked];
            }

            $revision = (int) $locked->logistics_company_payable_override_revision + 1;
            $amount = $locked->logistics_company_payable_amount
                ?? $locked->logistics_company_payable_default_amount;

            $baseline = FinancialJournalEntry::query()
                ->where('order_id', $locked->id)
                ->where('account_name', 'logistics_company_payable')
                ->where('entry_type', 'credit')
                ->exists();
            $suspenseCreditExists = FinancialJournalEntry::query()
                ->where('order_id', $locked->id)
                ->where('account_name', 'logistics_payable_suspense')
                ->where('entry_type', 'credit')
                ->exists();

            if (!$baseline && $suspenseCreditExists && ($amount === null || !is_numeric($amount) || BcMathService::comp((string) $amount, '0.00') <= 0)) {
                // The payment was settled while the independent tariff was missing.
                // Assign the accountable company but keep its payable ledger unassigned
                // until a Super Admin records the explicit tariff reconciliation.
                $locked->logistics_company_id = $company->id;
                $locked->logistics_company_payable_ledger_company_id = null;
                $locked->logistics_company_payable_override_revision = $revision;
                $locked->save();
                AdminAuditLog::create([
                    'user_id' => $admin->id,
                    'action' => 'logistics_company_assigned_payable_held_for_tariff',
                    'target_type' => Order::class,
                    'target_id' => $locked->id,
                    'old_values' => ['logistics_company_id' => $oldCompanyId, 'payable_ledger_company_id' => $locked->getOriginal('logistics_company_payable_ledger_company_id')],
                    'new_values' => [
                        'logistics_company_id' => $company->id,
                        'company_name' => $company->company_name,
                        'payable_status' => 'held_in_suspense_pending_explicit_tariff_reconciliation',
                        'assignment_reason' => $reason,
                        'assigned_by' => $admin->id,
                        'revision' => $revision,
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent(),
                ]);

                return ['success' => true, 'replayed' => false, 'payable_held_for_tariff' => true, 'order' => $locked];
            }

            if ($amount === null || !is_numeric($amount) || BcMathService::comp((string) $amount, '0.00') <= 0) {
                throw new RuntimeException('The immutable logistics company/corridor payable snapshot is missing or invalid; reconcile the tariff before assigning a company.');
            }
            $amount = (string) $amount;

            if (!$baseline) {
                throw new RuntimeException('The original logistics-company payable journal is missing; reconcile before assigning a company.');
            }

            $ledgerCompanyId = $locked->logistics_company_payable_ledger_company_id;
            if ($baseline && (int) ($ledgerCompanyId ?? 0) !== (int) $company->id
                && BcMathService::comp($amount, '0.00') > 0) {
                $this->journal($locked, $admin, $revision, 'assignment-reclass-debit', 'logistics_company_payable', 'debit', $amount, $ledgerCompanyId ? (int) $ledgerCompanyId : null, 'Reclassify payable from prior beneficiary/suspense.');
                $this->journal($locked, $admin, $revision, 'assignment-reclass-credit', 'logistics_company_payable', 'credit', $amount, $company->id, 'Assign payable to the approved logistics company.');
            }

            $locked->logistics_company_id = $company->id;
            $locked->logistics_company_payable_ledger_company_id = $company->id;
            $locked->logistics_company_payable_override_revision = $revision;
            $locked->save();

            AdminAuditLog::create([
                'user_id' => $admin->id,
                'action' => 'logistics_company_assigned',
                'target_type' => Order::class,
                'target_id' => $locked->id,
                'old_values' => [
                    'logistics_company_id' => $oldCompanyId,
                    'payable_ledger_company_id' => $ledgerCompanyId,
                ],
                'new_values' => [
                    'logistics_company_id' => $company->id,
                    'company_name' => $company->company_name,
                    'payable_amount' => $amount,
                    'assignment_reason' => $reason,
                    'assigned_by' => $admin->id,
                    'revision' => $revision,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return ['success' => true, 'replayed' => false, 'order' => $locked];
        });
    }

    /**
     * Release the approved company payable to the company wallet exactly once after
     * server-verified customer handover and proof. This does not pay the rider.
     */
    public function releaseAfterVerifiedDelivery(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new RuntimeException('Order not found.');
            }
            if (!$locked->retailer_model_order) {
                return ['success' => true, 'skipped' => true, 'replayed' => false];
            }
            if ($locked->order_status !== 'delivered'
                || $locked->received_at === null
                || !$locked->delivery_confirmed_by_customer
                || empty($locked->proof_of_delivery_image)) {
                throw new InvalidArgumentException('Verified delivery OTP, completion state and photo proof are required before company wallet release.');
            }
            if ($locked->logistics_company_wallet_credited_at !== null) {
                return ['success' => true, 'skipped' => false, 'replayed' => true, 'order' => $locked];
            }
            if (!$locked->logistics_company_id) {
                throw new InvalidArgumentException('An approved logistics company must be assigned before release.');
            }

            $company = LogisticsCompanyProfile::query()
                ->whereKey($locked->logistics_company_id)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();
            if (!$company) {
                throw new InvalidArgumentException('The assigned logistics company must remain active at delivery release.');
            }

            $amount = $locked->logistics_company_payable_amount
                ?? $locked->logistics_company_payable_default_amount;
            if ($amount === null || !is_numeric($amount) || BcMathService::comp((string) $amount, '0.00') <= 0) {
                AdminAuditLog::create([
                    'user_id' => $locked->customer_id,
                    'action' => 'logistics_payable_release_blocked_missing_tariff',
                    'target_type' => Order::class,
                    'target_id' => $locked->id,
                    'old_values' => ['logistics_company_payable_amount' => null],
                    'new_values' => [
                        'release' => 'blocked',
                        'reason' => 'Missing or invalid immutable company/corridor payable snapshot',
                        'order_status' => $locked->order_status,
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent(),
                ]);

                return [
                    'success' => false,
                    'skipped' => true,
                    'blocked' => true,
                    'reason' => 'Missing or invalid immutable logistics-company payable snapshot; company wallet was not credited.',
                    'order' => $locked,
                ];
            }
            $amount = (string) $amount;

            if ((int) ($locked->logistics_company_payable_ledger_company_id ?? 0) !== (int) $locked->logistics_company_id) {
                throw new InvalidArgumentException('The logistics payable must be assigned to the active company ledger before release.');
            }

            if (BcMathService::comp($amount, '0.00') > 0) {
                $company->creditWallet($amount);
                FinancialJournalEntry::create([
                    'order_id' => $locked->id,
                    'transaction_reference' => $locked->payment_reference ?? $locked->order_number,
                    'event_id' => 'EVT-LOG-COMPANY-RELEASE-' . $locked->id . '-PAYABLE',
                    'beneficiary_type' => LogisticsCompanyProfile::class,
                    'beneficiary_id' => $company->id,
                    'account_name' => 'logistics_company_payable',
                    'entry_type' => 'debit',
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'description' => 'Release approved retailer logistics payable to the company wallet after verified delivery.',
                ]);
                FinancialJournalEntry::create([
                    'order_id' => $locked->id,
                    'transaction_reference' => $locked->payment_reference ?? $locked->order_number,
                    'event_id' => 'EVT-LOG-COMPANY-RELEASE-' . $locked->id . '-WALLET',
                    'beneficiary_type' => LogisticsCompanyProfile::class,
                    'beneficiary_id' => $company->id,
                    'account_name' => 'logistics_company_wallet',
                    'entry_type' => 'credit',
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'description' => 'Company wallet credited for verified retailer delivery; this is not a rider payout.',
                ]);
            }

            $locked->logistics_company_wallet_credited_at = now();
            $locked->logistics_company_wallet_credited_amount = $amount;
            $locked->save();

            return ['success' => true, 'skipped' => false, 'replayed' => false, 'order' => $locked];
        });
    }

    /**
     * Admin-only commercial override. Runs under an order lock, records the
     * original/default and final amount, and posts balanced journal adjustments.
     * Ordinary overrides are blocked after verified handover; the sole exception is
     * explicit recovery of an uncredited missing-tariff suspense balance.
     */
    public function override(Order $order, User $admin, string $amount, string $reason): array
    {
        if ($admin->status !== 'active' || $admin->user_type !== 'super_admin') {
            throw new AuthorizationException('Only an active Super Admin may override a logistics-company payable.');
        }
        if (!is_numeric($amount) || BcMathService::comp($amount, '0.00') <= 0) {
            throw new InvalidArgumentException('The approved logistics-company amount must be greater than zero.');
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
            $hasPayableCredit = FinancialJournalEntry::query()
                ->where('order_id', $locked->id)
                ->where('account_name', 'logistics_company_payable')
                ->where('entry_type', 'credit')
                ->exists();
            $hasSuspenseCredit = FinancialJournalEntry::query()
                ->where('order_id', $locked->id)
                ->where('account_name', 'logistics_payable_suspense')
                ->where('entry_type', 'credit')
                ->exists();
            $missingTariffRecoveryCandidate = $locked->logistics_company_payable_default_amount === null
                && $locked->logistics_company_payable_amount === null
                && !$hasPayableCredit
                && $hasSuspenseCredit
                && $locked->logistics_company_wallet_credited_at === null;

            if (!$missingTariffRecoveryCandidate
                && (in_array($locked->order_status, ['delivered', 'returned', 'cancelled', 'canceled', 'refunded'], true)
                    || $locked->received_at !== null || $locked->pickup_confirmed_by_rider === true)) {
                throw new InvalidArgumentException('The payable cannot be overridden after rider custody or customer handover, except to reconcile an explicitly held missing-tariff suspense balance.');
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
            $baselineExists = FinancialJournalEntry::query()
                ->where('order_id', $locked->id)
                ->where('account_name', 'logistics_company_payable')
                ->where('entry_type', 'credit')
                ->exists();
            $suspenseCreditExists = FinancialJournalEntry::query()
                ->where('order_id', $locked->id)
                ->where('account_name', 'logistics_payable_suspense')
                ->where('entry_type', 'credit')
                ->exists();
            $missingTariffRecovery = $default === null && !$baselineExists && $suspenseCreditExists;
            $hasExistingManualPayable = $default === null
                && $baselineExists
                && $locked->logistics_company_payable_amount !== null;

            if ($default === null && !$missingTariffRecovery && !$hasExistingManualPayable) {
                throw new RuntimeException('The immutable logistics-company payable snapshot is missing and no eligible suspense balance or reconciled payable exists; manual reconciliation is required.');
            }
            $oldAmount = $missingTariffRecovery
                ? '0.00'
                : bcadd((string) ($locked->logistics_company_payable_amount ?? $default), '0', 2);
            $revision = (int) $locked->logistics_company_payable_override_revision + 1;

            if (!$missingTariffRecovery
                && BcMathService::comp($oldAmount, $amount) === 0
                && trim((string) $locked->logistics_company_payable_override_reason) === $reason
                && (int) $locked->logistics_company_payable_override_by === (int) $admin->id) {
                return ['success' => true, 'replayed' => true, 'order' => $locked];
            }

            $newMargin = BcMathService::comp($fee, $amount) > 0 ? BcMathService::sub($fee, $amount) : '0.00';
            $newSubsidy = BcMathService::comp($amount, $fee) > 0 ? BcMathService::sub($amount, $fee) : '0.00';
            $ledgerCompanyId = $locked->logistics_company_payable_ledger_company_id;

            if ($missingTariffRecovery) {
                // Replace the suspense credit with an explicitly authorized payable.
                // Revenue/subsidy is recognized only as the difference from the actual customer fee.
                $this->journal($locked, $admin, $revision, 'tariff-recovery-suspense-debit', 'logistics_payable_suspense', 'debit', $fee, null, 'Release customer delivery charge from suspense after explicit tariff reconciliation.');
                $this->journal($locked, $admin, $revision, 'tariff-recovery-company-credit', 'logistics_company_payable', 'credit', $amount, $company->id, 'Create the explicitly approved company payable after missing-tariff reconciliation.');
                if (BcMathService::comp($newMargin, '0.00') > 0) {
                    $this->journal($locked, $admin, $revision, 'tariff-recovery-margin-credit', 'platform_delivery_revenue', 'credit', $newMargin, null, 'Recognize the delivery margin after explicit company tariff reconciliation.');
                }
                if (BcMathService::comp($newSubsidy, '0.00') > 0) {
                    $this->journal($locked, $admin, $revision, 'tariff-recovery-subsidy-debit', 'delivery_subsidy_expense', 'debit', $newSubsidy, null, 'Record VM-funded subsidy after explicit company tariff reconciliation.');
                }
                $locked->logistics_company_payable_ledger_company_id = $company->id;
            } else {
                if (!$baselineExists) {
                    throw new RuntimeException('The original logistics-company payable journal is missing; reconcile before overriding.');
                }

                $oldMargin = BcMathService::comp($fee, $oldAmount) > 0 ? BcMathService::sub($fee, $oldAmount) : '0.00';
                $oldSubsidy = BcMathService::comp($oldAmount, $fee) > 0 ? BcMathService::sub($oldAmount, $fee) : '0.00';

                if ((int) ($ledgerCompanyId ?? 0) !== (int) $company->id) {
                    $this->journal($locked, $admin, $revision, 'company-reclass-debit', 'logistics_company_payable', 'debit', $oldAmount, $ledgerCompanyId ? (int) $ledgerCompanyId : null, 'Reclassify unsettled logistics payable from prior company.');
                    $this->journal($locked, $admin, $revision, 'company-reclass-credit', 'logistics_company_payable', 'credit', $oldAmount, $company->id, 'Assign payable to the approved logistics company.');
                    $locked->logistics_company_payable_ledger_company_id = $company->id;
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
            $locked->logistics_subsidy_amount = $newSubsidy;
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

            $walletRelease = null;
            if ($missingTariffRecovery
                && $locked->order_status === 'delivered'
                && $locked->received_at !== null
                && $locked->delivery_confirmed_by_customer
                && !empty($locked->proof_of_delivery_image)) {
                // This wallet credit follows an explicit Super Admin reconciliation
                // action and existing delivery proof; it is not an automated bank transfer.
                $walletRelease = $this->releaseAfterVerifiedDelivery($locked);
            }

            return [
                'success' => true,
                'replayed' => false,
                'payable_recovered_from_suspense' => $missingTariffRecovery,
                'wallet_release' => $walletRelease,
                'order' => $locked->fresh(),
            ];
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
