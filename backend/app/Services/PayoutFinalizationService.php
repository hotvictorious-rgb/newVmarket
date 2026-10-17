<?php

namespace App\Services;

use App\Models\FinancialJournalEntry;
use App\Models\LogisticsCompanyProfile;
use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorPayoutAllocation;
use App\Models\VendorPayoutRequest;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutFinalizationService
{
    /**
     * Authoritative idempotent finalization of a Vendor Payout.
     * Guaranteed to produce identical, balanced accounting whether triggered
     * by Admin Disburse, Gateway Webhook (transfer.success), Manual Reconcile, or Scheduled Reconciler.
     */
    public function finalizeVendorPayout(VendorPayoutRequest $payout, array $gatewayData = [], ?User $admin = null): array
    {
        return DB::transaction(function () use ($payout, $gatewayData, $admin) {
            $lockedPayout = VendorPayoutRequest::with(['vendor', 'allocations'])
                ->where('id', $payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingAllocationsCount = $lockedPayout->allocations()->where('status', 'allocated')->count();

            // 1. Idempotency Guard: If already allocated, ensure terminal state without duplicating ledger/allocations
            if ($existingAllocationsCount > 0) {
                $lockedPayout->status = 'approved';
                $lockedPayout->gateway_status = 'success';
                $lockedPayout->processing_token = null;
                $lockedPayout->processing_started_at = null;
                $lockedPayout->disbursed_at = $lockedPayout->disbursed_at ?? Carbon::now();
                if ($admin && !$lockedPayout->approved_by_admin_id) {
                    $lockedPayout->approved_by_admin_id = $admin->id;
                }
                if (!empty($gatewayData['transfer_code'])) {
                    $lockedPayout->paystack_transfer_code = $gatewayData['transfer_code'];
                }
                if (!empty($gatewayData['reference'])) {
                    $lockedPayout->transfer_reference = $gatewayData['reference'];
                }
                $lockedPayout->save();

                return [
                    'success' => true,
                    'idempotent' => true,
                    'payout' => $lockedPayout,
                    'message' => 'Payout already allocated and finalized.',
                ];
            }

            // 2. State Transition & Evidence Tracking
            $transferCode = $gatewayData['transfer_code'] ?? $lockedPayout->paystack_transfer_code;
            $transferRef = $gatewayData['reference'] ?? ($lockedPayout->transfer_reference ?? ('VND-DISBURSE-' . $lockedPayout->id));
            $proofPath = $transferCode ? ('paystack_transfer:' . $transferCode) : ('paystack_transfer_ref:' . $transferRef);
            $vendor = $lockedPayout->vendor;

            $lockedPayout->status = 'approved';
            $lockedPayout->gateway_status = 'success';
            $lockedPayout->processing_token = null;
            $lockedPayout->processing_started_at = null;
            $lockedPayout->disbursed_at = Carbon::now();
            $lockedPayout->approved_by_admin_id = $admin?->id ?? $lockedPayout->approved_by_admin_id;
            $lockedPayout->paystack_transfer_code = $transferCode;
            $lockedPayout->transfer_reference = $transferRef;
            $lockedPayout->settlement_proof_path = $lockedPayout->settlement_proof_path ?? $proofPath;
            $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . 'Paystack Gateway Delivery Confirmed (' . ($transferCode ?? $transferRef) . ')';
            $lockedPayout->save();

            // 3. Exact Order Allocations under Row Locks
            $remainingToSettle = (string)$lockedPayout->amount;
            $eligibleOrders = Order::where('vendor_id', $lockedPayout->vendor_id)
                ->where('vendor_settlement_status', 'eligible')
                ->where('payment_status', 'paid')
                ->where('order_status', 'delivered')
                ->whereDoesntHave('returnCases', function ($q) {
                    $q->whereNotIn('status', ['resolved', 'rejected']);
                })
                ->whereDoesntHave('dispute', function ($q) {
                    $q->whereIn('status', ['filed', 'under_review']);
                })
                ->with(['payoutAllocations.payoutRequest'])
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($eligibleOrders as $order) {
                if (bccomp($remainingToSettle, '0.00', 2) <= 0) {
                    break;
                }
                $orderAvailable = $order->remaining_payable_to_vendor;
                if (bccomp($orderAvailable, '0.00', 2) <= 0) {
                    continue;
                }

                $allocated = (bccomp($remainingToSettle, $orderAvailable, 2) >= 0) ? $orderAvailable : $remainingToSettle;

                VendorPayoutAllocation::create([
                    'vendor_payout_request_id' => $lockedPayout->id,
                    'order_id' => $order->id,
                    'allocated_amount' => $allocated,
                    'status' => 'allocated',
                ]);

                $remainingToSettle = bcsub($remainingToSettle, $allocated, 2);

                // Reload and check if order is fully settled
                $order->refresh();
                if (bccomp($order->remaining_payable_to_vendor, '0.00', 2) <= 0) {
                    $order->update(['vendor_settlement_status' => 'settled']);
                }
            }

            // 4. Balanced Double-Entry Financial Journal Entries
            $formattedPayoutAmount = bcadd((string)$lockedPayout->amount, '0', 2);
            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $transferRef,
                'event_id' => 'EVT-VND-PAYOUT-' . $lockedPayout->id,
                'beneficiary_type' => VendorProfile::class,
                'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                'account_name' => 'vendor_payable',
                'entry_type' => 'debit',
                'amount' => $formattedPayoutAmount,
                'currency' => 'NGN',
                'description' => "Merchant withdrawal liability discharged via Paystack automated transfer ({$transferCode}) to " . ($vendor?->business_name ?? 'Vendor'),
            ]);

            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $transferRef,
                'event_id' => 'EVT-VND-PAYOUT-' . $lockedPayout->id,
                'beneficiary_type' => VendorProfile::class,
                'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                'account_name' => 'cash_in_escrow',
                'entry_type' => 'credit',
                'amount' => $formattedPayoutAmount,
                'currency' => 'NGN',
                'description' => "Escrow cash disbursed via Paystack automated transfer ({$transferCode}) to " . ($vendor?->business_name ?? 'Vendor'),
            ]);

            // 5. Notify Merchant
            NotificationDispatchService::dispatchPayoutDisbursed($lockedPayout);

            return [
                'success' => true,
                'idempotent' => false,
                'payout' => $lockedPayout,
                'message' => 'Vendor payout finalized successfully with complete allocations and journal records.',
            ];
        });
    }

    /**
     * Authoritative idempotent finalization of a Logistics Payout.
     */
    public function finalizeLogisticsPayout(LogisticsPayoutRequest $payout, array $gatewayData = [], ?User $admin = null): array
    {
        return DB::transaction(function () use ($payout, $gatewayData, $admin) {
            $lockedPayout = LogisticsPayoutRequest::with('company')
                ->where('id', $payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 1. Idempotency Guard
            if ($lockedPayout->status === 'approved' && $lockedPayout->gateway_status === 'success' && $lockedPayout->disbursed_at !== null) {
                return [
                    'success' => true,
                    'idempotent' => true,
                    'payout' => $lockedPayout,
                    'message' => 'Logistics payout already finalized.',
                ];
            }

            // 2. State Transition
            $transferCode = $gatewayData['transfer_code'] ?? $lockedPayout->paystack_transfer_code;
            $transferRef = $gatewayData['reference'] ?? ($lockedPayout->transfer_reference ?? ('FLEET-DISBURSE-' . $lockedPayout->id));
            $proofPath = $transferCode ? ('paystack_transfer:' . $transferCode) : ('paystack_transfer_ref:' . $transferRef);
            $company = $lockedPayout->company;

            $lockedPayout->status = 'approved';
            $lockedPayout->gateway_status = 'success';
            $lockedPayout->processing_token = null;
            $lockedPayout->processing_started_at = null;
            $lockedPayout->disbursed_at = Carbon::now();
            $lockedPayout->approved_by_admin_id = $admin?->id ?? $lockedPayout->approved_by_admin_id;
            $lockedPayout->paystack_transfer_code = $transferCode;
            $lockedPayout->transfer_reference = $transferRef;
            $lockedPayout->settlement_proof_path = $lockedPayout->settlement_proof_path ?? $proofPath;
            $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . 'Paystack Gateway Delivery Confirmed (' . ($transferCode ?? $transferRef) . ')';
            $lockedPayout->save();

            // 3. Balanced Double-Entry Financial Journal Entries
            $formattedPayoutAmount = bcadd((string)$lockedPayout->amount, '0', 2);
            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $transferRef,
                'event_id' => 'EVT-FLEET-PAYOUT-' . $lockedPayout->id,
                'beneficiary_type' => LogisticsCompanyProfile::class,
                'beneficiary_id' => $company ? $company->id : $lockedPayout->logistics_company_id,
                'account_name' => 'logistics_payout_payable',
                'entry_type' => 'debit',
                'amount' => $formattedPayoutAmount,
                'currency' => 'NGN',
                'description' => "Logistics fleet payout liability discharged via Paystack automated transfer ({$transferCode}) to " . ($company?->company_name ?? 'Fleet'),
            ]);

            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $transferRef,
                'event_id' => 'EVT-FLEET-PAYOUT-' . $lockedPayout->id,
                'beneficiary_type' => LogisticsCompanyProfile::class,
                'beneficiary_id' => $company ? $company->id : $lockedPayout->logistics_company_id,
                'account_name' => 'cash_in_escrow',
                'entry_type' => 'credit',
                'amount' => $formattedPayoutAmount,
                'currency' => 'NGN',
                'description' => "Escrow cash disbursed via Paystack automated transfer ({$transferCode}) to " . ($company?->company_name ?? 'Fleet'),
            ]);

            // 4. Notify Fleet Owner
            NotificationDispatchService::dispatchPayoutDisbursed($lockedPayout);

            return [
                'success' => true,
                'idempotent' => false,
                'payout' => $lockedPayout,
                'message' => 'Logistics payout finalized successfully.',
            ];
        });
    }

    /**
     * Authoritative safe reversal of a failed/reversed Vendor Payout.
     */
    public function revertVendorPayout(VendorPayoutRequest $payout, string $reason, ?User $admin = null): array
    {
        return DB::transaction(function () use ($payout, $reason, $admin) {
            $lockedPayout = VendorPayoutRequest::with(['allocations.order', 'vendor'])
                ->where('id', $payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Restore ONLY the exact orders that were allocated to this failed payout request (Finding 1 & P1)
            $allocatedItems = $lockedPayout->allocations()->where('status', 'allocated')->get();
            if ($allocatedItems->isNotEmpty()) {
                foreach ($allocatedItems as $alloc) {
                    $alloc->update([
                        'status' => 'reversed',
                        'reversed_at' => Carbon::now(),
                    ]);
                    $order = $alloc->order;
                    if ($order && $order->vendor_settlement_status === 'settled') {
                        $order->update([
                            'vendor_settlement_status' => 'eligible',
                        ]);
                    }
                }
            } else {
                // Audit Finding D: Halt Unsafe Guessed Reversals
                Log::alert("Payout Reversal: Payout #{$lockedPayout->id} has no allocation records. Halting order reversal for manual treasury review.");
                $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . 'Order allocations missing; orders not reversed to prevent ledger corruption. Requires treasury audit.';
            }

            $lockedPayout->status = 'rejected';
            $lockedPayout->gateway_status = 'failed';
            $lockedPayout->processing_token = null;
            $lockedPayout->processing_started_at = null;
            $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . 'Transfer Reversal: ' . $reason;
            $lockedPayout->save();

            $formattedPayoutAmount = bcadd((string)$lockedPayout->amount, '0', 2);
            $revRef = 'REV-VND-' . $lockedPayout->id . '-' . date('YmdHis');
            $vendor = $lockedPayout->vendor;

            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $revRef,
                'event_id' => 'EVT-REV-VND-' . $lockedPayout->id,
                'beneficiary_type' => VendorProfile::class,
                'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                'account_name' => 'vendor_escrow_holding',
                'entry_type' => 'credit',
                'amount' => $formattedPayoutAmount,
                'currency' => 'NGN',
                'description' => "Merchant escrow holding restored after Paystack transfer failure: " . ($vendor?->business_name ?? 'Merchant'),
            ]);

            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $revRef,
                'event_id' => 'EVT-REV-VND-' . $lockedPayout->id,
                'beneficiary_type' => VendorProfile::class,
                'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                'account_name' => 'cash_in_escrow',
                'entry_type' => 'debit',
                'amount' => $formattedPayoutAmount,
                'currency' => 'NGN',
                'description' => "Escrow cash restored after Paystack transfer failure: " . ($vendor?->business_name ?? 'Merchant'),
            ]);

            return ['success' => true, 'payout' => $lockedPayout];
        });
    }

    /**
     * Authoritative safe reversal of a failed/reversed Logistics Payout.
     */
    public function revertLogisticsPayout(LogisticsPayoutRequest $payout, string $reason, ?User $admin = null): array
    {
        return DB::transaction(function () use ($payout, $reason, $admin) {
            $lockedPayout = LogisticsPayoutRequest::with('company')
                ->where('id', $payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            $company = $lockedPayout->company;
            $wallet = $company?->wallet;
            if ($wallet) {
                $wallet->lockForUpdate();
                $wallet->balance = bcadd((string)$wallet->balance, (string)$payout->amount, 2);
                $wallet->save();
            }

            if ($company) {
                $company->wallet_balance = bcadd((string)$company->wallet_balance, (string)$payout->amount, 2);
                $company->save();
            }

            $lockedPayout->status = 'rejected';
            $lockedPayout->gateway_status = 'failed';
            $lockedPayout->processing_token = null;
            $lockedPayout->processing_started_at = null;
            $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . 'Transfer Reversal: ' . $reason;
            $lockedPayout->save();

            $formattedPayoutAmount = bcadd((string)$lockedPayout->amount, '0', 2);
            $revRef = 'REV-FLEET-' . $lockedPayout->id . '-' . date('YmdHis');

            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $revRef,
                'event_id' => 'EVT-REV-FLEET-' . $lockedPayout->id,
                'beneficiary_type' => LogisticsCompanyProfile::class,
                'beneficiary_id' => $company ? $company->id : $lockedPayout->logistics_company_id,
                'account_name' => 'logistics_payout_payable',
                'entry_type' => 'credit',
                'amount' => $formattedPayoutAmount,
                'currency' => 'NGN',
                'description' => "Corporate payout liability restored after Paystack transfer failure: " . ($company?->company_name ?? 'Logistics'),
            ]);

            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => $revRef,
                'event_id' => 'EVT-REV-FLEET-' . $lockedPayout->id,
                'beneficiary_type' => LogisticsCompanyProfile::class,
                'beneficiary_id' => $company ? $company->id : $lockedPayout->logistics_company_id,
                'account_name' => 'cash_in_escrow',
                'entry_type' => 'debit',
                'amount' => $formattedPayoutAmount,
                'currency' => 'NGN',
                'description' => "Escrow cash restored after Paystack transfer failure: " . ($company?->company_name ?? 'Logistics'),
            ]);

            return ['success' => true, 'payout' => $lockedPayout];
        });
    }
}