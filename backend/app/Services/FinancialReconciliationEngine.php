<?php

namespace App\Services;

use App\Models\FinancialJournalEntry;
use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnCase;
use App\Models\RetailerSupplierPayoutAllocation;
use App\Models\VendorProfile;
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
     * Reconcile retailer-model supplier liabilities by order and supplier. This is a
     * read-only safety audit: it compares current fulfillment supplier/cost snapshots
     * with net supplier_payable journal entries and never releases money automatically.
     * An optional order ID supports focused regression checks and manual investigations.
     */
    public function reconcileRetailerSupplierPayables(?int $onlyOrderId = null): array
    {
        $result = ['orders_checked' => 0, 'mismatches' => 0, 'errors' => []];
        $orders = Order::query()
            ->where('retailer_model_order', true)
            ->where('payment_status', 'paid')
            ->when($onlyOrderId !== null, fn ($query) => $query->whereKey($onlyOrderId))
            ->orderBy('id');

        $orders->chunkById(100, function ($orderBatch) use (&$result) {
            $orderIds = $orderBatch->pluck('id')->all();
            $itemsByOrder = OrderItem::whereIn('order_id', $orderIds)->orderBy('id')->get([
                'id', 'order_id', 'quantity', 'fulfillment_supplier_vendor_id', 'fulfillment_supplier_cost_snapshot',
            ])->groupBy('order_id');
            $entriesByOrder = FinancialJournalEntry::whereIn('order_id', $orderIds)
                ->whereIn('account_name', ['supplier_payable', 'supplier_payout_payable'])
                ->get(['order_id', 'order_item_id', 'event_id', 'account_name', 'beneficiary_type', 'beneficiary_id', 'entry_type', 'amount'])
                ->groupBy('order_id');

            $itemsById = $itemsByOrder->flatten(1)->keyBy('id');
            $payoutAllocationsByItem = RetailerSupplierPayoutAllocation::whereIn('order_item_id', $itemsById->keys()->all())
                ->whereIn('status', ['allocated', 'settled'])
                ->get(['order_item_id', 'supplier_vendor_id', 'amount', 'status'])
                ->groupBy('order_item_id');
            $refundedCasesByOrder = ReturnCase::whereIn('order_id', $orderIds)
                ->whereNotNull('refunded_at')
                ->with('items')
                ->get()
                ->groupBy('order_id');

            foreach ($orderBatch as $order) {
                $result['orders_checked']++;
                $expectedBySupplier = [];
                $expectedByItemSupplier = [];
                $ledgerByItemSupplier = [];
                $orderErrors = [];
                $items = $itemsByOrder->get($order->id, collect());
                if ($items->isEmpty()) {
                    $orderErrors[] = 'No order items exist for a paid retailer-model order.';
                }

                foreach ($items as $item) {
                    $supplierId = (int) ($item->fulfillment_supplier_vendor_id ?? 0);
                    $unitCost = $item->fulfillment_supplier_cost_snapshot;
                    if ($supplierId <= 0 || $unitCost === null || BcMathService::comp((string) $unitCost, '0.00') <= 0
                        || (int) $item->quantity <= 0) {
                        $orderErrors[] = "Order item #{$item->id} is missing a valid fulfillment supplier, cost snapshot, or quantity.";
                        continue;
                    }
                    $lineCost = BcMathService::mul((string) $unitCost, (string) $item->quantity);
                    $expectedBySupplier[$supplierId] = BcMathService::add($expectedBySupplier[$supplierId] ?? '0.00', $lineCost);
                    $expectedByItemSupplier[$item->id . ':' . $supplierId] = [
                        'item_id' => (int) $item->id,
                        'supplier_id' => $supplierId,
                        'amount' => $lineCost,
                    ];
                }

                $ledgerBySupplier = [];
                $supplierJournalEntries = $entriesByOrder->get($order->id, collect());
                foreach ($supplierJournalEntries as $entry) {
                    $supplierId = (int) ($entry->beneficiary_id ?? 0);
                    $journalItemId = (int) ($entry->order_item_id ?? 0);
                    $journalItem = $itemsById->get($journalItemId);
                    if ($supplierId <= 0 || $entry->beneficiary_type !== VendorProfile::class
                        || !in_array($entry->account_name, ['supplier_payable', 'supplier_payout_payable'], true)) {
                        $orderErrors[] = 'A supplier payable journal entry has an invalid supplier beneficiary or account.';
                        continue;
                    }
                    if (!$journalItem || (int) $journalItem->order_id !== (int) $order->id) {
                        $orderErrors[] = 'A supplier_payable journal entry is not linked to a valid order item on this order.';
                    }
                    $current = $ledgerBySupplier[$supplierId] ?? '0.00';
                    $ledgerBySupplier[$supplierId] = $entry->entry_type === 'credit'
                        ? BcMathService::add($current, (string) $entry->amount)
                        : BcMathService::sub($current, (string) $entry->amount);

                    $itemSupplierKey = $journalItemId . ':' . $supplierId;
                    $itemCurrent = $ledgerByItemSupplier[$itemSupplierKey] ?? '0.00';
                    $ledgerByItemSupplier[$itemSupplierKey] = $entry->entry_type === 'credit'
                        ? BcMathService::add($itemCurrent, (string) $entry->amount)
                        : BcMathService::sub($itemCurrent, (string) $entry->amount);
                }

                // A completed refund reverses supplier payable for returned items.
                // Require the exact matching debit before reducing expected liability;
                // a refund event alone must not hide a missing supplier-cost reversal.
                foreach ($refundedCasesByOrder->get($order->id, collect()) as $returnCase) {
                    foreach ($returnCase->items as $returnItem) {
                        $item = $itemsById->get($returnItem->order_item_id);
                        if (!$item || (int) $item->order_id !== (int) $order->id) {
                            $orderErrors[] = "Refunded return item #{$returnItem->id} does not resolve to an item on this order.";
                            continue;
                        }
                        $supplierId = (int) ($item->fulfillment_supplier_vendor_id ?? 0);
                        $unitCost = $item->fulfillment_supplier_cost_snapshot;
                        $returnedQuantity = (int) $returnItem->quantity;
                        if ($supplierId <= 0 || $unitCost === null || $returnedQuantity <= 0
                            || $returnedQuantity > (int) $item->quantity) {
                            $orderErrors[] = "Refunded return item #{$returnItem->id} has invalid supplier/cost/quantity evidence.";
                            continue;
                        }
                        if ($order->vendor_settlement_status === 'settled') {
                            $orderErrors[] = "Refunded order item #{$item->id} is marked settled; supplier recovery needs separate reconciliation.";
                            continue;
                        }

                        $settledAllocations = $payoutAllocationsByItem->get($item->id, collect())
                            ->where('status', 'settled');
                        if ($settledAllocations->isNotEmpty()) {
                            // A supplier already paid for this item. Refund accounting must
                            // use supplier recovery/loss mapping, not debit payable twice.
                            continue;
                        }

                        $activeAllocations = $payoutAllocationsByItem->get($item->id, collect())
                            ->where('status', 'allocated');
                        if ($activeAllocations->isNotEmpty()) {
                            $orderErrors[] = "Refunded return item #{$returnItem->id} conflicts with an active supplier payout reservation.";
                            continue;
                        }

                        $refundSupplierCost = BcMathService::mul((string) $unitCost, (string) $returnedQuantity);
                        $expectedRefundEvent = 'EVT-RETAILER-REFUND-' . $returnCase->id . '-SUPPLIER-PAYABLE-' . $returnItem->id;
                        $hasRefundDebit = $supplierJournalEntries->contains(function ($entry) use ($expectedRefundEvent, $supplierId, $refundSupplierCost) {
                            return $entry->event_id === $expectedRefundEvent
                                && $entry->account_name === 'supplier_payable'
                                && (int) $entry->order_item_id === (int) $item->id
                                && $entry->beneficiary_type === VendorProfile::class
                                && (int) $entry->beneficiary_id === $supplierId
                                && $entry->entry_type === 'debit'
                                && BcMathService::comp((string) $entry->amount, $refundSupplierCost) === 0;
                        });
                        if (!$hasRefundDebit) {
                            $orderErrors[] = "Refunded return item #{$returnItem->id} is missing its exact supplier_payable reversal journal.";
                            continue;
                        }
                        $expectedBySupplier[$supplierId] = BcMathService::sub(
                            $expectedBySupplier[$supplierId] ?? '0.00',
                            $refundSupplierCost
                        );
                        $itemSupplierKey = $item->id . ':' . $supplierId;
                        if (!isset($expectedByItemSupplier[$itemSupplierKey])) {
                            $orderErrors[] = "Refunded return item #{$returnItem->id} has no operational supplier-cost entry to reverse.";
                        } else {
                            $expectedByItemSupplier[$itemSupplierKey]['amount'] = BcMathService::sub(
                                $expectedByItemSupplier[$itemSupplierKey]['amount'],
                                $refundSupplierCost
                            );
                        }
                    }
                }

                // Settled supplier payout allocations are no longer outstanding liabilities.
                foreach ($payoutAllocationsByItem as $itemId => $allocationsForItem) {
                    $settled = $allocationsForItem->where('status', 'settled');
                    if ($settled->isEmpty()) {
                        continue;
                    }
                    foreach ($settled as $allocation) {
                        $supplierId = (int) $allocation->supplier_vendor_id;
                        $amount = (string) $allocation->amount;
                        $expectedBySupplier[$supplierId] = BcMathService::sub(
                            $expectedBySupplier[$supplierId] ?? '0.00',
                            $amount
                        );
                        $itemSupplierKey = $itemId . ':' . $supplierId;
                        if (isset($expectedByItemSupplier[$itemSupplierKey])) {
                            $expectedByItemSupplier[$itemSupplierKey]['amount'] = BcMathService::sub(
                                $expectedByItemSupplier[$itemSupplierKey]['amount'],
                                $amount
                            );
                        }
                    }
                }

                $itemSupplierKeys = array_unique(array_merge(array_keys($expectedByItemSupplier), array_keys($ledgerByItemSupplier)));
                foreach ($itemSupplierKeys as $itemSupplierKey) {
                    $expectedEntry = $expectedByItemSupplier[$itemSupplierKey] ?? null;
                    $expected = $expectedEntry['amount'] ?? '0.00';
                    $actual = $ledgerByItemSupplier[$itemSupplierKey] ?? '0.00';
                    if (BcMathService::comp($expected, $actual) !== 0) {
                        $itemId = $expectedEntry['item_id'] ?? (int) explode(':', $itemSupplierKey, 2)[0];
                        $supplierId = $expectedEntry['supplier_id'] ?? (int) explode(':', $itemSupplierKey, 2)[1];
                        $orderErrors[] = "Order item #{$itemId}, supplier #{$supplierId} payable mismatch: operational expected ₦{$expected}, ledger net ₦{$actual}.";
                    }
                }

                $supplierIds = array_unique(array_merge(array_keys($expectedBySupplier), array_keys($ledgerBySupplier)));
                foreach ($supplierIds as $supplierId) {
                    $expected = $expectedBySupplier[$supplierId] ?? '0.00';
                    $actual = $ledgerBySupplier[$supplierId] ?? '0.00';
                    if (BcMathService::comp($expected, $actual) !== 0) {
                        $orderErrors[] = "Supplier #{$supplierId} payable mismatch: operational expected ₦{$expected}, ledger net ₦{$actual}.";
                    }
                }

                if ($orderErrors) {
                    $result['mismatches']++;
                    $result['errors'][] = ['order_id' => $order->id, 'issues' => $orderErrors];
                    Log::critical('Retailer supplier payable reconciliation mismatch; no automatic payout or journal repair was attempted.', [
                        'order_id' => $order->id,
                        'issues' => $orderErrors,
                    ]);
                }
            }
        });

        return $result;
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
        $pendingOperationOrderIds = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
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
                    $hasOutstandingOperation = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
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
                $refundAuthorization = null;
                if ($returnCase && !empty($returnCase->reviewed_by_admin_id)
                    && in_array($returnCase->status, ['refund_pending', 'refund_approved'], true)) {
                    $refundAuthorization = [
                        'type' => 'admin_approved_return',
                        'approved_by_user_id' => (int) $returnCase->reviewed_by_admin_id,
                    ];
                } elseif ($claimedOrder->order_status === 'paid_stock_unavailable'
                    && $claimedOrder->vendor_settlement_status === 'refund_pending'
                    && $claimedOrder->payment_status === 'paid') {
                    $refundAuthorization = ['type' => 'auto_late_payment_cancelled_before_vendor_action'];
                }

                if ($refundAuthorization === null) {
                    Log::warning("Refund reconciliation skipped: no persisted approval or approved automatic exception for order #{$claimedOrder->id}.");
                    $releaseClaim('pending');
                    continue;
                }

                try {
                    $op = $finOpService->createRefundOperation(
                        $claimedOrder,
                        $refundAmount,
                        "Automated reconciliation refund for authorized Order #{$claimedOrder->order_number}",
                        $returnCase,
                        null,
                        $refundAuthorization
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
                $outstandingOperations = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
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

                if (!$existingOp) {
                    // Preserve gateway verification for orphaned refunds so the
                    // mismatch is observable, but never finalize an unrelated operation.
                    if ($orderRefundReference !== '') {
                        $this->paystackService->verifyRefund($orderRefundReference);
                    }
                    $releaseClaim('pending');
                    Log::warning('Paystack refund has no unambiguous matching FinancialOperation; left pending for manual reconciliation.', [
                        'order_id' => $claimedOrder->id,
                        'refund_reference' => $orderRefundReference,
                        'outstanding_operations' => $outstandingOperations->count(),
                    ]);
                    continue;
                }
                if (!$refundReference) {
                    $releaseClaim('pending');
                    Log::warning('Paystack refund operation has no persisted gateway reference; left pending for manual reconciliation.', [
                        'order_id' => $claimedOrder->id,
                        'operation_id' => $existingOp->id,
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

                        // A verified status/reference is not enough if the gateway also
                        // supplies conflicting financial details. Validate all amount/currency
                        // evidence before committing the reservation or journal.
                        $gatewayRefundData = is_array($verifyRes['data'] ?? null) ? $verifyRes['data'] : [];
                        $verifiedAmount = $verifyRes['amount'] ?? ($gatewayRefundData['amount'] ?? null);
                        $verifiedCurrency = $verifyRes['currency'] ?? ($gatewayRefundData['currency'] ?? null);
                        $amountMismatch = $verifiedAmount !== null
                            && (!is_numeric($verifiedAmount) || (int) $verifiedAmount !== (int) $existingOp->amount_kobo);
                        $currencyMismatch = $verifiedCurrency !== null
                            && strtoupper(trim((string) $verifiedCurrency)) !== strtoupper((string) $existingOp->currency);
                        if ($amountMismatch || $currencyMismatch) {
                            $releaseClaim('pending');
                            Log::critical('Paystack refund verification financial details conflict with the operation; left pending for manual reconciliation.', [
                                'order_id' => $claimedOrder->id,
                                'operation_id' => $existingOp->id,
                                'refund_reference' => $refundReference,
                                'expected_amount_kobo' => $existingOp->amount_kobo,
                                'verified_amount_kobo' => $verifiedAmount,
                                'expected_currency' => $existingOp->currency,
                                'verified_currency' => $verifiedCurrency,
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
            }
        }

        return $reconciled;
    }
}