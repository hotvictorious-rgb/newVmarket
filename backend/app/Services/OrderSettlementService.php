<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\CustomerCashbackLedger;
use App\Models\FinancialJournalEntry;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\PickupReservation;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderSettlementService
{
    protected PaystackService $paystackService;

    public function __construct(?PaystackService $paystackService = null)
    {
        $this->paystackService = $paystackService ?? app(PaystackService::class);
    }
    /**
     * Authoritative Paystack Order Settlement Core.
     * Enforces pessimistic locking, actor IDOR check, exact kobo invariant,
     * currency check, idempotency against replay attacks, state transition,
     * 5% customer cashback maturation queue, and immutable audit log.
     */
    public function settlePaystackOrder(
        Order $order,
        array $paystackData,
        ?User $actorUser = null,
        string $channel = 'paystack_webhook',
        ?int $webhookEventId = null,
        ?int $webhookAttempt = null
    ): array {
        $result = DB::transaction(function () use ($order, $paystackData, $actorUser, $channel, $webhookEventId, $webhookAttempt) {
            // For webhook settlement, hold the inbox generation lock in the same
            // transaction as order/payment settlement and journal side effects.
            if ($webhookEventId !== null || $webhookAttempt !== null) {
                if ($webhookEventId === null || $webhookAttempt === null) {
                    throw new \InvalidArgumentException('Webhook fencing requires both event ID and attempt generation.');
                }

                $claimedEvent = \App\Models\ProcessedWebhookEvent::where('id', $webhookEventId)
                    ->lockForUpdate()
                    ->first();

                if (!$claimedEvent
                    || $claimedEvent->status !== 'processing'
                    || (int) $claimedEvent->attempts !== $webhookAttempt) {
                    return [
                        'success' => false,
                        'status' => 'STALE_WEBHOOK_CLAIM',
                        'message' => 'Webhook claim generation is no longer current; order settlement blocked.',
                    ];
                }
            }

            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder) {
                return ['success' => false, 'status' => 'NOT_FOUND', 'message' => 'Order record not found under lock.'];
            }

            // 1. Actor IDOR Authorization Check
            if ($actorUser) {
                if ($actorUser->status !== 'active') {
                    return ['success' => false, 'status' => 'UNAUTHORIZED', 'message' => 'Unauthorized: User account is inactive.'];
                }
                if ($lockedOrder->customer_id && !$actorUser->isSuperAdmin() && (int)$lockedOrder->customer_id !== (int)$actorUser->id) {
                    return ['success' => false, 'status' => 'UNAUTHORIZED', 'message' => 'Unauthorized: You are not authorized to settle this customer order.'];
                }
            }

            // 2. Paid-order replay guard. Validate gateway evidence BEFORE returning
            // ALREADY_PAID: a second successful capture must not be mistaken for a replay.
            if ($lockedOrder->payment_status === 'paid') {
                $paidStatus = strtolower((string) ($paystackData['status'] ?? ''));
                $paidCurrency = strtoupper((string) ($paystackData['currency'] ?? ''));
                $paidReference = (string) ($paystackData['reference'] ?? '');
                $paidAmountRaw = $paystackData['amount'] ?? null;
                $paidAmountIsIntegerKobo = (is_int($paidAmountRaw) || (is_string($paidAmountRaw) && preg_match('/^\\d+$/', $paidAmountRaw) === 1));
                $paidAmountKobo = $paidAmountIsIntegerKobo ? (int) $paidAmountRaw : -1;
                $expectedPaidKobo = (int) bcmul(bcadd((string) $lockedOrder->grand_total, '0', 2), '100', 0);

                if ($paidStatus !== 'success') {
                    return ['success' => false, 'status' => 'STATUS_NOT_SUCCESS', 'message' => "Gateway status '{$paidStatus}' is not successful."];
                }
                if ($paidCurrency !== 'NGN') {
                    return ['success' => false, 'status' => 'CURRENCY_MISMATCH', 'message' => "Currency mismatch: gateway sent '{$paidCurrency}', expected 'NGN'."];
                }
                if ($paidReference === '' || empty($lockedOrder->payment_reference) || !hash_equals((string) $lockedOrder->payment_reference, $paidReference)) {
                    return ['success' => false, 'status' => 'REFERENCE_MISMATCH', 'message' => 'Payment reference does not match the already-paid order.'];
                }
                if (!$paidAmountIsIntegerKobo || $paidAmountKobo !== $expectedPaidKobo) {
                    return ['success' => false, 'status' => 'AMOUNT_MISMATCH', 'message' => 'Incoming capture does not match the already-paid order amount in integer kobo.'];
                }

                $incomingTransactionId = trim((string) ($paystackData['id'] ?? ''));
                $storedTransactionId = trim((string) ($lockedOrder->paystack_transaction_id ?? ''));
                $storedIdentityIsKnown = $storedTransactionId !== ''
                    && $storedTransactionId !== (string) $lockedOrder->payment_reference;
                $isExactCaptureReplay = $incomingTransactionId !== ''
                    && $storedIdentityIsKnown
                    && hash_equals($storedTransactionId, $incomingTransactionId);

                if (!$isExactCaptureReplay) {
                    // The order is already paid, but the incoming success cannot be
                    // proven to be the same gateway capture. Record a durable,
                    // idempotent exception for admin reconciliation; never create a
                    // second supplier payable, cashback credit, or automatic refund.
                    $exceptionIdentity = $incomingTransactionId !== ''
                        ? 'transaction:' . $incomingTransactionId
                        : 'evidence:' . hash('sha256', json_encode([
                            $paidReference,
                            $paidAmountKobo,
                            $paidCurrency,
                            $paidStatus,
                            $paystackData['channel'] ?? null,
                        ], JSON_UNESCAPED_SLASHES) ?: '');
                    $exceptionKey = 'PAYSTACK-DUP-CAP-' . hash('sha256', $exceptionIdentity);

                    $captureException = \App\Models\PaymentCaptureException::firstOrCreate(
                        ['idempotency_key' => $exceptionKey],
                        [
                            'order_id' => $lockedOrder->id,
                            'gateway_provider' => 'paystack',
                            'gateway_transaction_id' => $incomingTransactionId !== '' ? $incomingTransactionId : null,
                            'payment_reference' => $paidReference,
                            'amount_kobo' => $paidAmountKobo,
                            'currency' => $paidCurrency,
                            'exception_type' => $storedIdentityIsKnown ? 'distinct_successful_capture' : 'payment_identity_unproven',
                            'status' => 'pending_admin_review',
                            'evidence' => [
                                'stored_transaction_id' => $storedTransactionId !== '' ? $storedTransactionId : null,
                                'incoming_transaction_id' => $incomingTransactionId !== '' ? $incomingTransactionId : null,
                                'payment_reference' => $paidReference,
                                'amount_kobo' => $paidAmountKobo,
                                'currency' => $paidCurrency,
                                'channel' => (string) ($paystackData['channel'] ?? ''),
                                'detected_via' => $channel,
                            ],
                            'detected_at' => Carbon::now(),
                        ]
                    );

                    if ($captureException->wasRecentlyCreated) {
                        \App\Models\AdminAuditLog::create([
                        'user_id' => null,
                        'action' => 'paystack_duplicate_capture_review_required',
                        'target_type' => Order::class,
                        'target_id' => $lockedOrder->id,
                        'old_values' => ['paystack_transaction_id' => $storedTransactionId],
                        'new_values' => [
                            'exception_key' => $exceptionKey,
                            'exception_type' => $storedIdentityIsKnown ? 'distinct_successful_capture' : 'payment_identity_unproven',
                            'incoming_transaction_id' => $incomingTransactionId !== '' ? $incomingTransactionId : null,
                            'payment_reference' => $paidReference,
                            'amount_kobo' => $paidAmountKobo,
                            'currency' => $paidCurrency,
                            'requires_admin_review' => true,
                            'automatic_refund_dispatched' => false,
                        ],
                        'ip_address' => request()->ip() ?? '127.0.0.1',
                        'user_agent' => 'Paystack settlement reconciliation',
                        ]);
                    }

                    return [
                        'success' => true,
                        'status' => 'DUPLICATE_CAPTURE_REQUIRES_REVIEW',
                        'is_replayed' => false,
                        'requires_admin_review' => true,
                        'message' => 'The order was already paid, but this capture could not be proven to be the original transaction. A durable admin-review exception was recorded; no additional financial effects were posted.',
                        'order' => $lockedOrder,
                    ];
                }

                return [
                    'success' => true,
                    'status' => 'ALREADY_PAID',
                    'is_replayed' => true,
                    'message' => 'The same gateway transaction was already verified and settled.',
                    'order' => $lockedOrder,
                ];
            }

            // 3. Status Invariant
            $txStatus = strtolower((string)($paystackData['status'] ?? ''));
            if ($txStatus !== 'success') {
                return [
                    'success' => false,
                    'status' => 'STATUS_NOT_SUCCESS',
                    'message' => "Gateway status '{$txStatus}' is not successful.",
                ];
            }

            // 4. Currency Invariant
            $currency = strtoupper((string)($paystackData['currency'] ?? ''));
            if ($currency !== 'NGN') {
                return [
                    'success' => false,
                    'status' => 'CURRENCY_MISMATCH',
                    'message' => "Currency mismatch: gateway sent '{$currency}', expected 'NGN'.",
                ];
            }

            // 5. Exact Mathematical Integer Kobo Equality Guard (BCMath Zero Epsilon)
            $grandTotalNormalized = bcadd((string)$lockedOrder->grand_total, '0', 2);
            $expectedKobo = (int) bcmul($grandTotalNormalized, '100', 0);
            $actualKobo = (int) ($paystackData['amount'] ?? 0);

            if ($actualKobo !== $expectedKobo) {
                return [
                    'success' => false,
                    'status' => 'AMOUNT_MISMATCH',
                    'message' => "Mathematical amount mismatch: expected {$expectedKobo} kobo, gateway paid {$actualKobo} kobo.",
                ];
            }

            // 6. Reference Invariant
            $reference = (string)($paystackData['reference'] ?? '');
            if (empty($reference) || empty($lockedOrder->payment_reference) || !hash_equals((string)$lockedOrder->payment_reference, $reference)) {
                return [
                    'success' => false,
                    'status' => 'REFERENCE_MISMATCH',
                    'message' => "Payment reference mismatch: order expected '{$lockedOrder->payment_reference}', received '{$reference}'.",
                ];
            }

            // 7. Customer Identity Invariant
            $payerEmail = strtolower(trim((string)($paystackData['customer']['email'] ?? '')));
            $orderCustomerEmail = strtolower(trim((string)($lockedOrder->customer->email ?? '')));
            if (!empty($payerEmail) && !empty($orderCustomerEmail) && $payerEmail !== $orderCustomerEmail) {
                return [
                    'success' => false,
                    'status' => 'CUSTOMER_MISMATCH',
                    'message' => "Customer identity mismatch: payer email '{$payerEmail}' does not match order owner '{$orderCustomerEmail}'.",
                ];
            }

            // 8. Parent/child financial reconciliation must complete before any
            // payment-state mutation, cashback consumption, or journal posting.
            $childOrders = Order::where('parent_order_id', $lockedOrder->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($childOrders->isNotEmpty()) {
                $aggregateFields = [
                    'merchandise_subtotal',
                    'delivery_fee',
                    'cashback_redeemed',
                    'grand_total',
                    'vendor_net_90',
                    'customer_cashback_5',
                    'platform_margin_5',
                    'logistics_fee_retained',
                    'platform_delivery_fee',
                ];
                $aggregates = array_fill_keys($aggregateFields, '0.00');

                foreach ($childOrders as $child) {
                    if ((int) $child->parent_order_id !== (int) $lockedOrder->id
                        || (int) $child->customer_id !== (int) $lockedOrder->customer_id
                        || empty($child->vendor_id)
                        || empty($child->shop_id)
                        || empty($child->payment_reference)
                        || !hash_equals((string) $lockedOrder->payment_reference, (string) $child->payment_reference)
                        || $child->children()->exists()) {
                        return [
                            'success' => false,
                            'status' => 'CHILD_ORDER_INVARIANT_FAILED',
                            'message' => 'Parent/child ownership, payment reference, or hierarchy invariant failed; settlement was not applied.',
                        ];
                    }

                    foreach ($aggregateFields as $field) {
                        $aggregates[$field] = bcadd($aggregates[$field], (string) ($child->{$field} ?? '0.00'), 2);
                    }
                }

                foreach ($aggregateFields as $field) {
                    if (bccomp($aggregates[$field], (string) ($lockedOrder->{$field} ?? '0.00'), 2) !== 0) {
                        return [
                            'success' => false,
                            'status' => 'CHILD_ORDER_ALLOCATION_MISMATCH',
                            'message' => "Parent/child aggregate mismatch for {$field}; settlement was not applied.",
                        ];
                    }
                }
            }

            // 9. Atomic State Transition & Expiry Race Defense
            $now = Carbon::now();
            $oldPaymentStatus = $lockedOrder->payment_status;
            $depletedStockItems = [];
            $hasStockoutIssue = false;
            $stockoutReason = '';
            $allocationResult = null;
            $lockedOrder->payment_status = 'paid';
            $lockedOrder->paid_at = $now;
            $lockedOrder->payment_channel = (string)($paystackData['channel'] ?? 'paystack');
            $lockedOrder->paystack_transaction_id = (string)($paystackData['id'] ?? $reference);

            if ($lockedOrder->order_status === 'cancelled') {
                // The order was previously cancelled by the expiration worker.
                // Verify whether stock can be re-reserved atomically.
                // A retailer order that expired/cancelled before payment must not be
                // silently resurrected after a late charge. Keep its funds held for
                // an authorized refund-review decision under the binding recovery policy.
                $canReinstate = !$lockedOrder->retailer_model_order && $lockedOrder->items->isNotEmpty();
                foreach ($lockedOrder->items as $item) {
                    if ($item->product_id) {
                        $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                        if (!$product || !$product->is_available_for_sale) {
                            $canReinstate = false;
                            break;
                        }
                    }
                }

                if ($canReinstate) {
                    // Reinstate order to confirmed status
                    foreach ($lockedOrder->items as $item) {
                        if ($item->product_id) {
                            $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                            if ($product && isset($product->inventory_quantity) && (int)$product->inventory_quantity > 0) {
                                $product->decrement('inventory_quantity', (int)$item->quantity);
                                $product->is_in_stock = ((int)$product->inventory_quantity > 0);
                                $product->save();
                            }
                        }
                    }
                    $lockedOrder->order_status = 'confirmed';
                    $lockedOrder->vendor_settlement_status = 'held';

                    // A cancelled pickup reservation can be revived only as part
                    // of this verified, locked late-payment reinstatement.
                    if ($lockedOrder->fulfillment_type === 'in_shop_pickup') {
                        PickupReservation::where('order_id', $lockedOrder->id)
                            ->whereIn('status', ['reserved', 'canceled', 'cancelled', 'expired'])
                            ->update(['status' => 'payment_completed']);
                    }

                    AdminAuditLog::create([
                        'user_id' => $lockedOrder->customer_id,
                        'action' => 'paid_after_expiration_reinstated',
                        'target_type' => Order::class,
                        'target_id' => $lockedOrder->id,
                        'old_values' => ['order_status' => 'cancelled', 'payment_status' => 'pending'],
                        'new_values' => ['order_status' => 'confirmed', 'payment_status' => 'paid', 'stock_re_reserved' => true],
                        'ip_address' => request()->ip() ?? '127.0.0.1',
                        'user_agent' => request()->userAgent(),
                    ]);
                } else {
                    // Stock is depleted. Order marked as paid_stock_unavailable; funds held as customer refund liability.
                    $lockedOrder->order_status = 'paid_stock_unavailable';
                    $lockedOrder->vendor_settlement_status = 'refund_pending';

                    AdminAuditLog::create([
                        'user_id' => $lockedOrder->customer_id,
                        'action' => 'paid_after_expiration_out_of_stock_refund_queued',
                        'target_type' => Order::class,
                        'target_id' => $lockedOrder->id,
                        'old_values' => ['order_status' => 'cancelled', 'payment_status' => 'pending'],
                        'new_values' => [
                            'order_status' => 'paid_stock_unavailable',
                            'payment_status' => 'paid',
                            'vendor_settlement_status' => 'refund_pending',
                            'reason' => 'Inventory depleted following cancellation; payment queued for customer refund.',
                        ],
                        'ip_address' => request()->ip() ?? '127.0.0.1',
                        'user_agent' => request()->userAgent(),
                    ]);
                }
            } elseif ($lockedOrder->order_status === 'pending') {
                $lockedOrder->order_status = 'confirmed';
                $lockedOrder->vendor_settlement_status = 'held';

                $depletedStockItems = [];
                $decrementedProducts = [];

                // Binding sourcing starts only now: payment has been verified and
                // the order is locked inside this settlement transaction. Checkout
                // stores no supplier assignments or wholesale-cost snapshots.
                if ($lockedOrder->retailer_model_order) {
                    $allocationResult = app(SourcingAllocationService::class)->allocateVerifiedPaidOrder($lockedOrder);

                    // Allocation updates the order through its own row-locked model
                    // instance. Copy those authoritative fields onto this settlement
                    // instance before its later save, otherwise stale null snapshots
                    // would overwrite the just-created supplier-cost totals.
                    if (!empty($allocationResult['success'])) {
                        $sourcedState = Order::whereKey($lockedOrder->id)->firstOrFail();
                        foreach ([
                            'vendor_id',
                            'shop_id',
                            'supplier_cost_total_snapshot',
                            'fulfillment_supplier_cost_total_snapshot',
                            'retailer_merchandise_margin',
                            'sourcing_plan_locations_count',
                            'sourced_at',
                        ] as $field) {
                            $lockedOrder->{$field} = $sourcedState->{$field};
                        }
                    }
                }

                // Legacy product inventory remains authoritative for legacy-owned stock.
                // New canonical retailer products have no vendor/shop owner and use the
                // verified supplier offer as the stock signal. Their inventory_quantity=0
                // is a compatibility sentinel, not a depleted legacy inventory counter.
                // Do not turn that sentinel into a false post-payment stockout/refund hold.
                foreach ($lockedOrder->items as $item) {
                    if ($item->product_id) {
                        $prod = Product::where('id', $item->product_id)->lockForUpdate()->first();
                        if (!$prod || !isset($prod->inventory_quantity)) {
                            continue;
                        }

                        $supplierSourcedCanonicalProduct = $lockedOrder->retailer_model_order
                            && $item->supplier_offer_id
                            && $prod->vendor_id === null
                            && $prod->shop_id === null
                            && (int) $prod->inventory_quantity === 0
                            && (bool) $prod->is_in_stock;

                        if ($supplierSourcedCanonicalProduct) {
                            continue;
                        }

                        if ((int) $prod->inventory_quantity >= (int) $item->quantity) {
                            $prod->decrement('inventory_quantity', (int) $item->quantity);
                            $prod->is_in_stock = ((int) $prod->inventory_quantity > 0);
                            $prod->save();
                            $decrementedProducts[] = ['product' => $prod, 'quantity' => (int) $item->quantity];
                        } else {
                            $depletedStockItems[] = $item->product_name ?? "Product #{$prod->id}";
                        }
                    }
                }

                // Transition any associated pickup reservation to payment_completed upon confirmed gateway settlement
                \App\Models\PickupReservation::where('order_id', $lockedOrder->id)->update(['status' => 'payment_completed']);
            }

            // Post-Payment Stock & Supplier Availability Revalidation (Retailer Model)
            if ($lockedOrder->retailer_model_order && $lockedOrder->payment_status === 'paid'
                && $lockedOrder->order_status === 'confirmed') {
                $hasStockoutIssue = !empty($depletedStockItems)
                    || ($allocationResult !== null && empty($allocationResult['success']));
                $stockoutReason = !empty($depletedStockItems)
                    ? 'Inventory depleted for: ' . implode(', ', $depletedStockItems)
                    : (($allocationResult !== null && empty($allocationResult['success']))
                        ? (string) ($allocationResult['reason'] ?? 'No eligible supplier plan remains after verified payment.')
                        : '');

                $orderItems = $lockedOrder->items()->orderBy('id')->lockForUpdate()->get();
                $assignments = \App\Models\OrderSupplierAssignment::where('order_id', $lockedOrder->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($orderItems as $item) {
                    if ($item->supplier_offer_id) {
                        $offer = \App\Models\SupplierOffer::where('id', $item->supplier_offer_id)->lockForUpdate()->first();
                        if (!$offer || !$offer->is_available || $offer->isPenaltyActive() || ($offer->availability_expires_at && $offer->availability_expires_at->lte($now))) {
                            $hasStockoutIssue = true;
                            $stockoutReason = "Supplier offer for {$item->product_name} is no longer available or under penalty at payment.";
                            break;
                        }
                    }

                    $pendingHandovers = \App\Models\OrderSupplierAssignment::where('supplier_vendor_id', $item->supplier_vendor_id)
                        ->whereIn('status', ['awaiting_supplier_confirmation', 'supplier_confirmed', 'ready_for_collection'])
                        ->where('order_id', '!=', $lockedOrder->id)
                        ->count();
                    if ($pendingHandovers >= 5) {
                        $hasStockoutIssue = true;
                        $stockoutReason = "Supplier for {$item->product_name} has reached maximum pending handover limit (5).";
                        break;
                    }
                }

                if ($hasStockoutIssue) {
                    // Restore any catalog inventory decremented earlier because this order cannot be fulfilled
                    if (!empty($decrementedProducts)) {
                        foreach ($decrementedProducts as $dec) {
                            $dec['product']->increment('inventory_quantity', $dec['quantity']);
                            $dec['product']->is_in_stock = true;
                            $dec['product']->save();
                        }
                    }

                    $lockedOrder->sourcing_status = 'stockout_revalidation_hold';
                    $lockedOrder->sourcing_notes = $stockoutReason;

                    foreach ($assignments as $assignment) {
                        $assignment->status = 'stockout_revalidation_hold';
                        $assignment->save();
                    }

                    AdminAuditLog::create([
                        'user_id' => $actorUser ? $actorUser->id : null,
                        'action' => 'supplier_stockout_revalidation_hold',
                        'target_type' => Order::class,
                        'target_id' => $lockedOrder->id,
                        'new_values' => [
                            'reason' => $stockoutReason,
                            'order_number' => $lockedOrder->order_number,
                            'paid_at' => $lockedOrder->paid_at->toIso8601String(),
                        ],
                        'ip_address' => request()->ip() ?? '127.0.0.1',
                        'user_agent' => request()->userAgent(),
                    ]);
                } else {
                    $lockedOrder->sourcing_status = 'awaiting_supplier_confirmation';

                    foreach ($assignments as $assignment) {
                        $assignment->status = 'awaiting_supplier_confirmation';
                        $assignment->assigned_at = $now;
                        $assignment->save();
                    }
                }
            }

            $lockedOrder->save();

            // 9. Isolated Refund-Only Settlement Branch (Late Payment Depleted Stock)
            if ($lockedOrder->vendor_settlement_status === 'refund_pending') {
                // Post strictly balanced 2-legged refund journal:
                // Debit: Cash in Escrow (exact grand_total cash received from Paystack)
                // Credit: Customer Refund Liability (100% held for refund disbursement)
                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => $reference,
                    'event_id' => 'EVT-REFUND-ESCROW-' . $reference,
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => $lockedOrder->customer_id,
                    'account_name' => 'cash_in_escrow',
                    'entry_type' => 'debit',
                    'amount' => $lockedOrder->grand_total,
                    'currency' => 'NGN',
                    'description' => "Paystack gateway capture for cancelled/out-of-stock Order #{$lockedOrder->order_number}",
                ]);

                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => $reference,
                    'event_id' => 'EVT-REFUND-LIAB-' . $reference,
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => $lockedOrder->customer_id,
                    'account_name' => 'customer_refund_liability',
                    'entry_type' => 'credit',
                    'amount' => $lockedOrder->grand_total,
                    'currency' => 'NGN',
                    'description' => "Customer Refund Liability held in escrow for Order #{$lockedOrder->order_number} (Inventory depleted after expiration)",
                ]);

                // The payment capture and its customer refund obligation must commit
                // atomically. If the pending admin-review operation cannot be persisted,
                // throw and roll back the payment state + liability journal together.
                // This creates a request only; it never dispatches a gateway refund.
                $latePaymentRefundReason = "Refund pending administrative authorization for cancelled/depleted Order #{$lockedOrder->order_number}";
                app(\App\Services\FinancialOperationService::class)->createRefundOperation(
                    $lockedOrder,
                    (string) $lockedOrder->grand_total,
                    $latePaymentRefundReason,
                    null,
                    'PAYSTACK-LATE-REFUND-' . $lockedOrder->id,
                    ['type' => 'pending_admin_review_late_payment']
                );

                // Invariant: Customer cashback balance is LEFT UNTOUCHED (zero lots consumed)
                // Invariant: ZERO new cashback reward is accrued for unfulfilled orders
                // Invariant: ZERO merchant, logistics, or platform cut is distributed

                // Release any pre-payment cashback reservation
                \App\Models\CashbackReservation::where('order_id', $lockedOrder->id)
                    ->where('status', 'reserved')
                    ->update(['status' => 'released']);

                AdminAuditLog::create([
                    'user_id' => $lockedOrder->customer_id,
                    'action' => 'paystack_settlement_refund_pending_recorded',
                    'target_type' => Order::class,
                    'target_id' => $lockedOrder->id,
                    'old_values' => ['order_status' => 'cancelled', 'payment_status' => $oldPaymentStatus],
                    'new_values' => [
                        'order_status' => 'cancelled',
                        'payment_status' => 'paid',
                        'vendor_settlement_status' => 'refund_pending',
                        'refund_liability_amount' => $lockedOrder->grand_total,
                        'paystack_refund_reference' => $lockedOrder->paystack_refund_reference,
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent(),
                ]);

                return [
                    'success' => true,
                    'status' => 'REFUND_PENDING',
                    'is_refund_only' => true,
                    'message' => 'Order was cancelled prior to payment and inventory is depleted. 100% of payment held in escrow as customer refund liability and automated refund requested.',
                    'order' => $lockedOrder,
                    'trigger_gateway_refund' => true,
                    'refund_reference' => $reference,
                    'refund_amount' => (string)$lockedOrder->grand_total,
                    'refund_order_id' => $lockedOrder->id,
                    'refund_order_number' => $lockedOrder->order_number,
                ];
            }

            // 10. Standard Fulfilled Order Settlement: Balanced Double-Entry Financial Journal
            // Debit: Cash in Escrow (Grand Total Cash Paid)
            FinancialJournalEntry::create([
                'order_id' => $lockedOrder->id,
                'transaction_reference' => $reference,
                'event_id' => 'EVT-PAY-' . $reference,
                'beneficiary_type' => User::class,
                'beneficiary_id' => $lockedOrder->customer_id,
                'account_name' => 'cash_in_escrow',
                'entry_type' => 'debit',
                'amount' => $lockedOrder->grand_total,
                'currency' => 'NGN',
                'description' => "Paystack gateway capture for Order #{$lockedOrder->order_number}",
            ]);

            // Debit: Cashback Liability Redeemed (if customer applied previously earned cashback)
            if (bccomp((string)$lockedOrder->cashback_redeemed, '0.00', 2) > 0) {
                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => $reference,
                    'event_id' => 'EVT-CB-RED-' . $reference,
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => $lockedOrder->customer_id,
                    'account_name' => 'cashback_liability',
                    'entry_type' => 'debit',
                    'amount' => $lockedOrder->cashback_redeemed,
                    'currency' => 'NGN',
                    'description' => "Customer cashback redeemed for Order #{$lockedOrder->order_number}",
                ]);

                // Consume available customer cashback entries under exclusive pessimistic row lock
                if ($lockedOrder->customer_id) {
                    $remainingToDeduct = (string)$lockedOrder->cashback_redeemed;
                    $availableLots = CustomerCashbackLedger::where('customer_id', $lockedOrder->customer_id)
                        ->where('status', 'available')
                        ->where('matures_at', '<=', $now)
                        ->where('expires_at', '>', $now)
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->get();

                    $totalAvailable = '0.00';
                    foreach ($availableLots as $lot) {
                        $totalAvailable = bcadd($totalAvailable, (string)$lot->amount, 2);
                    }

                    if (bccomp($totalAvailable, $remainingToDeduct, 2) < 0) {
                        throw new \RuntimeException("Insufficient matured cashback balance to satisfy redemption of ₦{$remainingToDeduct}. Available: ₦{$totalAvailable}.");
                    }

                    foreach ($availableLots as $lot) {
                        if (bccomp($remainingToDeduct, '0.00', 2) <= 0) {
                            break;
                        }
                        if (bccomp((string)$lot->amount, $remainingToDeduct, 2) <= 0) {
                            $remainingToDeduct = bcsub($remainingToDeduct, (string)$lot->amount, 2);
                            $lot->status = 'redeemed';
                            $lot->save();
                        } else {
                            $lot->amount = bcsub((string)$lot->amount, $remainingToDeduct, 2);
                            $lot->save();
                            $remainingToDeduct = '0.00';
                        }
                    }

                    // Transition reservation to consumed
                    \App\Models\CashbackReservation::where('order_id', $lockedOrder->id)
                        ->where('status', 'reserved')
                        ->update(['status' => 'consumed']);
                }
            }

            // Debit: Customer Wallet Liability Redeemed (if customer applied instant refund wallet credit)
            if (bccomp((string)$lockedOrder->wallet_credit_applied, '0.00', 2) > 0) {
                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => $reference,
                    'event_id' => 'EVT-WALLET-RED-' . $reference,
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => $lockedOrder->customer_id,
                    'account_name' => 'customer_wallet_liability',
                    'entry_type' => 'debit',
                    'amount' => $lockedOrder->wallet_credit_applied,
                    'currency' => 'NGN',
                    'description' => "Customer refund wallet credit redeemed for Order #{$lockedOrder->order_number}",
                ]);

                // Atomically spend customer wallet balance
                if ($lockedOrder->customer_id) {
                    \App\Models\CustomerWalletCredit::spendWalletBalance(
                        $lockedOrder->customer_id,
                        $lockedOrder->id,
                        (string)$lockedOrder->wallet_credit_applied
                    );
                }
            }

            // Check if this is a Multi-Vendor Parent Order with child orders
            $childOrders = $lockedOrder->children()->get();
            if ($childOrders->count() > 0) {
                // Settle each child vendor order for their own merchandise subtotal only (Delivery fee excluded)
                foreach ($childOrders as $child) {
                    $child->payment_status = 'paid';
                    $child->order_status = 'confirmed';
                    $child->paid_at = $now;
                    $child->save();

                    // Credit: Vendor Payable for Child Vendor (90% Merchandise Net)
                    FinancialJournalEntry::create([
                        'order_id' => $child->id,
                        'transaction_reference' => $reference,
                        'event_id' => 'EVT-VND-ESCROW-' . $child->id . '-' . $reference,
                        'beneficiary_type' => VendorProfile::class,
                        'beneficiary_id' => $child->vendor_id,
                        'account_name' => 'vendor_payable',
                        'entry_type' => 'credit',
                        'amount' => $child->vendor_net_90,
                        'currency' => 'NGN',
                        'description' => "Merchant 90% Net Payable held in escrow for Child Order #{$child->order_number} (Vendor #{$child->vendor_id})",
                    ]);

                    // Credit: Customer Cashback Liability (5% Merchandise Split for Child Order)
                    if (bccomp((string)$child->customer_cashback_5, '0.00', 2) > 0) {
                        FinancialJournalEntry::create([
                            'order_id' => $child->id,
                            'transaction_reference' => $reference,
                            'event_id' => 'EVT-CB-ACCRUE-' . $child->id . '-' . $reference,
                            'beneficiary_type' => User::class,
                            'beneficiary_id' => $child->customer_id,
                            'account_name' => 'cashback_liability',
                            'entry_type' => 'credit',
                            'amount' => $child->customer_cashback_5,
                            'currency' => 'NGN',
                            'description' => "Customer 5% Cashback Reward Liability accrued for Child Order #{$child->order_number}",
                        ]);

                        CustomerCashbackLedger::updateOrCreate([
                            'order_id' => $child->id,
                        ], [
                            'customer_id' => $child->customer_id,
                            'amount' => $child->customer_cashback_5,
                            'status' => 'pending_maturation',
                            'matures_at' => null, // No maturity clock before verified fulfilment.
                            'expires_at' => null, // Validity starts only after maturity.
                        ]);
                    }

                    // Credit: Platform Merchandise Revenue (5% Commercial Cut for Child Order)
                    if (bccomp((string)$child->platform_margin_5, '0.00', 2) > 0) {
                        FinancialJournalEntry::create([
                            'order_id' => $child->id,
                            'transaction_reference' => $reference,
                            'event_id' => 'EVT-PLATFORM-REV-' . $child->id . '-' . $reference,
                            'beneficiary_type' => User::class,
                            'beneficiary_id' => null,
                            'account_name' => 'platform_revenue',
                            'entry_type' => 'credit',
                            'amount' => $child->platform_margin_5,
                            'currency' => 'NGN',
                            'description' => "Victorious Market 5% Merchandise Commission for Child Order #{$child->order_number}",
                        ]);
                    }

                    // Delivery fee is a separate vendor earning (90%); no fleet/rider payable.
                    if (bccomp((string)$child->logistics_fee_retained, '0.00', 2) > 0) {
                        FinancialJournalEntry::create([
                            'order_id' => $child->id,
                            'transaction_reference' => $reference,
                            'event_id' => 'EVT-VND-DELIVERY-ESCROW-' . $child->id . '-' . $reference,
                            'beneficiary_type' => VendorProfile::class,
                            'beneficiary_id' => $child->vendor_id,
                            'account_name' => 'vendor_payable',
                            'entry_type' => 'credit',
                            'amount' => $child->logistics_fee_retained,
                            'currency' => 'NGN',
                            'description' => "Vendor 90% delivery-fee earning held for Child Order #{$child->order_number}",
                        ]);
                    }

                    // Credit: Platform Delivery Fee Cut per Child Order (10% Delivery Cut)
                    if (bccomp((string)$child->platform_delivery_fee, '0.00', 2) > 0) {
                        FinancialJournalEntry::create([
                            'order_id' => $child->id,
                            'transaction_reference' => $reference,
                            'event_id' => 'EVT-PLATFORM-DELIVERY-REV-' . $child->id . '-' . $reference,
                            'beneficiary_type' => User::class,
                            'beneficiary_id' => null,
                            'account_name' => 'platform_revenue',
                            'entry_type' => 'credit',
                            'amount' => $child->platform_delivery_fee,
                            'currency' => 'NGN',
                            'description' => "Victorious Market 10% Delivery Fee for Child Order #{$child->order_number}",
                        ]);
                    }
                }

                // Parent payment is allocated across child orders above. Do not post a
                // second parent-level delivery split: each child's 90/10 split is authoritative.
            } else {
                if ($lockedOrder->retailer_model_order) {
                    if ($hasStockoutIssue) {
                        // When post-payment revalidation detects inventory depletion or supplier unavailability,
                        // order is placed on stockout_revalidation_hold.
                        // Zero supplier payable, zero merchant margin, zero logistics payable, and zero cashback reward.
                        // Customer funds are held intact in escrow liability.
                        $totalCustomerFunds = BcMathService::add(
                            BcMathService::add((string)$lockedOrder->grand_total, (string)$lockedOrder->cashback_redeemed),
                            (string)$lockedOrder->wallet_credit_applied
                        );

                        FinancialJournalEntry::create([
                            'order_id' => $lockedOrder->id,
                            'transaction_reference' => $reference,
                            'event_id' => 'EVT-ESCROW-HOLD-' . $reference,
                            'beneficiary_type' => User::class,
                            'beneficiary_id' => $lockedOrder->customer_id,
                            'account_name' => 'customer_held_escrow',
                            'entry_type' => 'credit',
                            'amount' => $totalCustomerFunds,
                            'currency' => 'NGN',
                            'description' => "Customer payment held in escrow under stockout revalidation hold for Order #{$lockedOrder->order_number}: {$stockoutReason}",
                        ]);
                    } else {
                        // New retailer accounting: supplier payables are the immutable
                        // item-level wholesale costs, never a percentage of merchandise.
                        $items = $lockedOrder->items()->orderBy('id')->lockForUpdate()->get();
                    $supplierCostTotal = '0.00';
                    foreach ($items as $item) {
                        if (empty($item->supplier_vendor_id) || $item->supplier_cost_snapshot === null) {
                            throw new \RuntimeException('Retailer order item is missing its immutable supplier/cost snapshot.');
                        }
                        $lineCost = BcMathService::mul((string) $item->supplier_cost_snapshot, (string) $item->quantity);
                        $supplierCostTotal = BcMathService::add($supplierCostTotal, $lineCost);
                    }
                    if ($lockedOrder->supplier_cost_total_snapshot === null
                        || BcMathService::comp($supplierCostTotal, (string) $lockedOrder->supplier_cost_total_snapshot) !== 0) {
                        throw new \RuntimeException('Retailer supplier-cost total is missing or does not match the immutable order snapshot.');
                    }

                    foreach ($items as $item) {
                        $lineCost = BcMathService::mul((string) $item->supplier_cost_snapshot, (string) $item->quantity);
                        if (BcMathService::comp($lineCost, '0.00') > 0) {
                            FinancialJournalEntry::create([
                                'order_id' => $lockedOrder->id,
                                'order_item_id' => $item->id,
                                'transaction_reference' => $reference,
                                'event_id' => 'EVT-SUPPLIER-PAYABLE-' . $lockedOrder->id . '-ITEM-' . $item->id . '-' . $reference,
                                'beneficiary_type' => VendorProfile::class,
                                'beneficiary_id' => $item->supplier_vendor_id,
                                'account_name' => 'supplier_payable',
                                'entry_type' => 'credit',
                                'amount' => $lineCost,
                                'currency' => 'NGN',
                                'description' => "Agreed wholesale supplier payable for order item #{$item->id} on Order #{$lockedOrder->order_number}",
                            ]);
                        }
                    }

                    if (BcMathService::comp((string) $lockedOrder->customer_cashback_5, '0.00') > 0) {
                        FinancialJournalEntry::create([
                            'order_id' => $lockedOrder->id,
                            'transaction_reference' => $reference,
                            'event_id' => 'EVT-CB-ACCRUE-' . $reference,
                            'beneficiary_type' => User::class,
                            'beneficiary_id' => $lockedOrder->customer_id,
                            'account_name' => 'cashback_liability',
                            'entry_type' => 'credit',
                            'amount' => $lockedOrder->customer_cashback_5,
                            'currency' => 'NGN',
                            'description' => "Retailer 5% real-money merchandise cashback liability accrued for Order #{$lockedOrder->order_number}",
                        ]);

                        CustomerCashbackLedger::updateOrCreate([
                            'order_id' => $lockedOrder->id,
                        ], [
                            'customer_id' => $lockedOrder->customer_id,
                            'amount' => $lockedOrder->customer_cashback_5,
                            'status' => 'pending_maturation',
                            'matures_at' => null,
                            'expires_at' => null,
                        ]);
                    }

                    $retailerMargin = (string) ($lockedOrder->retailer_merchandise_margin ?? '0.00');
                    if (BcMathService::comp($retailerMargin, '0.00') > 0) {
                        FinancialJournalEntry::create([
                            'order_id' => $lockedOrder->id,
                            'transaction_reference' => $reference,
                            'event_id' => 'EVT-RETAIL-MARGIN-' . $reference,
                            'beneficiary_type' => User::class,
                            'beneficiary_id' => null,
                            'account_name' => 'retailer_merchandise_margin',
                            'entry_type' => 'credit',
                            'amount' => $retailerMargin,
                            'currency' => 'NGN',
                            'description' => "Retail merchandise margin after supplier cost and cashback for Order #{$lockedOrder->order_number}",
                        ]);
                    } elseif (BcMathService::comp($retailerMargin, '0.00') < 0) {
                        FinancialJournalEntry::create([
                            'order_id' => $lockedOrder->id,
                            'transaction_reference' => $reference,
                            'event_id' => 'EVT-RETAIL-LOSS-' . $reference,
                            'beneficiary_type' => User::class,
                            'beneficiary_id' => null,
                            'account_name' => 'retailer_merchandise_loss',
                            'entry_type' => 'debit',
                            'amount' => BcMathService::sub('0.00', $retailerMargin),
                            'currency' => 'NGN',
                            'description' => "Retail merchandise loss where supplier cost plus cashback exceeds merchandise revenue for Order #{$lockedOrder->order_number}",
                        ]);
                    }

                    $deliveryFeeSnapshot = (string) $lockedOrder->delivery_fee;
                    $deliveryMargin = '0.00';
                    $deliverySubsidy = '0.00';
                    $defaultCompanyPayable = $lockedOrder->logistics_company_payable_default_amount;
                    $hasValidCompanyTariff = $defaultCompanyPayable !== null
                        && is_numeric($defaultCompanyPayable)
                        && BcMathService::comp((string) $defaultCompanyPayable, '0.00') > 0;

                    if (!$hasValidCompanyTariff) {
                        // Do not lose a real Paystack capture merely because a legacy order
                        // lacks a company tariff. Settle the customer payment, keep the
                        // logistics payable unassigned, and raise an auditable exception.
                        $companyPayable = '0.00';
                        $lockedOrder->logistics_company_payable_amount = null;
                        $lockedOrder->logistics_company_payable_ledger_company_id = null;
                        $lockedOrder->logistics_subsidy_amount = '0.00';
                        // Keep the captured delivery-fee component balanced in suspense.
                        // This is explicitly NOT a logistics company payable and cannot be released.
                        if (BcMathService::comp($deliveryFeeSnapshot, '0.00') > 0) {
                            FinancialJournalEntry::create([
                                'order_id' => $lockedOrder->id,
                                'transaction_reference' => $reference,
                                'event_id' => 'EVT-LOG-SUSPENSE-' . $reference,
                                'beneficiary_type' => User::class,
                                'beneficiary_id' => null,
                                'account_name' => 'logistics_payable_suspense',
                                'entry_type' => 'credit',
                                'amount' => $deliveryFeeSnapshot,
                                'currency' => 'NGN',
                                'description' => 'Customer delivery charge held in suspense because the independent company/corridor tariff is missing; not a company payable.',
                            ]);
                        }

                        AdminAuditLog::create([
                            'user_id' => $lockedOrder->customer_id,
                            'action' => 'logistics_payable_missing_tariff_hold',
                            'target_type' => Order::class,
                            'target_id' => $lockedOrder->id,
                            'old_values' => ['logistics_company_payable_default_amount' => null],
                            'new_values' => [
                                'payment_settlement' => 'recorded_without_logistics_payable',
                                'logistics_company_assignment' => 'blocked_pending_tariff_reconciliation',
                                'delivery_fee_snapshot' => $deliveryFeeSnapshot,
                                'suspense_account' => 'logistics_payable_suspense',
                            ],
                            'ip_address' => request()->ip() ?? '127.0.0.1',
                            'user_agent' => request()->userAgent(),
                        ]);
                    } else {
                        $companyPayable = (string) ($lockedOrder->logistics_company_payable_amount ?? $defaultCompanyPayable);
                        $lockedOrder->logistics_company_payable_amount = $companyPayable;
                        $lockedOrder->logistics_company_payable_ledger_company_id = $lockedOrder->logistics_company_id;

                        $deliveryMargin = BcMathService::comp($deliveryFeeSnapshot, $companyPayable) > 0
                            ? BcMathService::sub($deliveryFeeSnapshot, $companyPayable)
                            : '0.00';
                        $deliverySubsidy = BcMathService::comp($companyPayable, $deliveryFeeSnapshot) > 0
                            ? BcMathService::sub($companyPayable, $deliveryFeeSnapshot)
                            : '0.00';
                        $lockedOrder->logistics_subsidy_amount = $deliverySubsidy;
                    }
                    $lockedOrder->save();

                    if (BcMathService::comp($companyPayable, '0.00') > 0) {
                        FinancialJournalEntry::create([
                            'order_id' => $lockedOrder->id,
                            'transaction_reference' => $reference,
                            'event_id' => 'EVT-LOG-COMPANY-PAYABLE-' . $reference,
                            'beneficiary_type' => LogisticsCompanyProfile::class,
                            'beneficiary_id' => $lockedOrder->logistics_company_id,
                            'account_name' => 'logistics_company_payable',
                            'entry_type' => 'credit',
                            'amount' => $companyPayable,
                            'currency' => 'NGN',
                            'description' => "Default/approved logistics-company payable for Order #{$lockedOrder->order_number}; not a rider payable.",
                        ]);
                    }



                    if (BcMathService::comp($deliveryMargin, '0.00') > 0) {
                        FinancialJournalEntry::create([
                            'order_id' => $lockedOrder->id,
                            'transaction_reference' => $reference,
                            'event_id' => 'EVT-PLATFORM-DELIVERY-REV-' . $reference,
                            'beneficiary_type' => User::class,
                            'beneficiary_id' => null,
                            'account_name' => 'platform_delivery_revenue',
                            'entry_type' => 'credit',
                            'amount' => $deliveryMargin,
                            'currency' => 'NGN',
                            'description' => "VM delivery margin after logistics-company payable for Order #{$lockedOrder->order_number}",
                        ]);
                    }
                    if (BcMathService::comp($deliverySubsidy, '0.00') > 0) {
                        FinancialJournalEntry::create([
                            'order_id' => $lockedOrder->id,
                            'transaction_reference' => $reference,
                            'event_id' => 'EVT-DELIVERY-SUBSIDY-' . $reference,
                            'beneficiary_type' => User::class,
                            'beneficiary_id' => null,
                            'account_name' => 'delivery_subsidy_expense',
                            'entry_type' => 'debit',
                            'amount' => $deliverySubsidy,
                            'currency' => 'NGN',
                            'description' => "VM-funded delivery subsidy where company payable exceeds customer delivery fee for Order #{$lockedOrder->order_number}",
                        ]);
                    }
                    }
                } else {
                // Standard Single-Vendor Order Path:
                // Credit: Vendor Payable (90% Merchandise Net)
                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => $reference,
                    'event_id' => 'EVT-VND-ESCROW-' . $reference,
                    'beneficiary_type' => VendorProfile::class,
                    'beneficiary_id' => $lockedOrder->vendor_id,
                    'account_name' => 'vendor_payable',
                    'entry_type' => 'credit',
                    'amount' => $lockedOrder->vendor_net_90,
                    'currency' => 'NGN',
                    'description' => "Merchant 90% Net Payable held in escrow for Order #{$lockedOrder->order_number}",
                ]);

                // Delivery fee is a separate vendor earning (90%); no fleet/rider payable.
                if (bccomp((string)$lockedOrder->logistics_fee_retained, '0.00', 2) > 0) {
                    FinancialJournalEntry::create([
                        'order_id' => $lockedOrder->id,
                        'transaction_reference' => $reference,
                        'event_id' => 'EVT-VND-DELIVERY-ESCROW-' . $reference,
                        'beneficiary_type' => VendorProfile::class,
                        'beneficiary_id' => $lockedOrder->vendor_id,
                        'account_name' => 'vendor_payable',
                        'entry_type' => 'credit',
                        'amount' => $lockedOrder->logistics_fee_retained,
                        'currency' => 'NGN',
                        'description' => "Vendor 90% delivery-fee earning held for Order #{$lockedOrder->order_number}",
                    ]);
                }

                // Credit: Customer Cashback Liability (5% Merchandise Split)
                if (bccomp((string)$lockedOrder->customer_cashback_5, '0.00', 2) > 0) {
                    FinancialJournalEntry::create([
                        'order_id' => $lockedOrder->id,
                        'transaction_reference' => $reference,
                        'event_id' => 'EVT-CB-ACCRUE-' . $reference,
                        'beneficiary_type' => User::class,
                        'beneficiary_id' => $lockedOrder->customer_id,
                        'account_name' => 'cashback_liability',
                        'entry_type' => 'credit',
                        'amount' => $lockedOrder->customer_cashback_5,
                        'currency' => 'NGN',
                        'description' => "Customer 5% Cashback Reward Liability accrued for Order #{$lockedOrder->order_number}",
                    ]);
                }

                // Credit: Platform Revenue (5% Margin + 10% Delivery Cut)
                $platformTotalCut = bcadd((string)$lockedOrder->platform_margin_5, (string)$lockedOrder->platform_delivery_fee, 2);
                if (bccomp($platformTotalCut, '0.00', 2) > 0) {
                    FinancialJournalEntry::create([
                        'order_id' => $lockedOrder->id,
                        'transaction_reference' => $reference,
                        'event_id' => 'EVT-PLATFORM-REV-' . $reference,
                        'beneficiary_type' => User::class,
                        'beneficiary_id' => null,
                        'account_name' => 'platform_revenue',
                        'entry_type' => 'credit',
                        'amount' => $platformTotalCut,
                        'currency' => 'NGN',
                        'description' => "Victorious Market Gross Retained Commission for Order #{$lockedOrder->order_number}",
                    ]);
                }

                // 11. Register 5% Customer Cashback Reward in Ledger
                // Invariant: Cashback is strictly NOT redeemable at payment!
                // Maturation countdown commences ONLY upon physical delivery / counter handover inspection.
                if ($lockedOrder->customer_id && bccomp((string)$lockedOrder->customer_cashback_5, '0.00', 2) > 0) {
                    CustomerCashbackLedger::updateOrCreate([
                        'order_id' => $lockedOrder->id,
                    ], [
                        'customer_id' => $lockedOrder->customer_id,
                        'amount' => $lockedOrder->customer_cashback_5,
                        'status' => 'pending_maturation',
                        'matures_at' => null, // No maturity clock before verified fulfilment.
                        'expires_at' => null, // Validity starts only after maturity.
                    ]);
                }
                }
            }

            // 10. Immutable Audit Trail
            AdminAuditLog::create([
                'user_id' => $actorUser ? $actorUser->id : null,
                'action' => 'paystack_payment_verified',
                'target_type' => Order::class,
                'target_id' => $lockedOrder->id,
                'old_values' => [
                    'payment_status' => $oldPaymentStatus,
                ],
                'new_values' => [
                    'payment_status' => 'paid',
                    'payment_channel' => $lockedOrder->payment_channel,
                    'paystack_reference' => $reference,
                    'paystack_transaction_id' => $lockedOrder->paystack_transaction_id,
                    'paid_at' => $lockedOrder->paid_at->toIso8601String(),
                    'grand_total' => $lockedOrder->grand_total,
                    'amount_kobo' => $actualKobo,
                    'channel_source' => $channel,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            // 11. Multi-Channel Alert to Customer & Vendor (dispatched after successful commit)
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($lockedOrder) {
                try {
                    NotificationDispatchService::dispatchOrderPlaced($lockedOrder);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Order #{$lockedOrder->id} notification dispatch deferred: " . $e->getMessage());
                }
            });

            return [
                'success' => true,
                'status' => 'PAID',
                'is_replayed' => false,
                'message' => 'Payment verified and order successfully confirmed.',
                'order' => $lockedOrder,
            ];
        }, 5);

        // Late-payment refund obligations are created inside the settlement transaction
        // above. There is deliberately no post-commit best-effort creation step here:
        // a committed refund liability must never be missing its admin-review operation.
        return $result;