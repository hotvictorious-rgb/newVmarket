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
                VendorPayoutRequest::where('id', $payout->id)->update([
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
                LogisticsPayoutRequest::where('id', $payout->id)->update([
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
        $pendingRefundOrders = Order::where(function ($q) {
            $q->where('paystack_refund_status', 'pending')
              ->orWhere(function ($sub) {
                  $sub->where('order_status', 'cancelled')
                      ->where('payment_status', 'paid')
                      ->whereNull('paystack_refund_status');
              });
        })->whereNotNull('payment_reference')->get();

        foreach ($pendingRefundOrders as $order) {
            if (empty($order->paystack_refund_reference)) {
                // 1. Attempt to initiate refund via Paystack Refund API
                $refundRes = $this->paystackService->initiateRefund(
                    $order->payment_reference,
                    (string)$order->grand_total,
                    "Automated reconciliation refund for cancelled Order #{$order->order_number}"
                );

                if (!empty($refundRes['success']) && !empty($refundRes['refund_reference'])) {
                    $gwStatus = strtolower($refundRes['status'] ?? 'pending');
                    $isCompleted = in_array($gwStatus, ['processed', 'success', 'completed'], true);

                    $order->update([
                        'paystack_refund_reference' => $refundRes['refund_reference'],
                        'paystack_refund_status' => $isCompleted ? 'completed' : 'pending',
                    ]);

                    if ($isCompleted) {
                        $reconciled++;
                    }
                } else {
                    Log::error("Paystack refund initiation failed for order #{$order->id}: " . ($refundRes['message'] ?? 'Gateway rejected refund.'));
                }
            } else {
                // 2. Authoritatively verify refund status with Paystack Gateway (Never assume completed!)
                $verifyRes = $this->paystackService->verifyRefund($order->paystack_refund_reference);

                if (!empty($verifyRes['success'])) {
                    $gwStatus = strtolower($verifyRes['status'] ?? 'pending');
                    if (in_array($gwStatus, ['processed', 'success', 'completed'], true)) {
                        $order->update([
                            'paystack_refund_status' => 'completed',
                        ]);
                        $reconciled++;
                    } elseif (in_array($gwStatus, ['failed', 'reversed'], true)) {
                        $order->update([
                            'paystack_refund_status' => 'failed',
                        ]);
                        Log::error("Paystack refund #{$order->paystack_refund_reference} for order #{$order->id} was marked failed by gateway.");
                    }
                }
            }
        }

        return $reconciled;
    }
}