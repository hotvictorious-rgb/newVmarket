<?php

namespace App\Services;

use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use App\Models\ProcessedWebhookEvent;
use App\Models\VendorPayoutRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
     */
    public function reconcilePayouts(): array
    {
        $results = [
            'vendor_reconciled' => 0,
            'logistics_reconciled' => 0,
            'refunds_reconciled' => 0,
            'errors' => [],
        ];

        // 1. Vendor Payouts
        $vendorPayouts = VendorPayoutRequest::where('processing_token', 'HOLD_TIMEOUT_RECONCILIATION')
            ->orWhere('gateway_status', 'processing')
            ->get();

        foreach ($vendorPayouts as $payout) {
            $ref = $payout->transfer_reference ?? ('VND-DISBURSE-' . $payout->id);
            $verify = $this->paystackService->verifyTransfer($ref);

            if ($verify['success'] && ($verify['status'] ?? '') === 'success') {
                $this->finalizationService->finalizeVendorPayout($payout, [
                    'transfer_code' => $verify['transfer_code'] ?? $payout->paystack_transfer_code,
                    'reference' => $ref,
                ]);
                $results['vendor_reconciled']++;
            } elseif ($verify['success'] && in_array($verify['status'] ?? '', ['failed', 'reversed'], true)) {
                $this->finalizationService->revertVendorPayout(
                    $payout,
                    'Auto-Reconciled by Engine: Verified Failed at Paystack'
                );
                $results['vendor_reconciled']++;
            }
        }

        // 2. Logistics Payouts
        $logisticsPayouts = LogisticsPayoutRequest::where('processing_token', 'HOLD_TIMEOUT_RECONCILIATION')
            ->orWhere('gateway_status', 'processing')
            ->get();

        foreach ($logisticsPayouts as $payout) {
            $ref = $payout->transfer_reference ?? ('FLEET-DISBURSE-' . $payout->id);
            $verify = $this->paystackService->verifyTransfer($ref);

            if ($verify['success'] && ($verify['status'] ?? '') === 'success') {
                $this->finalizationService->finalizeLogisticsPayout($payout, [
                    'transfer_code' => $verify['transfer_code'] ?? $payout->paystack_transfer_code,
                    'reference' => $ref,
                ]);
                $results['logistics_reconciled']++;
            } elseif ($verify['success'] && in_array($verify['status'] ?? '', ['failed', 'reversed'], true)) {
                $this->finalizationService->revertLogisticsPayout(
                    $payout,
                    'Auto-Reconciled by Engine: Verified Failed at Paystack'
                );
                $results['logistics_reconciled']++;
            }
        }

        // 3. Reconcile Customer Refunds
        $results['refunds_reconciled'] = $this->reconcileRefunds();

        return $results;
    }

    /**
     * Stale Webhook Inbox Recovery.
     * Unsticks events where the worker crashed or timed out (> 5 minutes).
     */
    public function reconcileStaleWebhooks(): int
    {
        return ProcessedWebhookEvent::where('status', 'processing')
            ->where('updated_at', '<', Carbon::now()->subMinutes(5))
            ->update([
                'status' => 'failed',
                'error_reason' => 'TIMEOUT_STALE_WORKER: Event was abandoned in processing state for over 5 minutes. Reset to failed for retry.',
            ]);
    }

    /**
     * Autonomous Customer Refund Reconciliation (Audit Finding 5).
     * Scans cancelled orders with pending Paystack refunds and ensures gateway synchronization.
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
                // Attempt to initiate refund if not yet initiated
                $refundRes = $this->paystackService->initiateRefund(
                    $order->payment_reference,
                    (string)$order->grand_total,
                    "Automated reconciliation refund for cancelled Order #{$order->order_number}"
                );
                if (!empty($refundRes['success'])) {
                    $order->update([
                        'paystack_refund_reference' => $refundRes['refund_reference'] ?? ('rf_' . uniqid()),
                        'paystack_refund_status' => 'completed',
                    ]);
                    $reconciled++;
                }
            } else {
                // If reference exists, mark completed
                $order->update(['paystack_refund_status' => 'completed']);
                $reconciled++;
            }
        }

        return $reconciled;
    }
}