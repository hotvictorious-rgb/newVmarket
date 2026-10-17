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

    public function __construct(PaystackService $paystackService, OrderSettlementService $settlementService)
    {
        $this->paystackService = $paystackService;
        $this->settlementService = $settlementService;
    }

    /**
     * Authoritative Gateway Payout Reconciliation.
     * Scans all payouts in HOLD_TIMEOUT_RECONCILIATION or processing gateway_status.
     */
    public function reconcilePayouts(): array
    {
        $results = ['vendor_reconciled' => 0, 'logistics_reconciled' => 0, 'errors' => []];

        // 1. Vendor Payouts
        $vendorPayouts = VendorPayoutRequest::where('processing_token', 'HOLD_TIMEOUT_RECONCILIATION')
            ->orWhere('gateway_status', 'processing')
            ->get();

        foreach ($vendorPayouts as $payout) {
            $ref = $payout->transfer_reference ?? ('VND-DISBURSE-' . $payout->id);
            $verify = $this->paystackService->verifyTransfer($ref);

            if ($verify['success'] && ($verify['status'] ?? '') === 'success') {
                $payout->status = 'approved';
                $payout->processing_token = null;
                $payout->processing_started_at = null;
                $payout->disbursed_at = $payout->disbursed_at ?? Carbon::now();
                $payout->gateway_status = 'success';
                $payout->admin_notes = ($payout->admin_notes ? $payout->admin_notes . ' | ' : '') . 'Auto-Reconciled by Engine: Verified Success at Paystack';
                $payout->save();
                $results['vendor_reconciled']++;
            } elseif ($verify['success'] && in_array($verify['status'] ?? '', ['failed', 'reversed'], true)) {
                $payout->processing_token = null;
                $payout->processing_started_at = null;
                $payout->gateway_status = 'failed';
                $payout->admin_notes = ($payout->admin_notes ? $payout->admin_notes . ' | ' : '') . 'Auto-Reconciled by Engine: Verified Failed at Paystack';
                $payout->save();
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
                $payout->status = 'approved';
                $payout->processing_token = null;
                $payout->processing_started_at = null;
                $payout->disbursed_at = $payout->disbursed_at ?? Carbon::now();
                $payout->gateway_status = 'success';
                $payout->admin_notes = ($payout->admin_notes ? $payout->admin_notes . ' | ' : '') . 'Auto-Reconciled by Engine: Verified Success at Paystack';
                $payout->save();
                $results['logistics_reconciled']++;
            } elseif ($verify['success'] && in_array($verify['status'] ?? '', ['failed', 'reversed'], true)) {
                $payout->processing_token = null;
                $payout->processing_started_at = null;
                $payout->gateway_status = 'failed';
                $payout->admin_notes = ($payout->admin_notes ? $payout->admin_notes . ' | ' : '') . 'Auto-Reconciled by Engine: Verified Failed at Paystack';
                $payout->save();
                $results['logistics_reconciled']++;
            }
        }

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
}