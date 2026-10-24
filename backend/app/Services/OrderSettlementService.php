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
     * Settle an independently verified direct bank credit. The caller must already
     * have matched a unique bank transaction reference and exact credited amount to
     * the immutable order payment reference. This deliberately does not create a
     * Paystack transaction ID or invoke Paystack APIs.
     */
    public function settleManualBankTransferOrder(Order $order, string $bankTransactionReference, int $amountKobo, User $admin): array
    {
        if (trim($bankTransactionReference) === '' || $amountKobo < 1) {
            return ['success' => false, 'status' => 'INVALID_BANK_EVIDENCE', 'message' => 'A bank transaction reference and positive credited amount are required.'];
        }

        if (empty($order->payment_reference)) {
            return ['success' => false, 'status' => 'MISSING_ORDER_PAYMENT_REFERENCE', 'message' => 'The order has no immutable payment reference.'];
        }

        if (Order::where('payment_reference', trim($bankTransactionReference))
            ->orWhere('paystack_transaction_id', trim($bankTransactionReference))
            ->exists()
            || \App\Models\ManualBankTransferPayment::where('bank_transaction_reference', trim($bankTransactionReference))
                ->where('order_id', '!=', $order->id)
                ->exists()) {
            return ['success' => false, 'status' => 'BANK_TRANSACTION_ALREADY_USED', 'message' => 'This bank transaction identity is already associated with another payment/order.'];
        }

        return $this->settleVerifiedPaymentOrder($order, [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => $amountKobo,
            // This is the order's own immutable internal payment reference, not a
            // Paystack reference. The bank reference remains in the manual tender table.
            'reference' => (string) $order->payment_reference,
            'id' => $bankTransactionReference,
            'channel' => 'manual_bank_transfer',
            'customer' => ['email' => (string) ($order->customer->email ?? '')],
        ], $admin, 'manual_bank_transfer_admin_review', null, null, 'manual_bank_transfer');
    }

    /**
     * Backward-compatible Paystack entry point. All existing Paystack callers stay
     * on the Paystack-only route; manual transfers call the provider-neutral core
     * directly with their own verified bank-credit evidence.
     */
    public function settlePaystackOrder(
        Order $order,
        array $paystackData,
        ?User $actorUser = null,
        string $channel = 'paystack_webhook',
        ?int $webhookEventId = null,
        ?int $webhookAttempt = null
    ): array {
        return $this->settleVerifiedPaymentOrder(
            $order,
            $paystackData,
            $actorUser,
            $channel,
            $webhookEventId,
            $webhookAttempt,
            'paystack'
        );
    }

    public function settleVerifiedPaymentOrder(
        Order $order,
        array $paymentData,
        ?User $actorUser = null,
        string $channel = 'payment_provider',
        ?int $webhookEventId = null,
        ?int $webhookAttempt = null,
        string $provider = 'paystack'
    ): array {
        if (!in_array($provider, ['paystack', 'manual_bank_transfer'], true)) {
            return ['success' => false, 'status' => 'UNSUPPORTED_PAYMENT_PROVIDER', 'message' => 'Unsupported payment provider.'];
        }

        $result = DB::transaction(function () use ($order, $paymentData, $actorUser, $channel, $webhookEventId, $webhookAttempt, $provider) {
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

            // Payment methods are isolated. Manual bank evidence can never settle a Paystack order,
            // and Paystack callbacks can never settle a manual-transfer order.
            if ($provider === 'manual_bank_transfer' && $lockedOrder->payment_method !== 'manual_bank_transfer') {
                return ['success' => false, 'status' => 'PAYMENT_METHOD_MISMATCH', 'message' => 'Manual bank transfer cannot settle an order assigned to another payment method.'];
            }
            if ($provider === 'paystack' && $lockedOrder->payment_method === 'manual_bank_transfer') {
                return ['success' => false, 'status' => 'PAYMENT_METHOD_MISMATCH', 'message' => 'Paystack cannot settle a manual bank transfer order.'];
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

            // A manual bank transfer is a one-shot tender: once settled, a second approval
            // is never treated as a harmless replay and cannot create another payment.
            if ($provider === 'manual_bank_transfer' && $lockedOrder->payment_status === 'paid') {
                return ['success' => false, 'status' => 'ALREADY_PAID', 'message' => 'This order already has a settled payment; another manual payment is forbidden.'];
            }

            // 2. Paid-order replay guard. Validate gateway evidence BEFORE returning
            // ALREADY_PAID: a second successful capture must not be mistaken for a replay.
            if ($lockedOrder->payment_status === 'paid') {
                $paidStatus = strtolower((string) ($paymentData['status'] ?? ''));
                $paidCurrency = strtoupper((string) ($paymentData['currency'] ?? ''));
                $paidReference = (string) ($paymentData['reference'] ?? '');
                $paidAmountRaw = $paymentData['amount'] ?? null;
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

                $incomingTransactionId = trim((string) ($paymentData['id'] ?? ''));
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
                            $paymentData['channel'] ?? null,
                        ], JSON_UNESCAPED_SLASHES) ?: '');
                    $exceptionKey = 'PAYSTACK-DUP-CAP-' . hash('sha256', $lockedOrder->id . '|' . $exceptionIdentity);

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
                                'channel' => (string) ($paymentData['channel'] ?? ''),
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
            $txStatus = strtolower((string)($paymentData['status'] ?? ''));
            if ($txStatus !== 'success') {
                return [
                    'success' => false,
                    'status' => 'STATUS_NOT_SUCCESS',
                    'message' => "Gateway status '{$txStatus}' is not successful.",
                ];
            }

            // 4. Currency Invariant
            $currency = strtoupper((string)($paymentData['currency'] ?? ''));
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
            $actualKoboRaw = $paymentData['amount'] ?? null;
            $actualKoboIsInteger = is_int($actualKoboRaw)
                || (is_string($actualKoboRaw) && preg_match('/^\\d+$/', $actualKoboRaw) === 1);
            $actualKobo = $actualKoboIsInteger ? (int) $actualKoboRaw : -1;

            if (!$actualKoboIsInteger || $actualKobo !== $expectedKobo) {
                return [
                    'success' => false,
                    'status' => 'AMOUNT_MISMATCH',
                    'message' => "Mathematical amount mismatch: expected {$expectedKobo} kobo, gateway paid {$actualKobo} kobo.",
                ];
            }

            // 6. Reference Invariant
            $reference = (string)($paymentData['reference'] ?? '');
            if (empty($reference) || empty($lockedOrder->payment_reference) || !hash_equals((string)$lockedOrder->payment_reference, $reference)) {
                return [
                    'success' => false,
                    'status' => 'REFERENCE_MISMATCH',
                    'message' => "Payment reference mismatch: order expected '{$lockedOrder->payment_reference}', received '{$reference}'.",
                ];
            }

            // A provider/order reference is a single-order identity, not a reusable
            // lookup key. Enforce this across tenders even if an old database lacks a
            // unique index on the legacy orders.payment_reference column.
            if (Order::where('payment_reference', $reference)
                ->where('id', '!=', $lockedOrder->id)
                ->exists()) {
                return ['success' => false, 'status' => 'PAYMENT_REFERENCE_ALREADY_BOUND', 'message' => 'This payment reference is already bound to another order.'];
            }

            $normalizedTransactionId = trim((string) ($paymentData['id'] ?? ''));
            if ($provider === 'paystack'
                && $normalizedTransactionId !== ''
                && Order::where('paystack_transaction_id', $normalizedTransactionId)
                    ->where('id', '!=', $lockedOrder->id)
                    ->exists()) {
                return ['success' => false, 'status' => 'PAYMENT_TRANSACTION_ALREADY_APPLIED', 'message' => 'This Paystack transaction ID has already been applied to another order.'];
            }

            // 7. Customer Identity Invariant
            $payerEmail = strtolower(trim((string)($paymentData['customer']['email'] ?? '')));
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
            // Manual bank transfers arriving after the order's expiry must never
            // revive the order or trigger supplier sourcing, even if the expiry worker
            // has not yet updated its state. Record the real credit and hold it for
            // the separate manual-refund review path.
            if ($provider === 'manual_bank_transfer'
                && (($lockedOrder->expires_at && $lockedOrder->expires_at->lte($now))
                    || in_array($lockedOrder->order_status, ['cancelled', 'canceled', 'failed', 'paid_stock_unavailable'], true))) {
                if (!in_array($lockedOrder->order_status, ['cancelled', 'canceled', 'failed', 'paid_stock_unavailable'], true)) {
                    $lockedOrder->order_status = 'cancelled';
                }
                $lockedOrder->vendor_settlement_status = 'refund_pending';
            }

            $lockedOrder->payment_status = 'paid';
            $lockedOrder->paid_at = $now;
            $lockedOrder->payment_channel = $provider === 'manual_bank_transfer'
                ? 'manual_bank_transfer'
                : (string)($paymentData['channel'] ?? 'paystack');
            // Direct bank transfers are recorded in their own table. Never forge a
            // Paystack transaction ID for a manual bank credit.
            $lockedOrder->paystack_transaction_id = $provider === 'manual_bank_transfer'
                ? null
                : (string)($paymentData['id'] ?? $reference);

            if ($lockedOrder->order_status === 'cancelled') {
                // The order was previously cancelled by the expiration worker.
                // Verify whether stock can be re-reserved atomically.
                // A retailer order that expired/cancelled before payment must not be
                // silently resurrected after a late charge. Keep its funds held for
                // an authorized refund-review decision under the binding recovery policy.
                $canReinstate = $provider !== 'manual_bank_transfer' && !$lockedOrder->retailer_model_order && $lockedOrder->items->isNotEmpty();
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
                // verified supplier offer as the stock signal. Their legacy inventory_quantity is not an authoritative quantity counter for supplier-backed
                // listings; the supplier's post-payment quantity confirmation is authoritative.
                // Do not turn that sentinel into a false post-payment stockout/refund hold.
                foreach ($lockedOrder->items as $item) {
                    if ($item->product_id) {
                        $prod = Product::where('id', $item->product_id)->lockForUpdate()->first();
                        if (!$prod || !isset($prod->inventory_quantity)) {
                            continue;
                        }

                        $supplierSourcedCanonicalProduct = $lockedOrder->retailer_model_order
                            && $prod->vendor_id === null
                            && $prod->shop_id === null
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
                    'description' => $provider === 'manual_bank_transfer'
                        ? "Verified manual bank transfer credit held for refund review for Order #{$lockedOrder->order_number}"
                        : "Paystack gateway capture for cancelled/out-of-stock Order #{$lockedOrder->order_number}",
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

                // Record the refund obligation atomically with the payment/liability.
                // Direct bank transfers must never be represented as Paystack gateway
                // refunds. They receive a separate manual-review case and no automated
                // or gateway refund instruction.
                $latePaymentRefundReason = "Refund pending administrative authorization for cancelled/depleted Order #{$lockedOrder->order_number}";
                if ($provider === 'manual_bank_transfer') {
                    $manualPayment = \App\Models\ManualBankTransferPayment::where('order_id', $lockedOrder->id)
                        ->lockForUpdate()
                        ->first();
                    if (!$manualPayment || $manualPayment->status !== 'pending_review') {
                        throw new \RuntimeException('Manual bank transfer refund hold requires its unique pending receipt-review record.');
                    }

                    $refundCase = \App\Models\ManualBankTransferRefundCase::firstOrCreate(
                        ['order_id' => $lockedOrder->id],
                        [
                            'manual_bank_transfer_payment_id' => $manualPayment->id,
                            'idempotency_key' => 'MANUAL-BANK-REFUND-' . $lockedOrder->id,
                            'amount_kobo' => $actualKobo,
                            'currency' => 'NGN',
                            'status' => 'pending_admin_review',
                            'reason' => $latePaymentRefundReason,
                            'created_by_admin_id' => $actorUser?->id,
                        ]
                    );

                    if ((int) $refundCase->manual_bank_transfer_payment_id !== (int) $manualPayment->id
                        || (int) $refundCase->amount_kobo !== $actualKobo
                        || $refundCase->currency !== 'NGN') {
                        throw new \RuntimeException('Existing manual bank transfer refund case does not match the verified credit.');
                    }
                } else {
                    app(\App\Services\FinancialOperationService::class)->createRefundOperation(
                        $lockedOrder,
                        (string) $lockedOrder->grand_total,
                        $latePaymentRefundReason,
                        null,
                        'PAYSTACK-LATE-REFUND-' . $lockedOrder->id,
                        ['type' => 'pending_admin_review_late_payment']
                    );
                }

                // Invariant: Customer cashback balance is LEFT UNTOUCHED (zero lots consumed)
                // Invariant: ZERO new cashback reward is accrued for unfulfilled orders
                // Invariant: ZERO merchant, logistics, or platform cut is distributed

                // Release any pre-payment cashback reservation
                \App\Models\CashbackReservation::where('order_id', $lockedOrder->id)
                    ->where('status', 'reserved')
                    ->update(['status' => 'released']);

                AdminAuditLog::create([
                    'user_id' => $lockedOrder->customer_id,
                    'action' => $provider === 'manual_bank_transfer' ? 'manual_bank_transfer_settlement_refund_pending_recorded' : 'paystack_settlement_refund_pending_recorded',
                    'target_type' => Order::class,
                    'target_id' => $lockedOrder->id,
                    'old_values' => ['order_status' => 'cancelled', 'payment_status' => $oldPaymentStatus],
                    'new_values' => [
                        'order_status' => 'cancelled',
                        'payment_status' => 'paid',
                        'vendor_settlement_status' => 'refund_pending',
                        'refund_liability_amount' => $lockedOrder->grand_total,
                        'paystack_refund_reference' => $provider === 'paystack' ? $lockedOrder->paystack_refund_reference : null,
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent(),
                ]);

                return [
                    'success' => true,
                    'status' => 'REFUND_PENDING',
                    'is_refund_only' => true,
                    'message' => 'Order was cancelled before payment and inventory is unavailable. The full captured amount is held as customer refund liability, with a pending admin-review request; no automatic refund is dispatched.',
                    'order' => $lockedOrder,
                    'trigger_gateway_refund' => $provider === 'paystack',
                    'requires_manual_refund_review' => $provider === 'manual_bank_transfer',
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

                    if ($provider !== 'manual_bank_transfer' && BcMathService::comp((string) $lockedOrder->customer_cashback_5, '0.00') > 0) {
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
                if ($provider !== 'manual_bank_transfer' && bccomp((string)$lockedOrder->customer_cashback_5, '0.00', 2) > 0) {
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
                if ($provider !== 'manual_bank_transfer' && $lockedOrder->customer_id && bccomp((string)$lockedOrder->customer_cashback_5, '0.00', 2) > 0) {
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
                'action' => $provider === 'manual_bank_transfer' ? 'manual_bank_transfer_payment_verified' : 'paystack_payment_verified',
                'target_type' => Order::class,
                'target_id' => $lockedOrder->id,
                'old_values' => [
                    'payment_status' => $oldPaymentStatus,
                ],
                'new_values' => [
                    'payment_status' => 'paid',
                    'payment_channel' => $lockedOrder->payment_channel,
                    'payment_reference' => $reference,
                    'bank_transaction_reference' => $provider === 'manual_bank_transfer' ? (string) ($paymentData['id'] ?? '') : null,
                    'paystack_reference' => $provider === 'paystack' ? $reference : null,
                    'paystack_transaction_id' => $provider === 'paystack' ? $lockedOrder->paystack_transaction_id : null,
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

        // Late-payment refund obligations are created inside the settlement transaction.
        // A committed customer refund liability must never lack its admin-review operation.
        return $result;
    }
    /**
     * Handshake 1A: Vendor signals courier arrival / handover readiness at branch counter.
     * Sets vendor_handover_ready_at, updates order status to ready_for_pickup,
     * and reveals the secret 6-digit pickup_verification_code to the vendor.
     */
    public function markVendorHandoverReady(Order $order, $merchantUser = null): array
    {
        return DB::transaction(function () use ($order, $merchantUser) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            // New retailer orders require each supplier assignment to be prepared
            // independently. The legacy order-level shortcut must never bypass this.
            if ($lockedOrder->retailer_model_order
                && \App\Models\OrderSupplierAssignment::where('order_id', $lockedOrder->id)->exists()) {
                return [
                    'success' => false,
                    'message' => 'Retailer orders require supplier-specific collection readiness. Use the assigned supplier order workflow.',
                ];
            }

            // Authorization & Branch Isolation Guard for Merchant Owner / Counter Staff
            if ($merchantUser) {
                if ($merchantUser->status !== 'active') {
                    return ['success' => false, 'message' => 'Unauthorized: User account is inactive.'];
                }

                if ($merchantUser->isVendorEmployee()) {
                    $employee = $merchantUser->vendorEmployeeProfile;
                    if (!$employee || !$employee->is_active || !$employee->canAccessBranch($lockedOrder->shop_id)) {
                        return ['success' => false, 'message' => 'Unauthorized: Branch isolation prevents access to this order.'];
                    }
                } elseif ($merchantUser->isVendor()) {
                    $vendor = $merchantUser->vendorProfile;
                    if (!$vendor || (int)$vendor->id !== (int)$lockedOrder->vendor_id) {
                        return ['success' => false, 'message' => 'Unauthorized: Merchant ownership barrier prevents access to this order.'];
                    }
                    if ($vendor->marketplace_status !== 'active') {
                        return ['success' => false, 'message' => 'Unauthorized: Merchant marketplace account is suspended.'];
                    }
                }
            }

            if ($lockedOrder->fulfillment_type !== 'doorstep_delivery') {
                return [
                    'success' => false,
                    'message' => 'Courier handover staging is only for doorstep delivery orders. In-shop pickup orders are staged for customer counter collection.',
                ];
            }

            if ($lockedOrder->payment_status !== 'paid') {
                return [
                    'success' => false,
                    'message' => 'Only paid orders can be staged for courier handover.',
                ];
            }

            if ($lockedOrder->retailer_model_order && $lockedOrder->sourcing_status !== 'supplier_confirmed') {
                return [
                    'success' => false,
                    'message' => 'Courier handover is blocked until every assigned supplier has confirmed availability.',
                ];
            }

            if (!in_array($lockedOrder->order_status, ['confirmed', 'processing', 'ready_for_dispatch', 'ready_for_pickup'])) {
                return [
                    'success' => false,
                    'message' => "Order cannot be staged for handover in status {$lockedOrder->order_status}.",
                ];
            }

            $lockedOrder->vendor_handover_ready_at = Carbon::now();
            $lockedOrder->order_status = 'ready_for_pickup';
            $lockedOrder->pickup_failed_attempts = 0;
            $lockedOrder->pickup_locked_until = null;
            $lockedOrder->save();

            // Dispatch handover authorized alert with OTP to merchant counter & alert assigned courier
            NotificationDispatchService::dispatchVendorHandoverReady($lockedOrder);

            return [
                'success' => true,
                'message' => 'Handover authorized! Show the 6-digit pickup code to the courier at the counter.',
                'pickup_otp' => $lockedOrder->pickup_verification_code,
                'order' => $lockedOrder,
            ];
        });
    }

    /**
     * Handshake 1B: Rider collects package from Vendor.
     * Courier inputs the secret 6-digit pickup_verification_code on their own phone,
     * with optional package condition photo proof.
     * Enforces strict authorization, vendor readiness, non-collision, and timed lockout protection.
     */
    public function verifyRiderPickupOtp(
        Order $order,
        string $otp,
        RiderProfile $rider,
        ?string $proofImagePath = null,
        ?string $pickupNotes = null
    ): array {
        return DB::transaction(function () use ($order, $otp, $rider, $proofImagePath, $pickupNotes) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder) {
                return ['success' => false, 'message' => 'Order record not found under lock.'];
            }

            if ($lockedOrder->fulfillment_type !== 'doorstep_delivery') {
                return ['success' => false, 'message' => 'Rider courier pickup is only applicable to doorstep delivery orders. In-shop pickup orders are collected directly at the shop counter.'];
            }

            // Payment is a prerequisite for physical custody. Never let legacy status
            // aliases or stale handover fields authorize dispatch of an unpaid order.
            if ($lockedOrder->payment_status !== 'paid') {
                return ['success' => false, 'message' => 'Unpaid orders cannot be handed to a rider.'];
            }

            // Enforce the supplier-rejection hold at the custody boundary too.
            // Controller checks are not enough: this service can be called by other
            // API/web flows and a valid-looking stale pickup OTP must not dispatch
            // a paid order whose sourcing has been administratively frozen.
            if ($lockedOrder->retailer_model_order && $lockedOrder->sourcing_status === 'supplier_rejected') {
                return ['success' => false, 'message' => 'Rider collection is blocked while this retailer order is on supplier-rejection hold. An administrator must resolve sourcing first.'];
            }

            if (!in_array($lockedOrder->order_status, ['confirmed', 'processing', 'ready_for_dispatch', 'ready_for_pickup'])) {
                return ['success' => false, 'message' => "Order cannot be picked up in status {$lockedOrder->order_status}."];
            }

            // Strict 6-Digit Format Validation
            if (!preg_match('/^[0-9]{6}$/', trim($otp))) {
                return ['success' => false, 'message' => 'Pickup verification code must be exactly 6 digits.'];
            }

            // New retailer deliveries use central logistics. Supplier/vendor association
            // is private sourcing metadata and never authorizes physical custody.
            if (!$rider->isEligibleForCentralOrder($lockedOrder)) {
                return ['success' => false, 'message' => 'Unauthorized: Rider must be active and linked exclusively to the active logistics company assigned to this order.'];
            }

            // New retailer orders may contain packages from several private suppliers.
            // Each supplier has its own one-time collection OTP and mandatory proof photo.
            if ($lockedOrder->retailer_model_order
                && \App\Models\OrderSupplierAssignment::where('order_id', $lockedOrder->id)->exists()) {
                if (empty($proofImagePath) || trim($proofImagePath) === '') {
                    return ['success' => false, 'message' => 'Mandatory courier pickup photo proof is required to verify handover collection.'];
                }
                if ($lockedOrder->order_status !== 'ready_for_pickup') {
                    return ['success' => false, 'message' => 'All supplier packages must be marked ready before the rider starts the complete-order collection.'];
                }
                if ($lockedOrder->isPickupLocked()) {
                    $minutesRemaining = max(1, Carbon::now()->diffInMinutes($lockedOrder->pickup_locked_until));
                    return ['success' => false, 'message' => "Pickup verification is locked. Try again in {$minutesRemaining} minute(s)."];
                }

                $assignment = \App\Models\OrderSupplierAssignment::where('order_id', $lockedOrder->id)
                    ->where('supplier_pickup_code', trim($otp))
                    ->lockForUpdate()
                    ->first();

                if ($assignment && $assignment->status === 'collected') {
                    return [
                        'success' => true,
                        'message' => 'This supplier package was already collected and sealed.',
                        'remaining_supplier_packages' => \App\Models\OrderSupplierAssignment::where('order_id', $lockedOrder->id)
                            ->whereNotIn('status', ['collected', 'admin_reassigned'])
                            ->count(),
                    ];
                }

                if (!$assignment || $assignment->status !== 'ready_for_collection') {
                    $lockedOrder->pickup_failed_attempts = (int) $lockedOrder->pickup_failed_attempts + 1;
                    if ($lockedOrder->pickup_failed_attempts >= 5) {
                        $lockedOrder->pickup_locked_until = Carbon::now()->addMinutes(15);
                        $lockedOrder->save();
                        return ['success' => false, 'message' => 'Pickup verification locked for 15 minutes after 5 incorrect attempts.'];
                    }
                    $lockedOrder->save();
                    return ['success' => false, 'message' => 'Invalid supplier collection OTP. Verify the code for this supplier package.'];
                }

                $assignment->status = 'collected';
                $assignment->collected_at = Carbon::now();
                $assignment->collection_proof_image_path = $proofImagePath;
                $assignment->collection_notes = $pickupNotes;
                $assignment->save();

                $lockedOrder->pickup_failed_attempts = 0;
                $lockedOrder->pickup_locked_until = null;
                $allCollected = !\App\Models\OrderSupplierAssignment::where('order_id', $lockedOrder->id)
                    ->whereNotIn('status', ['collected', 'admin_reassigned'])
                    ->exists();

                if ($allCollected) {
                    $lockedOrder->order_status = 'out_for_delivery';
                    $lockedOrder->pickup_confirmed_by_rider = true;
                    $lockedOrder->picked_up_at = Carbon::now();
                    $lockedOrder->pickup_proof_image = $proofImagePath;
                    if ($pickupNotes) {
                        $lockedOrder->pickup_notes = $pickupNotes;
                    }
                    $lockedOrder->save();
                    NotificationDispatchService::dispatchRiderPickedUp($lockedOrder);

                    return [
                        'success' => true,
                        'message' => 'All supplier packages have been collected with separate OTP and photo proof. Order is out for delivery.',
                        'order' => $lockedOrder,
                    ];
                }

                $lockedOrder->save();

                return [
                    'success' => true,
                    'message' => 'Supplier package collection verified. Continue to the remaining supplier collection point(s).',
                    'order' => $lockedOrder,
                    'remaining_supplier_packages' => \App\Models\OrderSupplierAssignment::where('order_id', $lockedOrder->id)
                        ->whereNotIn('status', ['collected', 'admin_reassigned'])
                        ->count(),
                ];
            }

            // Enforce Vendor Handover Readiness
            if (!$lockedOrder->isVendorHandoverReady() && $lockedOrder->order_status !== 'ready_for_pickup') {
                return [
                    'success' => false,
                    'message' => 'Merchant handover has not been authorized yet. Please ask merchant counter staff to click "Handover Ready" on their screen.',
                ];
            }

            // Timed Lockout Protection: Check if pickup is currently locked
            if ($lockedOrder->isPickupLocked()) {
                $minutesRemaining = max(1, Carbon::now()->diffInMinutes($lockedOrder->pickup_locked_until));
                return [
                    'success' => false,
                    'message' => "Pickup verification locked due to excessive failed attempts. Try again in {$minutesRemaining} minute(s) or request merchant re-authorization at counter.",
                ];
            }

            // Mathematical Equality Check (Constant-time hash comparison prevents side-channel leaks)
            if (!hash_equals((string)$lockedOrder->pickup_verification_code, trim($otp))) {
                $lockedOrder->increment('pickup_failed_attempts');
                if ($lockedOrder->pickup_failed_attempts >= 5) {
                    $lockedOrder->pickup_locked_until = Carbon::now()->addMinutes(15);
                    $lockedOrder->save();
                    return [
                        'success' => false,
                        'message' => 'Pickup verification temporarily locked for 15 minutes due to 5 consecutive incorrect attempts. Merchant can re-authorize at counter.',
                    ];
                }
                return [
                    'success' => false,
                    'message' => 'Invalid merchant pickup verification OTP. Please verify with counter staff.',
                ];
            }

            // Reset failed attempts & lockout upon successful authentication
            $lockedOrder->pickup_failed_attempts = 0;
            $lockedOrder->pickup_locked_until = null;
            $lockedOrder->order_status = 'out_for_delivery';
            $lockedOrder->pickup_confirmed_by_rider = true;
            $lockedOrder->picked_up_at = Carbon::now();

            if ($proofImagePath) {
                $lockedOrder->pickup_proof_image = $proofImagePath;
            }

            if ($pickupNotes) {
                $lockedOrder->pickup_notes = $pickupNotes;
            }

            $lockedOrder->save();

            // Multi-channel alert to customer and vendor
            NotificationDispatchService::dispatchRiderPickedUp($lockedOrder);

            return [
                'success' => true,
                'message' => 'Pickup verified successfully. Order is out for delivery.',
                'order' => $lockedOrder,
            ];
        });
    }

    /**
     * Handshake 2A: Rider arrives at Customer Doorstep.
     * Records arrival time and signals customer phone to open verification prompt.
     */
    public function markRiderArrived(Order $order, RiderProfile $rider): array
    {
        return DB::transaction(function () use ($order, $rider) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if ($lockedOrder->fulfillment_type !== 'doorstep_delivery') {
                return ['success' => false, 'message' => 'Rider doorstep arrival is only applicable to doorstep delivery orders.'];
            }

            if ($lockedOrder->order_status !== 'out_for_delivery') {
                return [
                    'success' => false,
                    'message' => "Order cannot be marked arrived in status {$lockedOrder->order_status}.",
                ];
            }

            // Arrival requires the exact centrally assigned active logistics rider.
            if (!$rider->isEligibleForCentralOrder($lockedOrder)) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized: Only the active rider assigned through this order active logistics company may mark arrival.',
                ];
            }

            $lockedOrder->rider_arrived_at = Carbon::now();
            $lockedOrder->save();

            // Multi-channel SMS & WhatsApp alert to customer with secret OTP
            NotificationDispatchService::dispatchRiderArrived($lockedOrder);

            return [
                'success' => true,
                'message' => 'Arrival signaled! Handshake prompt is now active on the customer\'s phone.',
                'order' => $lockedOrder,
            ];
        });
    }

    /**
     * Handshake 2B: Customer enters 6-digit PIN on their own device with optional photo snap.
     * Verifies verification_code, sets delivered, enqueues cashback, and credits rider wallet.
     */
    public function verifyCustomerDeliveryOtp(
        Order $order,
        string $otp,
        ?RiderProfile $rider = null,
        ?string $proofImagePath = null,
        ?string $customerNotes = null
    ): array {
        return DB::transaction(function () use ($order, $otp, $rider, $proofImagePath, $customerNotes) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder) {
                return ['success' => false, 'message' => 'Order record not found under lock.'];
            }

            if ($lockedOrder->fulfillment_type !== 'doorstep_delivery') {
                return ['success' => false, 'message' => 'Customer doorstep verification is only for doorstep delivery orders.'];
            }

            // A valid customer OTP proves receipt, not payment. Payment must be verified
            // independently before the terminal fulfilment transition or cashback enqueue.
            if ($lockedOrder->payment_status !== 'paid') {
                return ['success' => false, 'message' => 'Unpaid orders cannot be confirmed as delivered.'];
            }

            if ($lockedOrder->order_status !== 'out_for_delivery') {
                return ['success' => false, 'message' => 'Order is not currently out for delivery.'];
            }

            // Strict 6-Digit Format Validation
            if (!preg_match('/^[0-9]{6}$/', trim($otp))) {
                return ['success' => false, 'message' => 'Delivery verification code must be exactly 6 digits.'];
            }

            // Lockout Protection: Check if delivery verification is currently locked
            if ($lockedOrder->isDeliveryLocked()) {
                $minutesRemaining = max(1, Carbon::now()->diffInMinutes($lockedOrder->delivery_locked_until));
                return [
                    'success' => false,
                    'message' => "Delivery verification locked due to excessive failed attempts. Try again in {$minutesRemaining} minute(s).",
                ];
            }

            // 1. Central logistics rider hardening. Direct service calls must enforce
            // the same exact assignment as the controller; no vendor fallback is allowed.
            $activeRider = $rider ?? ($lockedOrder->rider_id
                ? RiderProfile::with(['user', 'logisticsCompany.user'])->find($lockedOrder->rider_id)
                : null);
            $haltReason = null;
            if (!$activeRider) {
                $haltReason = 'Unauthorized: No assigned central logistics rider is available for delivery confirmation.';
            } elseif (!$activeRider->isEligibleForCentralOrder($lockedOrder)) {
                $haltReason = 'Unauthorized: Delivery confirmation requires the assigned active rider linked exclusively to the order active logistics company.';
            }

            if ($haltReason) {
                $lockedOrder->delivery_halted_reason = $haltReason;
                $lockedOrder->delivery_halted_at = Carbon::now();
                $lockedOrder->save();

                AdminAuditLog::create([
                    'user_id' => $activeRider ? $activeRider->user_id : ($lockedOrder->customer_id ?? null),
                    'action' => 'delivery_handshake_halted_entity_inactive',
                    'target_type' => Order::class,
                    'target_id' => $lockedOrder->id,
                    'old_values' => ['order_status' => $lockedOrder->order_status],
                    'new_values' => [
                        'order_number' => $lockedOrder->order_number,
                        'delivery_halted_reason' => $haltReason,
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent() ?? 'System Settlement Worker',
                ]);

                return ['success' => false, 'message' => $haltReason];
            }

            if (!hash_equals((string)$lockedOrder->verification_code, trim($otp))) {
                $lockedOrder->increment('delivery_failed_attempts');
                if ($lockedOrder->delivery_failed_attempts >= 5) {
                    $lockedOrder->delivery_locked_until = Carbon::now()->addMinutes(15);
                    $lockedOrder->save();
                    return [
                        'success' => false,
                        'message' => 'Delivery verification temporarily locked for 15 minutes due to 5 consecutive incorrect attempts.',
                    ];
                }
                return ['success' => false, 'message' => 'Invalid customer delivery verification OTP. Please check your phone SMS or screen.'];
            }

            // The customer OTP alone is not sufficient evidence of doorstep handover.
            // Every route into this service must supply server-stored photo proof before
            // changing order state, releasing any fulfilment transition, or enqueueing cashback.
            if (!is_string($proofImagePath) || trim($proofImagePath) === '') {
                return ['success' => false, 'message' => 'Doorstep photo proof is required before delivery can be confirmed.'];
            }

            $lockedOrder->delivery_failed_attempts = 0;
            $lockedOrder->delivery_locked_until = null;

            $now = Carbon::now();
            $refundHours = max(1, (int) SystemSetting::get('refund_window_hours', 24));
            $lockedOrder->order_status = 'delivered';
            $lockedOrder->delivery_confirmed_by_customer = true;
            $lockedOrder->received_at = $now;
            $lockedOrder->refund_window_expires_at = $now->copy()->addHours($refundHours);
            $lockedOrder->vendor_settlement_status = 'held';

            if ($proofImagePath) {
                $lockedOrder->proof_of_delivery_image = $proofImagePath;
            }

            if ($customerNotes) {
                $lockedOrder->customer_delivery_notes = $customerNotes;
            }

            if (!$lockedOrder->rider_id && $rider) {
                $lockedOrder->rider_id = $rider->id;
            }

            $lockedOrder->save();

            if ($lockedOrder->retailer_model_order) {
                app(\App\Services\LogisticsCompanyPayableService::class)->releaseAfterVerifiedDelivery($lockedOrder);
            }

            // Do not credit a logistics-company or rider wallet from a delivery event.
            // Vendor delivery earnings and the 90/10 fee split must be sourced from the
            // immutable parent/child allocation ledger and released only by vendor settlement
            // after the protection window, dispute/return checks, and payout-security gates.

            // Update/Enqueue 5% Customer Cashback reward with exact refund window maturation lock
            CustomerCashbackLedger::updateOrCreate([
                'order_id' => $lockedOrder->id,
            ], [
                'customer_id' => $lockedOrder->customer_id,
                'amount' => $lockedOrder->customer_cashback_5,
                'status' => 'pending_maturation',
                'matures_at' => $lockedOrder->refund_window_expires_at->copy()->addHours(24),
                'expires_at' => $lockedOrder->refund_window_expires_at->copy()->addHours(24)->addDays(365),
            ]);

            // Multi-channel notifications to Logistics Fleet, Customer, and Vendor
            NotificationDispatchService::dispatchOrderDelivered($lockedOrder);

            return [
                'success' => true,
                'message' => "Package delivery confirmed successfully! {$refundHours}-hour return window is now active.",
                'order' => $lockedOrder,
            ];
        });
    }

    /**
     * Handshake 3: In-Shop Physical Inspection Sign-Off.
     * Store employee marks reservation inspected and accepted by customer.
     */
    public function approvePickupInspection(PickupReservation $reservation, User $staffUser): array
    {
        if ($staffUser->status !== 'active') {
            return ['success' => false, 'message' => 'Unauthorized: User account is inactive.'];
        }

        return DB::transaction(function () use ($reservation, $staffUser): array {
            // Serialize inspection against customer cancellation, expiry, and order creation.
            $lockedReservation = PickupReservation::whereKey($reservation->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedReservation) {
                return ['success' => false, 'message' => 'Reservation no longer exists.'];
            }

            if ($staffUser->isVendorEmployee()) {
                $employee = $staffUser->vendorEmployeeProfile;
                if (!$employee || !$employee->is_active || !$employee->canAccessBranch($lockedReservation->shop_id)) {
                    return ['success' => false, 'message' => 'Unauthorized: Branch isolation prevents access to this shop.'];
                }
            } elseif ($staffUser->isVendor()) {
                $vendor = $staffUser->vendorProfile;
                if (!$vendor || (int) $vendor->id !== (int) $lockedReservation->vendor_id) {
                    return ['success' => false, 'message' => 'Unauthorized: Merchant ownership mismatch.'];
                }
                if ($vendor->marketplace_status !== 'active') {
                    return ['success' => false, 'message' => 'Merchant marketplace account is suspended.'];
                }
            } elseif (!$staffUser->isSuperAdmin() && $staffUser->user_type !== 'admin_employee') {
                return ['success' => false, 'message' => 'Unauthorized: Only authorized shop staff can inspect reservations.'];
            }

            if ($lockedReservation->status !== 'reserved') {
                return ['success' => false, 'message' => "Reservation cannot be inspected in status {$lockedReservation->status}."];
            }

            if (!$lockedReservation->expires_at || $lockedReservation->isExpired()) {
                $lockedReservation->status = 'expired';
                $lockedReservation->save();
                return ['success' => false, 'message' => 'This reservation has expired.'];
            }

            $lockedReservation->status = 'inspected_accepted';
            $lockedReservation->inspected_at = Carbon::now();
            $lockedReservation->inspected_by_staff_id = $staffUser->id;
            $lockedReservation->save();

            return [
                'success' => true,
                'message' => 'Inspection approved. Customer digital payment is now unlocked.',
                'reservation' => $lockedReservation,
            ];
        });
    }

    /**
     * Handshake 4: In-Shop Counter Handover OTP Verification.
     * Goods released to customer after verified digital payment.
     */
    public function verifyPickupHandoverOtp(
        Order $order,
        string $otp,
        User $staffUser,
        string $reservationCode,
        string $proofImagePath
    ): array {
        if ($staffUser->status !== 'active') {
            return ['success' => false, 'message' => 'Unauthorized: User account is inactive.'];
        }
        if ($staffUser->isVendorEmployee()) {
            $employee = $staffUser->vendorEmployeeProfile;
            if (!$employee || !$employee->canAccessBranch($order->shop_id)) {
                return ['success' => false, 'message' => 'Unauthorized: Branch isolation prevents access to this shop.'];
            }
        } elseif ($staffUser->isVendor()) {
            $vendor = $staffUser->vendorProfile;
            if (!$vendor || (int)$vendor->id !== (int)$order->vendor_id) {
                return ['success' => false, 'message' => 'Unauthorized: Merchant ownership barrier prevents access to this order.'];
            }
        } else {
            return ['success' => false, 'message' => 'Unauthorized: Only this shop’s active merchant or branch staff can release in-shop pickup orders.'];
        }

        if (!preg_match('/^[0-9]{6}$/', trim($otp))) {
            return ['success' => false, 'message' => 'Handover verification code must be exactly 6 digits.'];
        }
        if (trim($reservationCode) === '' || trim($proofImagePath) === '') {
            return ['success' => false, 'message' => 'The customer reservation pass and pickup photo evidence are required.'];
        }

        return DB::transaction(function () use ($order, $otp, $staffUser, $reservationCode, $proofImagePath) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->fulfillment_type !== 'in_shop_pickup') {
                return ['success' => false, 'message' => 'This is not an in-shop pickup order.'];
            }
            if ($lockedOrder->payment_status !== 'paid') {
                return ['success' => false, 'message' => 'Goods cannot be handed over before verified payment.'];
            }
            if (in_array($lockedOrder->order_status, ['picked_up', 'delivered', 'cancelled'], true)
                || !empty($lockedOrder->picked_up_at)) {
                return ['success' => false, 'message' => 'This pickup has already been completed or cancelled; the customer OTP cannot be reused.'];
            }

            $reservation = PickupReservation::where('order_id', $lockedOrder->id)
                ->where('customer_id', $lockedOrder->customer_id)
                ->where('vendor_id', $lockedOrder->vendor_id)
                ->where('shop_id', $lockedOrder->shop_id)
                ->lockForUpdate()
                ->first();

            if (!$reservation || !hash_equals((string) $reservation->reservation_code, strtoupper(trim($reservationCode)))) {
                return ['success' => false, 'message' => 'Reservation pass does not match this order and branch.'];
            }
            if ($reservation->status !== 'payment_completed') {
                return ['success' => false, 'message' => 'Reservation is not in a paid, eligible handover state.'];
            }

            if ($lockedOrder->isPickupLocked()) {
                $minutesRemaining = max(1, Carbon::now()->diffInMinutes($lockedOrder->pickup_locked_until));
                return [
                    'success' => false,
                    'message' => "Handover verification locked due to excessive failed attempts. Try again in {$minutesRemaining} minute(s).",
                ];
            }

            // verification_code is the customer-facing pickup OTP; pickup_verification_code is
            // the separate vendor-to-rider custody OTP and is never accepted for customer pickup.
            if (empty($lockedOrder->verification_code)
                || !hash_equals((string) $lockedOrder->verification_code, trim($otp))) {
                $lockedOrder->increment('pickup_failed_attempts');
                if ($lockedOrder->pickup_failed_attempts >= 5) {
                    $lockedOrder->pickup_locked_until = Carbon::now()->addMinutes(15);
                    $lockedOrder->save();
                    return [
                        'success' => false,
                        'message' => 'Handover verification temporarily locked for 15 minutes due to 5 consecutive incorrect attempts.',
                    ];
                }
                return ['success' => false, 'message' => 'Invalid customer pickup OTP.'];
            }

            $lockedOrder->pickup_failed_attempts = 0;
            $lockedOrder->pickup_locked_until = null;
            $previousOrderStatus = $lockedOrder->order_status;
            $now = Carbon::now();
            $refundHours = max(1, (int) SystemSetting::get('refund_window_hours', 24));
            $lockedOrder->order_status = 'picked_up';
            $lockedOrder->received_at = $now;
            $lockedOrder->picked_up_at = $now;
            $lockedOrder->customer_pickup_photo_path = $proofImagePath;
            $lockedOrder->verification_code = null; // one-time customer pickup OTP
            $lockedOrder->refund_window_expires_at = $now->copy()->addHours($refundHours);
            $lockedOrder->vendor_settlement_status = 'held';
            $lockedOrder->save();

            $reservation->status = 'handed_over';
            $reservation->save();

            AdminAuditLog::create([
                'user_id' => $staffUser->id,
                'action' => 'in_shop_customer_pickup_verified',
                'target_type' => Order::class,
                'target_id' => $lockedOrder->id,
                'old_values' => [
                    'order_status' => $previousOrderStatus,
                    'reservation_status' => 'payment_completed',
                ],
                'new_values' => [
                    'order_status' => 'picked_up',
                    'reservation_status' => 'handed_over',
                    'reservation_id' => $reservation->id,
                    'reservation_code_suffix' => substr((string) $reservation->reservation_code, -4),
                    'customer_pickup_photo_path' => $proofImagePath,
                    'customer_otp_consumed' => true,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            CustomerCashbackLedger::updateOrCreate([
                'order_id' => $lockedOrder->id,
            ], [
                'customer_id' => $lockedOrder->customer_id,
                'amount' => $lockedOrder->customer_cashback_5,
                'status' => 'pending_maturation',
                'matures_at' => $lockedOrder->refund_window_expires_at->copy()->addHours(24),
                'expires_at' => $lockedOrder->refund_window_expires_at->copy()->addHours(24)->addDays(365),
            ]);

            return [
                'success' => true,
                'message' => "Goods handed over successfully. {$refundHours}-hour return window has commenced.",
                'order' => $lockedOrder,
            ];
        });
    }

    /**
     * Evaluates order post-receipt maturation (called via scheduler/cron).
     * Transitions vendor settlement to 'eligible' and cashback to 'available' if configured refund window expired with zero disputes.
     */
    public function evaluateMaturation(Order $order): void
    {
        if (!$order->isFulfilled() || !$order->isReturnWindowExpired()) {
            return;
        }

        $shouldMatureCashback = false;
        DB::transaction(function () use ($order, &$shouldMatureCashback) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
            if (!$lockedOrder) {
                return;
            }

            $hasDispute = $lockedOrder->dispute()->whereIn('status', ['filed', 'under_review'])->exists();
            $hasReturnCase = $lockedOrder->returnCases()->whereNotIn('status', ['resolved', 'refunded', 'replacement_completed', 'rejected', 'closed'])->exists();

            if ($hasDispute || $hasReturnCase) {
                // Preserve legacy quarantine semantics for historical vendor settlement.
                // Retailer cashback remains pending so it can mature after the case is resolved.
                if (!$lockedOrder->retailer_model_order) {
                    $lockedOrder->vendor_settlement_status = 'disputed';
                    $lockedOrder->save();
                    CustomerCashbackLedger::where('order_id', $lockedOrder->id)->update([
                        'status' => 'quarantined_dispute',
                    ]);
                }
                return;
            }

            if (!$lockedOrder->retailer_model_order && $lockedOrder->vendor_settlement_status === 'held') {
                $lockedOrder->vendor_settlement_status = 'eligible';
                $lockedOrder->save();
            }
            $shouldMatureCashback = true;
        });

        if ($shouldMatureCashback) {
            app(CashbackMaturityService::class)->matureOrder($order);
        }
    }

    /**
     * Super Admin Manual Settlement Disbursement with Mandatory Bank Transfer Proof.
     */
    public function disburseVendorSettlement(Order $order, User $adminUser, string $proofPath): array
    {
        return DB::transaction(function () use ($order, $adminUser, $proofPath) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            // Administrator Authorization & Status Check
            if ($adminUser->status !== 'active') {
                return ['success' => false, 'message' => 'Unauthorized: Administrator account is inactive.'];
            }

            if (!$adminUser->isSuperAdmin() && !$adminUser->isAdminEmployee()) {
                return ['success' => false, 'message' => 'Unauthorized: User lacks administrative financial disbursement privileges.'];
            }

            // Documented Bank Transfer Proof Validation
            if (empty(trim($proofPath))) {
                return ['success' => false, 'message' => 'Settlement disbursement requires documented bank transfer proof.'];
            }

            // Retailer-model supplier payables are item-level liabilities and must
            // never flow through the legacy order-level vendor payout (vendor_net_90).
            // A journal credit alone is not payout authorization: the retailer workflow
            // must reconcile sourcing, collection, customer receipt, disputes/returns,
            // and supplier-specific payable before a dedicated payout path is permitted.
            if ($lockedOrder->retailer_model_order) {
                return [
                    'success' => false,
                    'message' => 'Retailer-model orders cannot use the legacy order-level vendor payout workflow.',
                ];
            }

            if ($lockedOrder->vendor_settlement_status !== 'eligible') {
                return [
                    'success' => false,
                    'message' => "Order is not eligible for disbursement (current state: {$lockedOrder->vendor_settlement_status}).",
                ];
            }

            // Merchant Integrity & 48-Hour Bank Cooldown Defense
            $vendor = $lockedOrder->vendor;
            if (!$vendor) {
                return ['success' => false, 'message' => 'Merchant profile not found for this order.'];
            }

            if ($vendor->marketplace_status !== 'active') {
                return [
                    'success' => false,
                    'message' => "Disbursement blocked: Merchant marketplace account is {$vendor->marketplace_status}.",
                ];
            }

            if ($vendor->isWithinBankCooldown()) {
                return [
                    'success' => false,
                    'message' => 'Disbursement blocked: Merchant bank account was recently updated and is under a 48-hour security cooldown.',
                ];
            }

            $lockedOrder->vendor_settlement_status = 'settled';
            $lockedOrder->settled_at = Carbon::now();
            $lockedOrder->settled_by_admin_id = $adminUser->id;
            $lockedOrder->settlement_proof_path = $proofPath;
            $lockedOrder->save();

            // Authoritative Double-Entry Financial Journal: Debit vendor_payable, Credit cash_in_escrow
            $payoutRef = 'VND-PAY-' . $lockedOrder->order_number . '-' . date('YmdHis');
            FinancialJournalEntry::create([
                'order_id' => $lockedOrder->id,
                'transaction_reference' => $payoutRef,
                'event_id' => 'EVT-VND-PAY-' . $lockedOrder->order_number,
                'beneficiary_type' => VendorProfile::class,
                'beneficiary_id' => $vendor->id,
                'account_name' => 'vendor_payable',
                'entry_type' => 'debit',
                'amount' => $lockedOrder->vendor_net_90,
                'currency' => 'NGN',
                'description' => "Bank settlement disbursement of ₦{$lockedOrder->vendor_net_90} to {$vendor->business_name} (Acct: {$vendor->account_number})",
            ]);

            FinancialJournalEntry::create([
                'order_id' => $lockedOrder->id,
                'transaction_reference' => $payoutRef,
                'event_id' => 'EVT-VND-PAY-' . $lockedOrder->order_number,
                'beneficiary_type' => VendorProfile::class,
                'beneficiary_id' => $vendor->id,
                'account_name' => 'cash_in_escrow',
                'entry_type' => 'credit',
                'amount' => $lockedOrder->vendor_net_90,
                'currency' => 'NGN',
                'description' => "Escrow cash outflow via verified bank transfer to {$vendor->business_name}",
            ]);

            // Immutable Financial Audit Log
            AdminAuditLog::create([
                'user_id' => $adminUser->id,
                'action' => 'vendor_settlement_disbursed',
                'target_type' => Order::class,
                'target_id' => $lockedOrder->id,
                'old_values' => [
                    'vendor_settlement_status' => 'eligible',
                ],
                'new_values' => [
                    'vendor_settlement_status' => 'settled',
                    'vendor_net_90' => $lockedOrder->vendor_net_90,
                    'vendor_id' => $vendor->id,
                    'merchant_name' => $vendor->business_name,
                    'proof_path' => $proofPath,
                    'settled_at' => $lockedOrder->settled_at->toIso8601String(),
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'message' => "Settlement of ₦{$lockedOrder->vendor_net_90} disbursed successfully.",
                'order' => $lockedOrder,
            ];
        });
    }

    /**
     * Reclaims inventory and cancels abandoned orders whose payment expiration deadline passed.
     */
    public function expireAbandonedPendingOrders(): int
    {
        $now = Carbon::now();
        $expiredOrders = Order::where('payment_status', 'pending')
            ->where('order_status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        $count = 0;
        foreach ($expiredOrders as $order) {
            // P0 Control: Payment Truth Outranks Expiration
            // If the order has an initiated payment reference, verify gateway truth before cancelling.
            if (!empty($order->payment_reference)) {
                $verification = $this->paystackService->verifyTransaction($order->payment_reference, $order);

                if (!empty($verification['success']) && ($verification['data']['status'] ?? '') === 'success') {
                    \Illuminate\Support\Facades\Log::info("Payment Truth Defense: Order #{$order->id} (Ref: {$order->payment_reference}) was captured by Paystack before expiration. Settling order.");
                    $this->settlePaystackOrder(
                        $order,
                        $verification['data'],
                        null,
                        'expiration_defense_reconciliation'
                    );
                    continue; // Successfully settled, do NOT cancel or restore stock!
                }

                // If verification failed due to transient gateway network timeout, defer cancellation to next cycle
                if (($verification['code'] ?? '') === 'GATEWAY_ERROR' && str_contains(strtolower($verification['message'] ?? ''), 'timeout')) {
                    \Illuminate\Support\Facades\Log::warning("Skipping order #{$order->id} expiry: Paystack gateway check timed out. Deferring to next cycle.");
                    continue;
                }
            }

            DB::transaction(function () use ($order, &$count) {
                $locked = Order::where('id', $order->id)->lockForUpdate()->first();
                if (
                    !$locked
                    || $locked->payment_status !== 'pending'
                    || $locked->order_status !== 'pending'
                    || !$locked->expires_at
                    || $locked->expires_at->gt(Carbon::now())
                ) {
                    // The candidate query may be stale. Re-check expiry under the row
                    // lock so a renewed/extended pending order is never cancelled by an
                    // older scheduler snapshot.
                    return;
                }

                $locked->order_status = 'cancelled';
                $locked->save();

                // Cancel any pending pickup reservation
                \App\Models\PickupReservation::where('order_id', $locked->id)
                    ->whereIn('status', ['reserved', 'pending_payment'])
                    ->update(['status' => 'canceled']);

                // New Victorious MARKET retailer orders do not reserve/decrement
                // canonical inventory before payment: supplier stock is an availability
                // signal and exact quantity is confirmed only after verified payment.
                // Restoring quantity for these orders would fabricate stock and can turn
                // the zero-inventory sentinel into a false legacy stock counter.
                if (!$locked->retailer_model_order) {
                    foreach ($locked->items as $item) {
                        if ($item->product_id) {
                            $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                            if ($product && isset($product->inventory_quantity) && (int) $product->inventory_quantity >= 0) {
                                $product->increment('inventory_quantity', (int) $item->quantity);
                                $product->is_in_stock = true;
                                $product->save();
                            }
                        }
                    }
                }

                $count++;
            });
        }

        return $count;
    }
}
