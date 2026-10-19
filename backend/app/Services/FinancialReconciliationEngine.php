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
        $vendorPayouts = VendorPayoutRequest::where('processing_token', 'HOLD_TIMEOUT_RECONCILIATION')
            ->orWhere('gateway_status', 'processing')
            ->get();

        foreach ($vendorPayouts as $payout) {
            // Atomic lease claim to prevent overlapping concurrent cron scans
            $claimToken = 'RECON-VND-' . $payout->id . '-' . Str::random(12);
            $claimed = VendorPayoutRequest::where('id', $payout->id)
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
                $this->finalizationService->finalizeVendorPayout($payout, [
                    'transfer_code' => $verify['transfer_code'] ?? $payout->paystack_transfer_code,
                    'reference' => $ref,
                    'status' => 'success',
                ]);
                $results['vendor_reconciled']++;
            } elseif ($verify['success'] && in_array($verify['status'] ?? '', ['failed', 'reversed'], true)) {
                $this->finalizationService->revertVendorPayout(
                    $payout,
                    'Auto-Reconciled by Engine: Verified Failed at Paystack'
                );
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
        $logisticsPayouts = LogisticsPayoutRequest::where('processing_token', 'HOLD_TIMEOUT_RECONCILIATION')
            ->orWhere('gateway_status', 'processing')
            ->get();

        foreach ($logisticsPayouts as $payout) {
            $claimToken = 'RECON-FLEET-' . $payout->id . '-' . Str::random(12);
            $claimed = LogisticsPayoutRequest::where('id', $payout->id)
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
                $this->finalizationService->finalizeLogisticsPayout($payout, [
                    'transfer_code' => $verify['transfer_code'] ?? $payout->paystack_transfer_code,
                    'reference' => $ref,
                    'status' => 'success',
                ]);
                $results['logistics_reconciled']++;
            } elseif ($verify['success'] && in_array($verify['status'] ?? '', ['failed', 'reversed'], true)) {
                $this->finalizationService->revertLogisticsPayout(
                    $payout,
                    'Auto-Reconciled by Engine: Verified Failed at Paystack'
                );
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
        $staleEvents = ProcessedWebhookEvent::where('status', 'processing')
            ->where('updated_at', '<', Carbon::now()->subMinutes(5))
            ->get();

        $replayed = 0;
        foreach ($staleEvents as $event) {
            if ($event->attempts >= 3) {
                $event->update([
                    'status' => 'failed',
                    'error_reason' => 'MAX_ATTEMPTS_EXCEEDED: Event failed after 3 worker attempts. Flagged for manual audit.',
                ]);
                continue;
            }

            $event->update([
                'status' => 'failed',
                'attempts' => $event->attempts + 1,
                'error_reason' => 'TIMEOUT_STALE_WORKER: Reset for retry.',
            ]);
            $replayed++;
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
        // Require explicit authorization: pending refund status or authorized return case instruction (Audit Findings P0 & P1)
        $pendingRefundOrders = Order::where(function ($q) {
            $q->whereIn('paystack_refund_status', ['pending', 'authorized'])
              ->orWhereHas('returnCases', function ($sub) {
                  $sub->whereIn('status', ['refund_pending', 'refund_approved']);
              });
        })->whereNotNull('payment_reference')->get();

        foreach ($pendingRefundOrders as $candidateOrder) {
            // Durable Claim: Atomically acquire processing lock under transaction to prevent double worker execution
            $claimedOrder = \Illuminate\Support\Facades\DB::transaction(function () use ($candidateOrder) {
                $ord = Order::where('id', $candidateOrder->id)->lockForUpdate()->first();
                if (!$ord) return null;

                if (in_array($ord->paystack_refund_status, ['completed', 'failed'], true)) {
                    return null;
                }

                if ($ord->paystack_refund_status === 'processing' && $ord->updated_at && $ord->updated_at->gt(Carbon::now()->subMinutes(5))) {
                    return null; // actively claimed by another worker
                }

                $ord->paystack_refund_status = 'processing';
                $ord->save();
                return $ord;
            });

            if (!$claimedOrder) {
                continue;
            }

            $finOpService = app(\App\Services\FinancialOperationService::class);

            if (empty($claimedOrder->paystack_refund_reference)) {
                // 1. Authoritative partial vs full refund amount determination (Audit Finding P0 resolution)
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

                    if (!empty($dispatchRes['success'])) {
                        if (($dispatchRes['status'] ?? '') === 'confirmed') {
                            $reconciled++;
                        }
                    } else {
                        $claimedOrder->update(['paystack_refund_status' => 'pending']);
                    }
                } catch (\Throwable $e) {
                    Log::error("Reconciler refund initiation failed for order #{$claimedOrder->id}: " . $e->getMessage());
                    $claimedOrder->update(['paystack_refund_status' => 'pending']);
                }
            } else {
                // 2. Authoritatively verify existing refund status with Paystack Gateway
                $verifyRes = $this->paystackService->verifyRefund($claimedOrder->paystack_refund_reference);

                if (!empty($verifyRes['success'])) {
                    $gwStatus = strtolower($verifyRes['status'] ?? 'pending');
                    if (in_array($gwStatus, ['processed', 'success', 'completed'], true)) {
                        // Check if a FinancialOperation exists for trace and double-entry ledger finalization
                        $existingOp = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
                            ->whereJsonContains('metadata->order_id', $claimedOrder->id)
                            ->where('status', '!=', 'confirmed')
                            ->first();

                        if ($existingOp) {
                            $finOpService->finalizeRefundConfirmation($existingOp, $verifyRes);
                        } else {
                            $claimedOrder->update([
                                'paystack_refund_status' => 'completed',
                            ]);
                            // Also transition any pending return case
                            $claimedOrder->returnCases()
                                ->whereIn('status', ['refund_pending', 'refund_approved'])
                                ->update([
                                    'status' => 'refunded',
                                    'refunded_at' => Carbon::now(),
                                ]);
                        }
                        $reconciled++;
                    } elseif (in_array($gwStatus, ['failed', 'reversed'], true)) {
                        $claimedOrder->update([
                            'paystack_refund_status' => 'failed',
                        ]);
                        Log::error("Paystack refund #{$claimedOrder->paystack_refund_reference} for order #{$claimedOrder->id} was marked failed by gateway.");
                    } else {
                        $claimedOrder->update([
                            'paystack_refund_status' => 'pending',
                        ]);
                    }
                } else {
                    $claimedOrder->update([
                        'paystack_refund_status' => 'pending',
                    ]);
                }
            }
        }

        return $reconciled;
    }
}