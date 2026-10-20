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
    public function finalizeVendorPayout(VendorPayoutRequest $payout, array $gatewayData = [], ?User $admin = null, ?string $expectedProcessingToken = null): array
    {
        $formattedPayoutAmount = bcadd((string)$payout->amount, '0', 2);

        $result = DB::transaction(function () use ($payout, $gatewayData, $admin, $formattedPayoutAmount, $expectedProcessingToken) {
            $lockedPayout = VendorPayoutRequest::with(['vendor', 'allocations'])
                ->where('id', $payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Fence reconciliation inside the row-locked transaction to close the
            // check-then-finalize race if another worker takes over an expired lease.
            if ($expectedProcessingToken !== null && $lockedPayout->processing_token !== $expectedProcessingToken) {
                return ['success' => false, 'protected' => true, 'idempotent' => true, 'payout' => $lockedPayout,
                    'message' => 'Vendor payout reconciliation lease was lost before finalization.'];
            }

            // A terminal failed/rejected payout must not be reopened by a delayed success
            // webhook after its wallet/allocation/journal reversal has already been applied.
            if ($lockedPayout->status === 'rejected' && $lockedPayout->gateway_status === 'failed') {
                return [
                    'success' => false,
                    'protected' => true,
                    'idempotent' => true,
                    'payout' => $lockedPayout,
                    'message' => 'Rejected vendor payout remains terminal; delayed success requires manual reconciliation.',
                ];
            }

            $existingAllocationsSum = (string)$lockedPayout->allocations()->where('status', 'allocated')->sum('allocated_amount');
            $hasJournalEntry = FinancialJournalEntry::where('event_id', 'EVT-VND-PAYOUT-' . $lockedPayout->id)->exists();
            $hasRequestJournal = FinancialJournalEntry::where('event_id', 'EVT-MERCHANT-REQ-' . $lockedPayout->id)->exists();

            // 1. Idempotency Guard: If already allocated and journaled, ensure terminal state without duplicating ledger/allocations
            if ($lockedPayout->status === 'approved' && $lockedPayout->gateway_status === 'success' && $hasJournalEntry) {
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

            // Terminal Gate: Require verified gateway confirmation for new finalization
            $gwStatus = strtolower((string)($gatewayData['status'] ?? ''));
            if (!in_array($gwStatus, ['success', 'processed', 'completed'], true)) {
                throw new \InvalidArgumentException("Payout finalization requires verified gateway success. Received gateway status: '{$gwStatus}'");
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
            $lockedPayout->disbursed_at = $lockedPayout->disbursed_at ?? Carbon::now();
            $lockedPayout->approved_by_admin_id = $admin?->id ?? $lockedPayout->approved_by_admin_id;
            $lockedPayout->paystack_transfer_code = $transferCode;
            $lockedPayout->transfer_reference = $transferRef;
            $lockedPayout->settlement_proof_path = $lockedPayout->settlement_proof_path ?? $proofPath;
            $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . 'Paystack Gateway Delivery Confirmed (' . ($transferCode ?? $transferRef) . ')';
            $lockedPayout->save();

            // 3. Exact Order Allocations under Row Locks
            // If already partially allocated to this request, only settle the remaining difference
            $remainingToSettle = bccomp($existingAllocationsSum, '0.00', 2) > 0
                ? bcsub($formattedPayoutAmount, $existingAllocationsSum, 2)
                : $formattedPayoutAmount;

            $totalNewlyAllocated = '0.00';

            if (bccomp($remainingToSettle, '0.00', 2) > 0) {
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
                    $totalNewlyAllocated = bcadd($totalNewlyAllocated, $allocated, 2);

                    // Reload and check if order is fully settled
                    $order->refresh();
                    if (bccomp($order->remaining_payable_to_vendor, '0.00', 2) <= 0) {
                        $order->update(['vendor_settlement_status' => 'settled']);
                    }
                }
            }

            // Ensure all allocated orders for this payout transition to settled if fully allocated
            $allAllocations = $lockedPayout->allocations()->where('status', 'allocated')->get();
            foreach ($allAllocations as $alloc) {
                $ord = $alloc->order;
                if ($ord && bccomp($ord->remaining_payable_to_vendor, '0.00', 2) <= 0) {
                    $ord->update(['vendor_settlement_status' => 'settled']);
                }
            }

            // 4. Allocation Conservation & Balanced Double-Entry Financial Journal Entries
            // Total allocated across existing + newly allocated
            $grandTotalAllocated = bcadd($existingAllocationsSum, $totalNewlyAllocated, 2);
            $unallocatedDiscrepancy = bcsub($formattedPayoutAmount, $grandTotalAllocated, 2);

            if ($hasRequestJournal) {
                // New lifecycle: the request already reclassified the vendor liability
                // from vendor_payable to vendor_payout_payable. Clear that exact reserved
                // liability when the gateway confirms the transfer, regardless of whether
                // allocation metadata requires a separate manual reconciliation.
                if (!$hasJournalEntry) {
                    FinancialJournalEntry::create([
                        'order_id' => null,
                        'transaction_reference' => $transferRef,
                        'event_id' => 'EVT-VND-PAYOUT-' . $lockedPayout->id,
                        'beneficiary_type' => VendorProfile::class,
                        'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                        'account_name' => 'vendor_payout_payable',
                        'entry_type' => 'debit',
                        'amount' => $formattedPayoutAmount,
                        'currency' => 'NGN',
                        'description' => "Reserved merchant payout liability discharged via Paystack ({$transferCode}) to " . ($vendor?->business_name ?? 'Vendor'),
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
                }
            } elseif (bccomp($unallocatedDiscrepancy, '0.00', 2) > 0) {
                // Audit Finding P0: Payout amount exceeded eligible allocated orders.
                // Log critical alert and route unexplained difference to treasury suspense
                Log::emergency("Treasury Discrepancy: Payout #{$lockedPayout->id} amount (₦{$formattedPayoutAmount}) exceeds eligible order allocations (₦{$grandTotalAllocated}) by ₦{$unallocatedDiscrepancy}. Booking difference to reconciliation suspense.");
                $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . "ALLOCATION_DISCREPANCY: Disbursed ₦{$formattedPayoutAmount} exceeded eligible order allocations by ₦{$unallocatedDiscrepancy}. Suspense booked.";
                $lockedPayout->save();

                if (bccomp($grandTotalAllocated, '0.00', 2) > 0 && !$hasJournalEntry) {
                    FinancialJournalEntry::create([
                        'order_id' => null,
                        'transaction_reference' => $transferRef,
                        'event_id' => 'EVT-VND-PAYOUT-' . $lockedPayout->id,
                        'beneficiary_type' => VendorProfile::class,
                        'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                        'account_name' => 'vendor_payable',
                        'entry_type' => 'debit',
                        'amount' => $grandTotalAllocated,
                        'currency' => 'NGN',
                        'description' => "Merchant withdrawal liability discharged via Paystack ({$transferCode}) to " . ($vendor?->business_name ?? 'Vendor'),
                    ]);
                }

                if (!$hasJournalEntry) {
                    FinancialJournalEntry::create([
                        'order_id' => null,
                        'transaction_reference' => $transferRef,
                        // Keep both sides of this disbursement under one journal event so
                        // the event itself remains balanced; account_name identifies suspense.
                        'event_id' => 'EVT-VND-PAYOUT-' . $lockedPayout->id,
                        'beneficiary_type' => VendorProfile::class,
                        'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                        'account_name' => 'treasury_reconciliation_suspense',
                        'entry_type' => 'debit',
                        'amount' => $unallocatedDiscrepancy,
                        'currency' => 'NGN',
                        'description' => "Treasury suspense booked for unallocated payout discrepancy on Payout #{$lockedPayout->id} to " . ($vendor?->business_name ?? 'Vendor'),
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
                }
            } else {
                // Exact balanced allocation
                if (!$hasJournalEntry) {
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
                }
            }

            return [
                'success' => true,
                'idempotent' => false,
                'payout' => $lockedPayout,
                'message' => 'Vendor payout finalized successfully with complete allocations and journal records.',
            ];
        });

        // 5. Audit Finding P1: Dispatch notification OUTSIDE database transaction
        if (!empty($result['success']) && empty($result['idempotent'])) {
            try {
                NotificationDispatchService::dispatchPayoutDisbursed($result['payout']);
            } catch (\Throwable $e) {
                Log::warning('Payout notification dispatch error: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Authoritative idempotent finalization of a Logistics Payout.
     */
    public function finalizeLogisticsPayout(LogisticsPayoutRequest $payout, array $gatewayData = [], ?User $admin = null, ?string $expectedProcessingToken = null): array
    {
        $result = DB::transaction(function () use ($payout, $gatewayData, $admin, $expectedProcessingToken) {
            $lockedPayout = LogisticsPayoutRequest::with('company')
                ->where('id', $payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedProcessingToken !== null && $lockedPayout->processing_token !== $expectedProcessingToken) {
                return ['success' => false, 'protected' => true, 'idempotent' => true, 'payout' => $lockedPayout,
                    'message' => 'Logistics payout reconciliation lease was lost before finalization.'];
            }

            // A terminal failed/rejected payout must not be reopened by a delayed success
            // webhook after its wallet/journal reversal has already been applied.
            if ($lockedPayout->status === 'rejected' && $lockedPayout->gateway_status === 'failed') {
                return [
                    'success' => false,
                    'protected' => true,
                    'idempotent' => true,
                    'payout' => $lockedPayout,
                    'message' => 'Rejected logistics payout remains terminal; delayed success requires manual reconciliation.',
                ];
            }

            // 1. Idempotency Guard
            if ($lockedPayout->status === 'approved' && $lockedPayout->gateway_status === 'success' && $lockedPayout->disbursed_at !== null) {
                return [
                    'success' => true,
                    'idempotent' => true,
                    'payout' => $lockedPayout,
                    'message' => 'Logistics payout already finalized.',
                ];
            }

            // Terminal Gate: Require verified gateway confirmation for new finalization
            $gwStatus = strtolower((string)($gatewayData['status'] ?? ''));
            if (!in_array($gwStatus, ['success', 'processed', 'completed'], true)) {
                throw new \InvalidArgumentException("Logistics payout finalization requires verified gateway success. Received status: '{$gwStatus}'");
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
            $lockedPayout->disbursed_at = $lockedPayout->disbursed_at ?? Carbon::now();
            $lockedPayout->approved_by_admin_id = $admin?->id ?? $lockedPayout->approved_by_admin_id;
            $lockedPayout->paystack_transfer_code = $transferCode;
            $lockedPayout->transfer_reference = $transferRef;
            $lockedPayout->settlement_proof_path = $lockedPayout->settlement_proof_path ?? $proofPath;
            $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . 'Paystack Gateway Delivery Confirmed (' . ($transferCode ?? $transferRef) . ')';
            $lockedPayout->save();

            // 3. Balanced Double-Entry Financial Journal Entries
            $formattedPayoutAmount = bcadd((string)$lockedPayout->amount, '0', 2);
            $hasJournalEntry = FinancialJournalEntry::where('event_id', 'EVT-FLEET-PAYOUT-' . $lockedPayout->id)->exists();

            if (!$hasJournalEntry) {
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
            }

            return [
                'success' => true,
                'idempotent' => false,
                'payout' => $lockedPayout,
                'message' => 'Logistics payout finalized successfully.',
            ];
        });

        // 4. Audit Finding P1: Dispatch notification OUTSIDE database transaction
        if (!empty($result['success']) && empty($result['idempotent'])) {
            try {
                NotificationDispatchService::dispatchPayoutDisbursed($result['payout']);
            } catch (\Throwable $e) {
                Log::warning('Logistics payout notification dispatch error: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Authoritative safe reversal of a failed/reversed Vendor Payout.
     */
    public function revertVendorPayout(VendorPayoutRequest $payout, string $reason, ?User $admin = null, bool $allowSuccessfulReversal = false, ?string $expectedProcessingToken = null): array
    {
        $result = DB::transaction(function () use ($payout, $reason, $admin, $allowSuccessfulReversal, $expectedProcessingToken) {
            $lockedPayout = VendorPayoutRequest::with(['allocations.order', 'vendor'])
                ->where('id', $payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedProcessingToken !== null && $lockedPayout->processing_token !== $expectedProcessingToken) {
                return ['success' => false, 'protected' => true, 'idempotent' => true, 'payout' => $lockedPayout,
                    'message' => 'Vendor payout reconciliation lease was lost before reversal.'];
            }

            // Terminal Guard (P0): Idempotent if already rejected/failed
            if ($lockedPayout->status === 'rejected' && $lockedPayout->gateway_status === 'failed') {
                return [
                    'success' => true,
                    'idempotent' => true,
                    'payout' => $lockedPayout,
                    'message' => 'Vendor payout already marked failed/reverted.',
                ];
            }

            // A stale failure/reconciliation result must not undo a confirmed payout.
            // Only an explicit transfer.reversed event may reverse a successful transfer.
            if (
                !$allowSuccessfulReversal
                && strtolower((string) $lockedPayout->gateway_status) === 'success'
                && strtolower((string) $lockedPayout->status) === 'approved'
            ) {
                return [
                    'success' => false,
                    'protected' => true,
                    'idempotent' => true,
                    'payout' => $lockedPayout,
                    'message' => 'Confirmed successful vendor payout protected from stale failure signal.',
                ];
            }

            // Restore ONLY the exact orders that were allocated to this failed payout request
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
                Log::alert("Payout Reversal: Payout #{$lockedPayout->id} has no allocation records. Halting order reversal for manual treasury review.");
                $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . 'Order allocations missing; orders not reversed to prevent ledger corruption. Requires treasury audit.';
            }

            $lockedPayout->status = 'rejected';
            $lockedPayout->gateway_status = 'failed';
            $lockedPayout->rejection_reason = $reason;
            $lockedPayout->processing_token = null;
            $lockedPayout->processing_started_at = null;
            $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . 'Transfer Reversal: ' . $reason;
            $lockedPayout->save();

            // A queued/failed transfer may never have posted the disbursement journal.
            // Only write a financial reversal when the original payout journal exists;
            // otherwise release allocations/state without fabricating a cash movement.
            $hasOriginalPayoutJournal = FinancialJournalEntry::where('event_id', 'EVT-VND-PAYOUT-' . $lockedPayout->id)->exists();
            $hasRequestJournal = FinancialJournalEntry::where('event_id', 'EVT-MERCHANT-REQ-' . $lockedPayout->id)->exists();

            if ($hasOriginalPayoutJournal && $hasRequestJournal) {
                // A confirmed transfer was reversed. Undo cash movement and restore
                // the vendor liability reclassified when the payout was requested.
                $formattedPayoutAmount = bcadd((string)$lockedPayout->amount, '0', 2);
                $revRef = 'REV-VND-' . $lockedPayout->id . '-' . date('YmdHis');
                $vendor = $lockedPayout->vendor;
                FinancialJournalEntry::create([
                    'order_id' => null, 'transaction_reference' => $revRef,
                    'event_id' => 'EVT-REV-VND-' . $lockedPayout->id,
                    'beneficiary_type' => VendorProfile::class,
                    'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                    'account_name' => 'cash_in_escrow', 'entry_type' => 'debit',
                    'amount' => $formattedPayoutAmount, 'currency' => 'NGN',
                    'description' => "Escrow cash restored after confirmed Paystack transfer reversal: " . ($vendor?->business_name ?? 'Merchant'),
                ]);
                FinancialJournalEntry::create([
                    'order_id' => null, 'transaction_reference' => $revRef,
                    'event_id' => 'EVT-REV-VND-' . $lockedPayout->id,
                    'beneficiary_type' => VendorProfile::class,
                    'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                    'account_name' => 'vendor_payable', 'entry_type' => 'credit',
                    'amount' => $formattedPayoutAmount, 'currency' => 'NGN',
                    'description' => "Merchant payable liability restored after confirmed Paystack transfer reversal: " . ($vendor?->business_name ?? 'Merchant'),
                ]);
            } elseif ($hasOriginalPayoutJournal) {
                $formattedPayoutAmount = bcadd((string)$lockedPayout->amount, '0', 2);
                $revRef = 'REV-VND-' . $lockedPayout->id . '-' . date('YmdHis');
                $vendor = $lockedPayout->vendor;
    
                $allocatedSum = (string)$lockedPayout->allocations()->where('status', 'reversed')->sum('allocated_amount');
                $discrepancy = bcsub($formattedPayoutAmount, $allocatedSum, 2);
    
                if (bccomp($discrepancy, '0.00', 2) > 0) {
                    // Symmetrical reversal of treasury suspense debit; group it with the cash/payable reversal so this journal event balances.
                    FinancialJournalEntry::create([
                        'order_id' => null,
                        'transaction_reference' => $revRef,
                        'event_id' => 'EVT-REV-VND-' . $lockedPayout->id,
                        'beneficiary_type' => VendorProfile::class,
                        'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                        'account_name' => 'treasury_reconciliation_suspense',
                        'entry_type' => 'credit',
                        'amount' => $discrepancy,
                        'currency' => 'NGN',
                        'description' => "Treasury suspense reversed after failed transfer on Payout #{$lockedPayout->id}",
                    ]);
    
                    if (bccomp($allocatedSum, '0.00', 2) > 0) {
                        FinancialJournalEntry::create([
                            'order_id' => null,
                            'transaction_reference' => $revRef,
                            'event_id' => 'EVT-REV-VND-' . $lockedPayout->id,
                            'beneficiary_type' => VendorProfile::class,
                            'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                            'account_name' => 'vendor_payable',
                            'entry_type' => 'credit',
                            'amount' => $allocatedSum,
                            'currency' => 'NGN',
                            'description' => "Merchant payable liability restored after Paystack transfer failure: " . ($vendor?->business_name ?? 'Merchant'),
                        ]);
                    }
                } else {
                    // Exact restoration of original vendor_payable liability debited during payout
                    FinancialJournalEntry::create([
                        'order_id' => null,
                        'transaction_reference' => $revRef,
                        'event_id' => 'EVT-REV-VND-' . $lockedPayout->id,
                        'beneficiary_type' => VendorProfile::class,
                        'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                        'account_name' => 'vendor_payable',
                        'entry_type' => 'credit',
                        'amount' => $formattedPayoutAmount,
                        'currency' => 'NGN',
                        'description' => "Merchant payable liability restored after Paystack transfer failure: " . ($vendor?->business_name ?? 'Merchant'),
                    ]);
                }
    
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
    
    
            } elseif ($hasRequestJournal) {
                // Failure before successful disbursement: reverse only the request-time
                // reclassification; do not invent a cash movement.
                $formattedPayoutAmount = bcadd((string)$lockedPayout->amount, '0', 2);
                $revRef = 'REV-VND-REQUEST-' . $lockedPayout->id . '-' . date('YmdHis');
                $vendor = $lockedPayout->vendor;
                FinancialJournalEntry::create([
                    'order_id' => null, 'transaction_reference' => $revRef,
                    'event_id' => 'EVT-REV-VND-' . $lockedPayout->id,
                    'beneficiary_type' => VendorProfile::class,
                    'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                    'account_name' => 'vendor_payout_payable', 'entry_type' => 'debit',
                    'amount' => $formattedPayoutAmount, 'currency' => 'NGN',
                    'description' => "Reserved payout liability released after failed transfer on Payout #{$lockedPayout->id}",
                ]);
                FinancialJournalEntry::create([
                    'order_id' => null, 'transaction_reference' => $revRef,
                    'event_id' => 'EVT-REV-VND-' . $lockedPayout->id,
                    'beneficiary_type' => VendorProfile::class,
                    'beneficiary_id' => $vendor ? $vendor->id : $lockedPayout->vendor_id,
                    'account_name' => 'vendor_payable', 'entry_type' => 'credit',
                    'amount' => $formattedPayoutAmount, 'currency' => 'NGN',
                    'description' => "Vendor payable restored after failed payout request #{$lockedPayout->id}",
                ]);
            }


            return ['success' => true, 'idempotent' => false, 'payout' => $lockedPayout];
        });

        // Audit Finding P1: Dispatch notification OUTSIDE database transaction
        if (!empty($result['success']) && empty($result['idempotent'])) {
            try {
                NotificationDispatchService::dispatchPayoutRejected($result['payout'], $reason);
            } catch (\Throwable $e) {
                Log::warning('Payout rejection notification dispatch error: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Authoritative safe reversal of a failed/reversed Logistics Payout.
     */
    public function revertLogisticsPayout(LogisticsPayoutRequest $payout, string $reason, ?User $admin = null, bool $allowSuccessfulReversal = false, ?string $expectedProcessingToken = null): array
    {
        $result = DB::transaction(function () use ($payout, $reason, $admin, $allowSuccessfulReversal, $expectedProcessingToken) {
            $lockedPayout = LogisticsPayoutRequest::with('company')
                ->where('id', $payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedProcessingToken !== null && $lockedPayout->processing_token !== $expectedProcessingToken) {
                return ['success' => false, 'protected' => true, 'idempotent' => true, 'payout' => $lockedPayout,
                    'message' => 'Logistics payout reconciliation lease was lost before reversal.'];
            }

            // Terminal Guard (P0): Idempotent if already rejected/failed (prevents duplicate wallet balance credits)
            if ($lockedPayout->status === 'rejected' && $lockedPayout->gateway_status === 'failed') {
                return [
                    'success' => true,
                    'idempotent' => true,
                    'payout' => $lockedPayout,
                    'message' => 'Logistics payout already marked failed/reverted.',
                ];
            }

            // A stale failure/reconciliation result must not undo a confirmed payout.
            // Only an explicit transfer.reversed event may reverse a successful transfer.
            if (
                !$allowSuccessfulReversal
                && strtolower((string) $lockedPayout->gateway_status) === 'success'
                && strtolower((string) $lockedPayout->status) === 'approved'
            ) {
                return [
                    'success' => false,
                    'protected' => true,
                    'idempotent' => true,
                    'payout' => $lockedPayout,
                    'message' => 'Confirmed successful logistics payout protected from stale failure signal.',
                ];
            }

            $company = $lockedPayout->company;
            if ($company) {
                $company->wallet_balance = bcadd((string)$company->wallet_balance, (string)$payout->amount, 2);
                $company->save();
            }

            $lockedPayout->status = 'rejected';
            $lockedPayout->gateway_status = 'failed';
            $lockedPayout->rejection_reason = $reason;
            $lockedPayout->processing_token = null;
            $lockedPayout->processing_started_at = null;
            $lockedPayout->admin_notes = ($lockedPayout->admin_notes ? $lockedPayout->admin_notes . ' | ' : '') . 'Transfer Reversal: ' . $reason;
            $lockedPayout->save();

            $formattedPayoutAmount = bcadd((string)$lockedPayout->amount, '0', 2);
            $hasOriginalPayoutJournal = FinancialJournalEntry::where('event_id', 'EVT-FLEET-PAYOUT-' . $lockedPayout->id)->exists();
            $revRef = 'REV-FLEET-' . $lockedPayout->id . '-' . date('YmdHis');
            $beneficiaryId = $company ? $company->id : $lockedPayout->logistics_company_id;

            if ($hasOriginalPayoutJournal) {
                // Reverse the confirmed disbursement event only when it actually exists.
                FinancialJournalEntry::create([
                    'order_id' => null,
                    'transaction_reference' => $revRef,
                    'event_id' => 'EVT-REV-FLEET-' . $lockedPayout->id,
                    'beneficiary_type' => LogisticsCompanyProfile::class,
                    'beneficiary_id' => $beneficiaryId,
                    'account_name' => 'logistics_payout_payable',
                    'entry_type' => 'credit',
                    'amount' => $formattedPayoutAmount,
                    'currency' => 'NGN',
                    'description' => "Corporate payout liability restored after Paystack transfer reversal: " . ($company?->company_name ?? 'Logistics'),
                ]);

                FinancialJournalEntry::create([
                    'order_id' => null,
                    'transaction_reference' => $revRef,
                    'event_id' => 'EVT-REV-FLEET-' . $lockedPayout->id,
                    'beneficiary_type' => LogisticsCompanyProfile::class,
                    'beneficiary_id' => $beneficiaryId,
                    'account_name' => 'cash_in_escrow',
                    'entry_type' => 'debit',
                    'amount' => $formattedPayoutAmount,
                    'currency' => 'NGN',
                    'description' => "Escrow cash restored after Paystack transfer reversal: " . ($company?->company_name ?? 'Logistics'),
                ]);

                // The original request journal debited the company's wallet and
                // credited payout payable. Restore that request-side liability too,
                // so the wallet subledger and payable balance are not left stranded.
                FinancialJournalEntry::create([
                    'order_id' => null,
                    'transaction_reference' => $revRef,
                    'event_id' => 'EVT-REV-FLEET-REQUEST-' . $lockedPayout->id,
                    'beneficiary_type' => LogisticsCompanyProfile::class,
                    'beneficiary_id' => $beneficiaryId,
                    'account_name' => 'logistics_payout_payable',
                    'entry_type' => 'debit',
                    'amount' => $formattedPayoutAmount,
                    'currency' => 'NGN',
                    'description' => "Unwound corporate withdrawal request after transfer reversal: " . ($company?->company_name ?? 'Logistics'),
                ]);

                FinancialJournalEntry::create([
                    'order_id' => null,
                    'transaction_reference' => $revRef,
                    'event_id' => 'EVT-REV-FLEET-REQUEST-' . $lockedPayout->id,
                    'beneficiary_type' => LogisticsCompanyProfile::class,
                    'beneficiary_id' => $beneficiaryId,
                    'account_name' => 'logistics_company_wallet',
                    'entry_type' => 'credit',
                    'amount' => $formattedPayoutAmount,
                    'currency' => 'NGN',
                    'description' => "Corporate wallet ledger restored after transfer reversal: " . ($company?->company_name ?? 'Logistics'),
                ]);
            } else {
                // Transfer failed before finalization: reverse the withdrawal request journal,
                // not a disbursement that never posted.
                FinancialJournalEntry::create([
                    'order_id' => null,
                    'transaction_reference' => $revRef,
                    'event_id' => 'EVT-REV-FLEET-REQUEST-' . $lockedPayout->id,
                    'beneficiary_type' => LogisticsCompanyProfile::class,
                    'beneficiary_id' => $beneficiaryId,
                    'account_name' => 'logistics_payout_payable',
                    'entry_type' => 'debit',
                    'amount' => $formattedPayoutAmount,
                    'currency' => 'NGN',
                    'description' => "Unpaid corporate withdrawal liability released after failed transfer: " . ($company?->company_name ?? 'Logistics'),
                ]);

                FinancialJournalEntry::create([
                    'order_id' => null,
                    'transaction_reference' => $revRef,
                    'event_id' => 'EVT-REV-FLEET-REQUEST-' . $lockedPayout->id,
                    'beneficiary_type' => LogisticsCompanyProfile::class,
                    'beneficiary_id' => $beneficiaryId,
                    'account_name' => 'logistics_company_wallet',
                    'entry_type' => 'credit',
                    'amount' => $formattedPayoutAmount,
                    'currency' => 'NGN',
                    'description' => "Corporate wallet restored after failed, unfinalized Paystack transfer: " . ($company?->company_name ?? 'Logistics'),
                ]);
            }

            return ['success' => true, 'idempotent' => false, 'payout' => $lockedPayout];
        });

        // Audit Finding P1: Dispatch notification OUTSIDE database transaction
        if (!empty($result['success']) && empty($result['idempotent'])) {
            try {
                NotificationDispatchService::dispatchPayoutRejected($result['payout'], $reason);
            } catch (\Throwable $e) {
                Log::warning('Logistics payout rejection notification dispatch error: ' . $e->getMessage());
            }
        }

        return $result;
    }
}