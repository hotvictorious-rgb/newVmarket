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

            // 2. IDEMPOTENCY GUARD: Already settled payment (Prevents duplicate captures / cashback replays)
            if ($lockedOrder->payment_status === 'paid') {
                return [
                    'success' => true,
                    'status' => 'ALREADY_PAID',
                    'is_replayed' => true,
                    'message' => 'Payment has already been successfully verified and settled.',
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
            $lockedOrder->payment_status = 'paid';
            $lockedOrder->paid_at = $now;
            $lockedOrder->payment_channel = (string)($paystackData['channel'] ?? 'paystack');
            $lockedOrder->paystack_transaction_id = (string)($paystackData['id'] ?? $reference);

            if ($lockedOrder->order_status === 'cancelled') {
                // The order was previously cancelled by the expiration worker.
                // Verify whether stock can be re-reserved atomically.
                $canReinstate = $lockedOrder->items->isNotEmpty();
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

                // Deduct inventory quantity if tracked upon confirmed payment
                foreach ($lockedOrder->items as $item) {
                    if ($item->product_id) {
                        $prod = Product::where('id', $item->product_id)->lockForUpdate()->first();
                        if ($prod && isset($prod->inventory_quantity) && (int)$prod->inventory_quantity > 0) {
                            $prod->decrement('inventory_quantity', (int)$item->quantity);
                            $prod->is_in_stock = ((int)$prod->inventory_quantity > 0);
                            $prod->save();
                        }
                    }
                }

                // Transition any associated pickup reservation to payment_completed upon confirmed gateway settlement
                \App\Models\PickupReservation::where('order_id', $lockedOrder->id)->update(['status' => 'payment_completed']);
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
                    if ($lockedOrder->supplier_cost_total_snapshot !== null
                        && BcMathService::comp($supplierCostTotal, (string) $lockedOrder->supplier_cost_total_snapshot) !== 0) {
                        throw new \RuntimeException('Retailer supplier-cost total does not match the immutable order snapshot.');
                    }

                    foreach ($items as $item) {
                        $lineCost = BcMathService::mul((string) $item->supplier_cost_snapshot, (string) $item->quantity);
                        if (BcMathService::comp($lineCost, '0.00') > 0) {
                            FinancialJournalEntry::create([
                                'order_id' => $lockedOrder->id,
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
                    $defaultCompanyPayable = $lockedOrder->logistics_company_payable_default_amount;
                    if ($defaultCompanyPayable === null) {
                        $defaultCompanyPayable = BcMathService::mul($deliveryFeeSnapshot, '0.90');
                        $lockedOrder->logistics_company_payable_default_amount = $defaultCompanyPayable;
                    }
                    $companyPayable = (string) ($lockedOrder->logistics_company_payable_amount ?? $defaultCompanyPayable);
                    $lockedOrder->logistics_company_payable_amount = $companyPayable;
                    $lockedOrder->logistics_company_payable_ledger_company_id = $lockedOrder->logistics_company_id;
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

                    $deliveryMargin = BcMathService::comp($deliveryFeeSnapshot, $companyPayable) > 0
                        ? BcMathService::sub($deliveryFeeSnapshot, $companyPayable)
                        : '0.00';
                    $deliverySubsidy = BcMathService::comp($companyPayable, $deliveryFeeSnapshot) > 0
                        ? BcMathService::sub($companyPayable, $deliveryFeeSnapshot)
                        : '0.00';

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

            // 11. Multi-Channel Alert to Customer & Vendor
            NotificationDispatchService::dispatchOrderPlaced($lockedOrder);

            return [
                'success' => true,
                'status' => 'PAID',
                'is_replayed' => false,
                'message' => 'Payment verified and order successfully confirmed.',
                'order' => $lockedOrder,
            ];
        });

        // Out-of-DB-Transaction Paystack Refund Execution via Authoritative FinancialOperationService
        if (!empty($result['trigger_gateway_refund'])) {
            $orderToRefund = Order::find($result['refund_order_id']);
            if ($orderToRefund) {
                try {
                    $finOpService = app(\App\Services\FinancialOperationService::class);
                    $op = $finOpService->createRefundOperation(
                        $orderToRefund,
                        $result['refund_amount'],
                        "Automated refund for out-of-stock/cancelled Order #{$result['refund_order_number']}",
                        null,
                        null,
                        ['type' => 'auto_late_payment_cancelled_before_vendor_action']
                    );
                    $dispatchRes = $finOpService->dispatchRefund($op);

                    if (isset($result['order'])) {
                        $result['order']->refresh();
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("OrderSettlementService refund operation error: " . $e->getMessage());
                }
            }
        }

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

                foreach ($locked->items as $item) {
                    if ($item->product_id) {
                        $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                        if ($product && isset($product->inventory_quantity) && (int)$product->inventory_quantity >= 0) {
                            $product->increment('inventory_quantity', (int)$item->quantity);
                            $product->is_in_stock = true;
                            $product->save();
                        }
                    }
                }

                $count++;
            });
        }

        return $count;
    }
}
