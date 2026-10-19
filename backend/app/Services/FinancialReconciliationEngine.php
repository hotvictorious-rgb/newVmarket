<?php

namespace App\Services;

use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use App\Models\ProcessedWebhookEvent;
use App\Models\VendorPayoutRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FinancialReconciliationEngine
{
    protected PaystackService $paystackService;
    protected OrderSettlementService $settlementService;
    protected PayoutFinalizationService $finalizationService;

    public function __construct(
        PaystackService $paystackService,
        OrderSettlementService $settlementService,
        PayoutFinalizationService $finalizationService
    ) {
        $this->paystackService = $paystackService;
        $this->settlementService = $settlementService;
        $this->finalizationService = $finalizationService;
    }

    /**
     * Authoritative Gateway Payout Reconciliation.
     * Scans all payouts in HOLD_TIMEOUT_RECONCILIATION or processing gateway_status.
     * Delegates 100% of settlement, order allocations, and double-entry bookkeeping
     * to the idempotent PayoutFinalizationService.
     * Enforces atomic claim tokens to prevent overlapping scans (Audit Finding 49).
     */
    public function reconcilePayouts(): array
    {
        $results = [
            'vendor_reconciled' => 0,
            'logistics_reconciled' => 0,
            'refunds_reconciled' => 0,
            'errors' => [],
        ];

        // 1. Vendor Payouts with Atomic Scan Claim
        $vendorPayouts = VendorPayoutRequest::where(function ($q) {
                $q->where('processing_token', 'HOLD_TIMEOUT_RECONCILIATION')
                  ->orWhere('gateway_status', 'processing');
            })
            ->where('status', '!=', 'rejected')
            ->get();

        foreach ($vendorPayouts as $payout) {
            // Atomic lease claim to prevent overlapping concurrent cron scans
            $claimToken = 'RECON-VND-' . $payout->id . '-' . Str::random(12);
            $claimed = VendorPayoutRequest::where('id', $payout->id)
                // Recheck state atomically: a record can become terminal after the scan query.
                ->where('status', '!=', 'rejected')
                ->where(function ($q) {
                    $q->whereNull('processing_token')
                      ->orWhere('processing_token', 'HOLD_TIMEOUT_RECONCILIATION')
                      ->orWhere('processing_started_at', '<', Carbon::now()->subMinutes(5));
                })
                ->update([
                    'processing_token' => $claimToken,
                    'processing_started_at' => Carbon::now(),
                ]);

            if ($claimed === 0) {
                // Another concurrent worker/process already claimed this payout
                continue;
            }

            $ref = $payout->transfer_reference ?? ('VND-DISBURSE-' . $payout->id);
            $verify = $this->paystackService->verifyTransfer($ref);

            // Fencing Guard (Finding P1): Verify this worker still holds the valid lease
            $stillOwned = VendorPayoutRequest::where('id', $payout->id)->where('processing_token', $claimToken)->exists();
            if (!$stillOwned) {
                Log::warning("Fencing guard: Stale worker for payout #{$payout->id} skipped (claim lease expired/overwritten).");
                continue;
            }

            if ($verify['success'] && ($verify['status'] ?? '') === 'success') {
                $finalized = $this->finalizationService->finalizeVendorPayout($payout, [
                    'transfer_code' => $verify['transfer_code'] ?? $payout->paystack_transfer_code,
                    'reference' => $ref,
                    'status' => 'success',
                ], null, $claimToken);
                if (!empty($finalized['protected'])) {
                    Log::warning("Fencing guard: Vendor payout #{$payout->id} lost its lease before finalization.");
                    continue;
                }
                $results['vendor_reconciled']++;
            } elseif ($verify['success'] && in_array($verify['status'] ?? '', ['failed', 'reversed'], true)) {
                $reverted = $this->finalizationService->revertVendorPayout(
                    $payout,
                    'Auto-Reconciled by Engine: Verified Failed at Paystack',
                    null,
                    false,
                    $claimToken
                );
                if (!empty($reverted['protected'])) {
                    Log::warning("Fencing guard: Vendor payout #{$payout->id} lost its lease before reversal.");
                    continue;
                }
                $results['vendor_reconciled']++;
            } else {
                // Transfer outcome still pending at bank or unreachable: maintain timeout hold
                VendorPayoutRequest::where('id', $payout->id)
                    ->where('processing_token', $claimToken)
                    ->update([
                        'processing_token' => 'HOLD_TIMEOUT_RECONCILIATION',
                        'processing_started_at' => Carbon::now(),
                    ]);
            }
        }

        // 2. Logistics Payouts with Atomic Scan Claim
        $logisticsPayouts = LogisticsPayoutRequest::where(function ($q) {
                $q->where('processing_token', 'HOLD_TIMEOUT_RECONCILIATION')
                  ->orWhere('gateway_status', 'processing');
            })
            ->where('status', '!=', 'rejected')
            ->get();

        foreach ($logisticsPayouts as $payout) {
            $claimToken = 'RECON-FLEET-' . $payout->id . '-' . Str::random(12);
            $claimed = LogisticsPayoutRequest::where('id', $payout->id)
                // Recheck state atomically: a record can become terminal after the scan query.
                ->where('status', '!=', 'rejected')
                ->where(function ($q) {
                    $q->whereNull('processing_token')
                      ->orWhere('processing_token', 'HOLD_TIMEOUT_RECONCILIATION')
                      ->orWhere('processing_started_at', '<', Carbon::now()->subMinutes(5));
                })
                ->update([
                    'processing_token' => $claimToken,
                    'processing_started_at' => Carbon::now(),
                ]);

            if ($claimed === 0) {
                continue;
            }

            $ref = $payout->transfer_reference ?? ('FLEET-DISBURSE-' . $payout->id);
            $verify = $this->paystackService->verifyTransfer($ref);

            // Fencing Guard (Finding P1): Verify this worker still holds the valid lease
            $stillOwned = LogisticsPayoutRequest::where('id', $payout->id)->where('processing_token', $claimToken)->exists();
            if (!$stillOwned) {
                Log::warning("Fencing guard: Stale worker for logistics payout #{$payout->id} skipped (claim lease expired/overwritten).");
                continue;
            }

            if ($verify['success'] && ($verify['status'] ?? '') === 'success') {
                $finalized = $this->finalizationService->finalizeLogisticsPayout($payout, [
                    'transfer_code' => $verify['transfer_code'] ?? $payout->paystack_transfer_code,
                    'reference' => $ref,
                    'status' => 'success',
                ], null, $claimToken);
                if (!empty($finalized['protected'])) {
                    Log::warning("Fencing guard: Logistics payout #{$payout->id} lost its lease before finalization.");
                    continue;
                }
                $results['logistics_reconciled']++;
            } elseif ($verify['success'] && in_array($verify['status'] ?? '', ['failed', 'reversed'], true)) {
                $reverted = $this->finalizationService->revertLogisticsPayout(
                    $payout,
                    'Auto-Reconciled by Engine: Verified Failed at Paystack',
                    null,
                    false,
                    $claimToken
                );
                if (!empty($reverted['protected'])) {
                    Log::warning("Fencing guard: Logistics payout #{$payout->id} lost its lease before reversal.");
                    continue;
                }
                $results['logistics_reconciled']++;
            } else {
                LogisticsPayoutRequest::where('id', $payout->id)
                    ->where('processing_token', $claimToken)
                    ->update([
                        'processing_token' => 'HOLD_TIMEOUT_RECONCILIATION',
                        'processing_started_at' => Carbon::now(),
                    ]);
            }
        }

        // 3. Reconcile Customer Refunds
        $results['refunds_reconciled'] = $this->reconcileRefunds();

        return $results;
    }

    /**
     * Stale Webhook Inbox Recovery (Audit Finding 46 & P1).
     * Unsticks events where the worker crashed or timed out (> 5 minutes).
     */
    public function reconcileStaleWebhooks(): int
    {
        $staleCutoff = Carbon::now()->subMinutes(5);
        $staleEvents = ProcessedWebhookEvent::where('status', 'processing')
            ->where('updated_at', '<', $staleCutoff)
            ->get();

        $replayed = 0;
        foreach ($staleEvents as $event) {
            // Compare-and-set the exact stale generation. A webhook delivery may
            // reclaim this event after the scan, so never overwrite a newer claim.
            $claim = ProcessedWebhookEvent::where('id', $event->id)
                ->where('status', 'processing')
                ->where('attempts', $event->attempts)
                ->where('updated_at', '<', $staleCutoff);

            if ($event->attempts >= 3) {
                $updated = $claim->update([
                    'status' => 'failed',
                    'error_reason' => 'MAX_ATTEMPTS_EXCEEDED: Event failed after 3 worker attempts. Flagged for manual audit.',
                ]);
                if ($updated) {
                    continue;
                }
                continue;
            }

            $updated = $claim->update([
                'status' => 'failed',
                'attempts' => $event->attempts + 1,
                'error_reason' => 'TIMEOUT_STALE_WORKER: Reset for retry.',
            ]);
            if ($updated) {
                $replayed++;
            }
        }

        return $replayed;
    }

    /**
     * Autonomous Customer Refund Reconciliation (Audit Finding 2 & P0).
     * Scans cancelled orders with pending Paystack refunds and ensures gateway synchronization.
     * Keeps refunds pending until actual Paystack status is verified. Never manufactures references.
     */
    public function reconcileRefunds(): int
    {
        $reconciled = 0;
        // Include orders with outstanding refund operations even if a previous
        // partial refund set the order-level status to completed. Each operation
        // retains its own gateway reference and reservation.
        $pendingOperationOrderIds = \\App\\Models\\FinancialOperation::where('operation_type', 'customer_refund')
            ->whereNotIn('status', ['confirmed', 'failed', 'reversed'])
            ->get()
            ->map(fn ($operation) => $operation->metadata['order_id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $pendingRefundOrders = Order::where(function ($q) use ($pendingOperationOrderIds) {
            $q->whereIn('paystack_refund_status', ['pending', 'authorized', 'processing'])
              ->orWhereHas('returnCases', function ($sub) {
                  $sub->whereIn('status', ['refund_pending', 'refund_approved']);
              })
              ->orWhereIn('id', $pendingOperationOrderIds);
        })->whereNotNull('payment_reference')->get();

        foreach ($pendingRefundOrders as $candidateOrder) {
            $claimToken = Str::random(48);
            $claimedOrder = \Illuminate\Support\Facades\DB::transaction(function () use ($candidateOrder, $claimToken) {
                $ord = Order::where('id', $candidateOrder->id)->lockForUpdate()->first();
                if (!$ord || $ord->paystack_refund_status === 'failed') {
                    return null;
                }
                if ($ord->paystack_refund_status === 'completed') {
                    $hasOutstandingOperation = \\App\\Models\\FinancialOperation::where('operation_type', 'customer_refund')
                        ->whereJsonContains('metadata->order_id', $ord->id)
                        ->whereNotIn('status', ['confirmed', 'failed', 'reversed'])
                        ->exists();
                    $hasPendingReturnCase = $ord->returnCases()
                        ->whereIn('status', ['refund_pending', 'refund_approved'])
                        ->exists();
                    if (!$hasOutstandingOperation && !$hasPendingReturnCase) {
                        return null;
                    }
                }

                $cutoff = Carbon::now()->subMinutes(5);
                if ($ord->refund_reconciliation_token && $ord->refund_reconciliation_claimed_at
                    && $ord->refund_reconciliation_claimed_at->gt($cutoff)) {
                    return null;
                }
                // Compatibility guard for rows left in processing by the older timestamp-only worker.
                if (!$ord->refund_reconciliation_token && $ord->paystack_refund_status === 'processing'
                    && $ord->updated_at && $ord->updated_at->gt($cutoff)) {
                    return null;
                }

                $ord->paystack_refund_status = 'processing';
                $ord->refund_reconciliation_token = $claimToken;
                $ord->refund_reconciliation_claimed_at = Carbon::now();
                $ord->save();

                return $ord;
            });

            if (!$claimedOrder) {
                continue;
            }

            // Only the current lease owner may release/reset the reconciliation state.
            $releaseClaim = function (?string $status = null) use ($claimedOrder, $claimToken): bool {
                $updates = [
                    'refund_reconciliation_token' => null,
                    'refund_reconciliation_claimed_at' => null,
                ];
                if ($status !== null) {
                    $updates['paystack_refund_status'] = $status;
                }
                return Order::where('id', $claimedOrder->id)
                    ->where('refund_reconciliation_token', $claimToken)
                    ->update($updates) > 0;
            };

            $finOpService = app(\App\Services\FinancialOperationService::class);

            if (empty($claimedOrder->paystack_refund_reference)) {
                $returnCase = $claimedOrder->returnCases()
                    ->whereIn('status', ['refund_pending', 'refund_approved'])
                    ->first();
                $refundAmount = $returnCase ? $returnCase->total_refundable_amount : (string)$claimedOrder->grand_total;

                try {
                    $op = $finOpService->createRefundOperation(
                        $claimedOrder,
                        $refundAmount,
                        "Automated reconciliation refund for authorized Order #{$claimedOrder->order_number}",
                        $returnCase
                    );
                    $dispatchRes = $finOpService->dispatchRefund($op);

                    if (!empty($dispatchRes['success']) && ($dispatchRes['status'] ?? '') === 'confirmed') {
                        $reconciled++;
                    }
                    $releaseClaim();
                } catch (\Throwable $e) {
                    Log::error("Reconciler refund initiation failed for order #{$claimedOrder->id}: " . $e->getMessage());
                    $releaseClaim('pending');
                }
            } else {
                $orderRefundReference = (string) $claimedOrder->paystack_refund_reference;
                $outstandingOperations = \\App\\Models\\FinancialOperation::where('operation_type', 'customer_refund')
                    ->whereJsonContains('metadata->order_id', $claimedOrder->id)
                    ->whereNotIn('status', ['confirmed', 'failed', 'reversed'])
                    ->with('gatewayAttempts')
                    ->orderBy('id')
                    ->get();

                // Prefer the order's current reference only when it belongs to an
                // outstanding operation. Otherwise recover a pending operation's
                // own reference from its attempt; never apply one refund's evidence
                // to a different operation.
                $existingOp = null;
                $refundReference = null;
                foreach ($outstandingOperations as $candidateOperation) {
                    $candidateReferences = $candidateOperation->gatewayAttempts
                        ->pluck('gateway_reference')
                        ->filter()
                        ->map(fn ($value) => (string) $value)
                        ->all();
                    $metadataReference = $candidateOperation->metadata['refund_reference'] ?? null;
                    if ($metadataReference) {
                        $candidateReferences[] = (string) $metadataReference;
                    }
                    if (in_array($orderRefundReference, $candidateReferences, true)) {
                        $existingOp = $candidateOperation;
                        $refundReference = $orderRefundReference;
                        break;
                    }
                }
                if (!$existingOp) {
                    foreach ($outstandingOperations as $candidateOperation) {
                        $attemptReference = $candidateOperation->gatewayAttempts
                            ->pluck('gateway_reference')->filter()->last();
                        $metadataReference = $candidateOperation->metadata['refund_reference'] ?? null;
                        $candidateReference = $attemptReference ?: $metadataReference;
                        if ($candidateReference) {
                            $existingOp = $candidateOperation;
                            $refundReference = (string) $candidateReference;
                            break;
                        }
                    }
                }

                // Keep compatibility with a single legacy operation that predates
                // persisted gateway references. Multiple operations are ambiguous
                // without an operation-specific gateway reference.
                if (!$existingOp && $outstandingOperations->count() === 1) {
                    $existingOp = $outstandingOperations->first();
                    $refundReference = $orderRefundReference;
                }

                if (!$existingOp || !$refundReference) {
                    $releaseClaim('pending');
                    Log::warning('Paystack refund has no unambiguous matching FinancialOperation; left pending for manual reconciliation.', [
                        'order_id' => $claimedOrder->id,
                        'refund_reference' => $orderRefundReference,
                        'outstanding_operations' => $outstandingOperations->count(),
                    ]);
                    continue;
                }

                $verifyRes = $this->paystackService->verifyRefund($refundReference);
                if (!empty($verifyRes['success'])) {
                    $gwStatus = strtolower($verifyRes['status'] ?? 'pending');
                    if (in_array($gwStatus, ['processed', 'success', 'completed'], true)) {
                        $evidenceReference = $verifyRes['refund_reference']
                            ?? ($verifyRes['data']['reference'] ?? null);
                        if ($evidenceReference !== null && (string) $evidenceReference !== $refundReference) {
                            $releaseClaim('pending');
                            Log::warning('Paystack refund verification returned a conflicting reference; left pending for manual reconciliation.', [
                                'order_id' => $claimedOrder->id,
                                'requested_reference' => $refundReference,
                                'verified_reference' => (string) $evidenceReference,
                            ]);
                            continue;
                        }

                        $finalized = $finOpService->finalizeRefundConfirmation($existingOp, $verifyRes, $claimToken);
                        if (!empty($finalized['protected'])) {
                            Log::warning('Refund reconciliation lease lost before finalization; stale worker left refund unchanged.', [
                                'order_id' => $claimedOrder->id,
                                'operation_id' => $existingOp->id,
                            ]);
                            continue;
                        }
                        $reconciled++;
                    } elseif (in_array($gwStatus, ['failed', 'reversed'], true)) {
                        // Do not mark the entire order failed for a failed partial refund.
                        if ($releaseClaim('pending')) {
                            Log::error("Paystack refund #{$refundReference} for order #{$claimedOrder->id} failed at gateway; operation requires review.");
                        }
                    } else {
                        $releaseClaim('pending');
                    }
                } else {
                    $releaseClaim('pending');
                }
            } }
        }

        return $reconciled;
    }
}