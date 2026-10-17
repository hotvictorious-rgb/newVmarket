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
    public function settlePaystackOrder(Order $order, array $paystackData, ?User $actorUser = null, string $channel = 'paystack_webhook'): array
    {
        return DB::transaction(function () use ($order, $paystackData, $actorUser, $channel) {
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

            // 8. Atomic State Transition & Expiry Race Defense
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
                        if (!$product || $product->inventory_quantity < $item->quantity) {
                            $canReinstate = false;
                            break;
                        }
                    }
                }

                if ($canReinstate) {
                    // Re-reserve stock and reinstate order to confirmed
                    foreach ($lockedOrder->items as $item) {
                        if ($item->product_id) {
                            $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                            if ($product) {
                                $product->decrement('inventory_quantity', $item->quantity);
                                $product->is_in_stock = ($product->inventory_quantity > 0);
                                $product->save();
                            }
                        }
                    }
                    $lockedOrder->order_status = 'confirmed';
                    $lockedOrder->vendor_settlement_status = 'held';

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
                    // Stock is depleted. Order remains cancelled; funds held as customer refund liability.
                    $lockedOrder->order_status = 'cancelled';
                    $lockedOrder->vendor_settlement_status = 'refund_pending';

                    AdminAuditLog::create([
                        'user_id' => $lockedOrder->customer_id,
                        'action' => 'paid_after_expiration_out_of_stock_refund_queued',
                        'target_type' => Order::class,
                        'target_id' => $lockedOrder->id,
                        'old_values' => ['order_status' => 'cancelled', 'payment_status' => 'pending'],
                        'new_values' => [
                            'order_status' => 'cancelled',
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

                // Invoke automated Paystack refund API
                $refundRes = $this->paystackService->initiateRefund(
                    $reference,
                    (string)$lockedOrder->grand_total,
                    "Automated refund for out-of-stock/cancelled Order #{$lockedOrder->order_number}"
                );

                if (!empty($refundRes['success']) && !empty($refundRes['refund_reference'])) {
                    $lockedOrder->paystack_refund_reference = $refundRes['refund_reference'];
                    $lockedOrder->save();
                }

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
                            'matures_at' => Carbon::now()->addDays(365),
                            'expires_at' => Carbon::now()->addDays(730),
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

                    // Credit: Logistics Fleet Payable per Child Order (90% Child Delivery Net)
                    if (bccomp((string)$child->logistics_fee_retained, '0.00', 2) > 0) {
                        FinancialJournalEntry::create([
                            'order_id' => $child->id,
                            'transaction_reference' => $reference,
                            'event_id' => 'EVT-FLEET-ESCROW-' . $child->id . '-' . $reference,
                            'beneficiary_type' => LogisticsCompanyProfile::class,
                            'beneficiary_id' => $child->logistics_company_id ?: $lockedOrder->logistics_company_id,
                            'account_name' => 'logistics_payable',
                            'entry_type' => 'credit',
                            'amount' => $child->logistics_fee_retained,
                            'currency' => 'NGN',
                            'description' => "Logistics Fleet 90% Net Payable held for Child Order #{$child->order_number}",
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

                // Credit: Logistics Payable (90% Delivery Net from parent order delivery fee)
                if (bccomp((string)$lockedOrder->logistics_fee_retained, '0.00', 2) > 0) {
                    FinancialJournalEntry::create([
                        'order_id' => $lockedOrder->id,
                        'transaction_reference' => $reference,
                        'event_id' => 'EVT-FLEET-ESCROW-' . $reference,
                        'beneficiary_type' => LogisticsCompanyProfile::class,
                        'beneficiary_id' => $lockedOrder->logistics_company_id,
                        'account_name' => 'logistics_payable',
                        'entry_type' => 'credit',
                        'amount' => $lockedOrder->logistics_fee_retained,
                        'currency' => 'NGN',
                        'description' => "Logistics Fleet 90% Net Payable held for Order #{$lockedOrder->order_number}",
                    ]);
                }

                // Credit: Platform Delivery Fee Revenue (10% Delivery Cut from parent order)
                if (bccomp((string)$lockedOrder->platform_delivery_fee, '0.00', 2) > 0) {
                    FinancialJournalEntry::create([
                        'order_id' => $lockedOrder->id,
                        'transaction_reference' => $reference,
                        'event_id' => 'EVT-PLATFORM-DELIVERY-REV-' . $reference,
                        'beneficiary_type' => User::class,
                        'beneficiary_id' => null,
                        'account_name' => 'platform_revenue',
                        'entry_type' => 'credit',
                        'amount' => $lockedOrder->platform_delivery_fee,
                        'currency' => 'NGN',
                        'description' => "Victorious Market 10% Delivery Fee Retained for Order #{$lockedOrder->order_number}",
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

                // Credit: Logistics Payable (90% Delivery Net)
                if (bccomp((string)$lockedOrder->logistics_fee_retained, '0.00', 2) > 0) {
                    FinancialJournalEntry::create([
                        'order_id' => $lockedOrder->id,
                        'transaction_reference' => $reference,
                        'event_id' => 'EVT-FLEET-ESCROW-' . $reference,
                        'beneficiary_type' => LogisticsCompanyProfile::class,
                        'beneficiary_id' => $lockedOrder->logistics_company_id,
                        'account_name' => 'logistics_payable',
                        'entry_type' => 'credit',
                        'amount' => $lockedOrder->logistics_fee_retained,
                        'currency' => 'NGN',
                        'description' => "Logistics Fleet 90% Net Payable held for Order #{$lockedOrder->order_number}",
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
                        'matures_at' => Carbon::now()->addDays(365), // Locked until confirmed delivery/pickup!
                        'expires_at' => Carbon::now()->addDays(730),
                    ]);
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

            if (!in_array($lockedOrder->order_status, ['confirmed', 'processing', 'ready_for_pickup'])) {
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

            if (!in_array($lockedOrder->order_status, ['confirmed', 'processing', 'ready_for_pickup'])) {
                return ['success' => false, 'message' => "Order cannot be picked up in status {$lockedOrder->order_status}."];
            }

            // Strict 6-Digit Format Validation
            if (!preg_match('/^[0-9]{6}$/', trim($otp))) {
                return ['success' => false, 'message' => 'Pickup verification code must be exactly 6 digits.'];
            }

            // Courier Operational Clearance Check
            if (!$rider->is_active) {
                return ['success' => false, 'message' => 'Courier account is currently inactive.'];
            }
            if ($rider->user && $rider->user->status !== 'active') {
                return ['success' => false, 'message' => 'Courier account is currently suspended.'];
            }
            if ($rider->logisticsCompany && $rider->logisticsCompany->status !== 'active') {
                return ['success' => false, 'message' => 'Courier logistics fleet is currently suspended.'];
            }

            // Merchant Operational Clearance Check
            if ($lockedOrder->vendor && $lockedOrder->vendor->marketplace_status !== 'active') {
                return ['success' => false, 'message' => 'Merchant marketplace account is currently suspended.'];
            }

            // Enforce Rider Assignment Authorization
            if ($lockedOrder->rider_id && (int)$lockedOrder->rider_id !== (int)$rider->id) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized: This order is assigned to another dispatch courier.',
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
            $lockedOrder->rider_id = $rider->id;

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

            if ($lockedOrder->order_status !== 'out_for_delivery') {
                return [
                    'success' => false,
                    'message' => "Order cannot be marked arrived in status {$lockedOrder->order_status}.",
                ];
            }

            // Enforce Rider Assignment Authorization
            if ($lockedOrder->rider_id && (int)$lockedOrder->rider_id !== (int)$rider->id) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized: This delivery is assigned to another dispatch courier.',
                ];
            }

            $lockedOrder->rider_arrived_at = Carbon::now();
            if (!$lockedOrder->rider_id) {
                $lockedOrder->rider_id = $rider->id;
            }
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

            // 1. Courier & Logistics Fleet Hardening: Validate active status at point of delivery
            $activeRider = $rider ?? ($lockedOrder->rider_id ? RiderProfile::with(['user', 'logisticsCompany'])->find($lockedOrder->rider_id) : null);
            $haltReason = null;
            if ($activeRider) {
                if (!$activeRider->is_active) {
                    $haltReason = 'Unauthorized: Dispatch courier profile is suspended or inactive. Delivery halted.';
                } elseif ($activeRider->user && $activeRider->user->status !== 'active') {
                    $haltReason = 'Unauthorized: Courier user account is inactive. Delivery halted.';
                } elseif (!$activeRider->logistics_company_id || ($activeRider->logisticsCompany && $activeRider->logisticsCompany->status !== 'active')) {
                    $haltReason = 'Unauthorized: Parent logistics fleet account is inactive or suspended. Delivery halted.';
                } elseif ($lockedOrder->rider_id && (int)$lockedOrder->rider_id !== (int)$activeRider->id) {
                    $haltReason = 'Unauthorized: This order is assigned to another delivery courier.';
                }
            }

            // 2. Logistics Company Direct Check
            if (!$haltReason) {
                $orderFleet = null;
                if ($lockedOrder->logistics_company_id) {
                    $orderFleet = LogisticsCompanyProfile::find($lockedOrder->logistics_company_id);
                } elseif ($activeRider && $activeRider->logistics_company_id) {
                    $orderFleet = $activeRider->logisticsCompany;
                }
                if ($orderFleet && $orderFleet->status !== 'active') {
                    $haltReason = 'Assigned logistics fleet company is suspended or inactive. Delivery halted.';
                }
            }

            // 3. Merchant / Vendor Hardening: Validate merchant active status
            if (!$haltReason && $lockedOrder->vendor) {
                if ($lockedOrder->vendor->marketplace_status !== 'active') {
                    $haltReason = 'Merchant marketplace account is suspended. Delivery settlement halted.';
                } elseif ($lockedOrder->vendor->user && $lockedOrder->vendor->user->status !== 'active') {
                    $haltReason = 'Merchant user account is inactive. Delivery settlement halted.';
                }
            }

            // 4. Shop Branch Hardening: Validate shop branch is active
            if (!$haltReason && $lockedOrder->shop && !$lockedOrder->shop->is_active) {
                $haltReason = 'Merchant shop branch is inactive or closed. Delivery settlement halted.';
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

            // Corporate B2B Settlement: Credit 90% of delivery fee to accredited Logistics Company Corporate Wallet
            // Victorious Market retains 10% platform delivery fee cut
            $targetRider = $rider ?? ($lockedOrder->rider_id ? RiderProfile::with('logisticsCompany')->find($lockedOrder->rider_id) : null);
            $logisticsCompany = null;
            if ($lockedOrder->logistics_company_id) {
                $logisticsCompany = LogisticsCompanyProfile::find($lockedOrder->logistics_company_id);
            }
            if (!$logisticsCompany) {
                if ($lockedOrder->collection_point_id) {
                    $collectionPoint = \App\Models\LogisticsCollectionPoint::find($lockedOrder->collection_point_id);
                    $logisticsCompany = $collectionPoint ? $collectionPoint->company : null;
                }
                if (!$logisticsCompany && $targetRider && $targetRider->logistics_company_id) {
                    $logisticsCompany = $targetRider->logisticsCompany;
                }
                if (!$logisticsCompany && $lockedOrder->delivery_lane_id) {
                    $lane = \App\Models\DeliveryLane::find($lockedOrder->delivery_lane_id);
                    $logisticsCompany = $lane ? $lane->assignedLogisticsCompany : null;
                }
                if ($logisticsCompany) {
                    $lockedOrder->logistics_company_id = $logisticsCompany->id;
                    $lockedOrder->save();
                }
            }
            // Authoritative 90% Logistics Net Calculation via BCMath
            $logisticsNetCut = $lockedOrder->logistics_fee_retained;
            if (empty($logisticsNetCut) || bccomp((string)$logisticsNetCut, '0.00', 2) === 0) {
                $logisticsNetCut = bcmul((string)($lockedOrder->delivery_fee ?? '0.00'), '0.90', 2);
            }

            // Closed-Loop Platform Settlement:
            // 100% of the delivery proceeds (90% logistics cut) are credited exclusively to the accredited Corporate Fleet Wallet of the Logistics Company.
            // Under NO circumstance is an unrelated active logistics company credited.
            // If no authorized company is resolvable, funds remain safely held in platform escrow pending administrative assignment.
            if ($logisticsCompany && $logisticsCompany->status === 'active' && bccomp((string)$logisticsNetCut, '0.00', 2) > 0) {
                $logisticsCompany->creditWallet((string)$logisticsNetCut);

                // Authoritative Double-Entry Journal Entry: Transfer from logistics_payable to corporate fleet wallet
                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => 'FLEET-EARN-' . $lockedOrder->order_number,
                    'event_id' => 'EVT-FLEET-EARN-' . $lockedOrder->order_number,
                    'beneficiary_type' => LogisticsCompanyProfile::class,
                    'beneficiary_id' => $logisticsCompany->id,
                    'account_name' => 'logistics_payable',
                    'entry_type' => 'debit',
                    'amount' => (string)$logisticsNetCut,
                    'currency' => 'NGN',
                    'description' => "Delivery fee released to {$logisticsCompany->company_name} corporate wallet for Order #{$lockedOrder->order_number}",
                ]);

                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => 'FLEET-EARN-' . $lockedOrder->order_number,
                    'event_id' => 'EVT-FLEET-EARN-' . $lockedOrder->order_number,
                    'beneficiary_type' => LogisticsCompanyProfile::class,
                    'beneficiary_id' => $logisticsCompany->id,
                    'account_name' => 'logistics_company_wallet',
                    'entry_type' => 'credit',
                    'amount' => (string)$logisticsNetCut,
                    'currency' => 'NGN',
                    'description' => "Corporate fleet earnings credited from delivery escrow for Order #{$lockedOrder->order_number}",
                ]);
            } elseif (bccomp((string)$logisticsNetCut, '0.00', 2) > 0) {
                // Operational Exception Audit: Retain in platform escrow
                AdminAuditLog::create([
                    'user_id' => $targetRider ? $targetRider->user_id : null,
                    'action' => 'unassigned_logistics_fee_held_in_escrow',
                    'target_type' => Order::class,
                    'target_id' => $lockedOrder->id,
                    'old_values' => ['logistics_net_cut' => (string)$logisticsNetCut],
                    'new_values' => [
                        'status' => 'held_in_platform_escrow',
                        'reason' => 'No active accredited logistics company affiliated with order, collection point, rider, or lane.',
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent(),
                ]);
            }

            // Update/Enqueue 5% Customer Cashback reward with exact refund window maturation lock
            CustomerCashbackLedger::updateOrCreate([
                'order_id' => $lockedOrder->id,
            ], [
                'customer_id' => $lockedOrder->customer_id,
                'amount' => $lockedOrder->customer_cashback_5,
                'status' => 'pending_maturation',
                'matures_at' => $lockedOrder->refund_window_expires_at,
                'expires_at' => $lockedOrder->refund_window_expires_at->copy()->addDays(365),
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

        if ($staffUser->isVendorEmployee()) {
            $employee = $staffUser->vendorEmployeeProfile;
            if (!$employee || !$employee->is_active || !$employee->canAccessBranch($reservation->shop_id)) {
                return ['success' => false, 'message' => 'Unauthorized: Branch isolation prevents access to this shop.'];
            }
        } elseif ($staffUser->isVendor()) {
            $vendor = $staffUser->vendorProfile;
            if (!$vendor || (int)$vendor->id !== (int)$reservation->vendor_id) {
                return ['success' => false, 'message' => 'Unauthorized: Merchant ownership mismatch.'];
            }
            if ($vendor->marketplace_status !== 'active') {
                return ['success' => false, 'message' => 'Merchant marketplace account is suspended.'];
            }
        }

        if ($reservation->status !== 'reserved') {
            return ['success' => false, 'message' => "Reservation cannot be inspected in status {$reservation->status}."];
        }

        if ($reservation->isExpired()) {
            $reservation->status = 'expired';
            $reservation->save();
            return ['success' => false, 'message' => 'This reservation has expired.'];
        }

        $reservation->status = 'inspected_accepted';
        $reservation->inspected_at = Carbon::now();
        $reservation->inspected_by_staff_id = $staffUser->id;
        $reservation->save();

        return [
            'success' => true,
            'message' => 'Inspection approved. Customer digital payment is now unlocked.',
            'reservation' => $reservation,
        ];
    }

    /**
     * Handshake 4: In-Shop Counter Handover OTP Verification.
     * Goods released to customer after verified digital payment.
     */
    public function verifyPickupHandoverOtp(Order $order, string $otp, User $staffUser): array
    {
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
        }

        // Strict 6-Digit Format Validation
        if (!preg_match('/^[0-9]{6}$/', trim($otp))) {
            return ['success' => false, 'message' => 'Handover verification code must be exactly 6 digits.'];
        }

        return DB::transaction(function () use ($order, $otp, $staffUser) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if ($lockedOrder->fulfillment_type !== 'in_shop_pickup') {
                return ['success' => false, 'message' => 'This is not an in-shop pickup order.'];
            }

            if ($lockedOrder->payment_status !== 'paid') {
                return ['success' => false, 'message' => 'Goods cannot be handed over before verified payment.'];
            }

            // Timed Lockout Protection: Check if handover verification is locked
            if ($lockedOrder->isPickupLocked()) {
                $minutesRemaining = max(1, Carbon::now()->diffInMinutes($lockedOrder->pickup_locked_until));
                return [
                    'success' => false,
                    'message' => "Handover verification locked due to excessive failed attempts. Try again in {$minutesRemaining} minute(s).",
                ];
            }

            if (!hash_equals((string)$lockedOrder->verification_code, trim($otp))) {
                $lockedOrder->increment('pickup_failed_attempts');
                if ($lockedOrder->pickup_failed_attempts >= 5) {
                    $lockedOrder->pickup_locked_until = Carbon::now()->addMinutes(15);
                    $lockedOrder->save();
                    return [
                        'success' => false,
                        'message' => 'Handover verification temporarily locked for 15 minutes due to 5 consecutive incorrect attempts.',
                    ];
                }
                return ['success' => false, 'message' => 'Invalid pickup handover OTP.'];
            }

            $lockedOrder->pickup_failed_attempts = 0;
            $lockedOrder->pickup_locked_until = null;

            $now = Carbon::now();
            $refundHours = max(1, (int) SystemSetting::get('refund_window_hours', 24));
            $lockedOrder->order_status = 'delivered';
            $lockedOrder->received_at = $now;
            $lockedOrder->refund_window_expires_at = $now->copy()->addHours($refundHours);
            $lockedOrder->vendor_settlement_status = 'held';
            $lockedOrder->save();

            // Mark associated reservation handed over
            PickupReservation::where('order_id', $lockedOrder->id)->update([
                'status' => 'handed_over',
            ]);

            // Update/Enqueue 5% Customer Cashback reward with exact refund window maturation lock
            CustomerCashbackLedger::updateOrCreate([
                'order_id' => $lockedOrder->id,
            ], [
                'customer_id' => $lockedOrder->customer_id,
                'amount' => $lockedOrder->customer_cashback_5,
                'status' => 'pending_maturation',
                'matures_at' => $lockedOrder->refund_window_expires_at,
                'expires_at' => $lockedOrder->refund_window_expires_at->copy()->addDays(365),
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
        if ($order->order_status !== 'delivered' || !$order->isReturnWindowExpired()) {
            return;
        }

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            // Check if active dispute exists or active return case exists
            $hasDispute = $lockedOrder->dispute()->whereIn('status', ['filed', 'under_review'])->exists();
            $hasReturnCase = $lockedOrder->returnCases()->whereNotIn('status', ['resolved', 'rejected'])->exists();

            if ($hasDispute || $hasReturnCase) {
                $lockedOrder->vendor_settlement_status = 'disputed';
                $lockedOrder->save();

                CustomerCashbackLedger::where('order_id', $lockedOrder->id)->update([
                    'status' => 'quarantined_dispute',
                ]);
                return;
            }

            if ($lockedOrder->vendor_settlement_status === 'held') {
                $lockedOrder->vendor_settlement_status = 'eligible';
                $lockedOrder->save();
            }

            CustomerCashbackLedger::where('order_id', $lockedOrder->id)
                ->where('status', 'pending_maturation')
                ->where('matures_at', '<=', Carbon::now())
                ->update(['status' => 'available']);
        });
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
                if (!$locked || $locked->payment_status !== 'pending' || $locked->order_status !== 'pending') {
                    return;
                }

                $locked->order_status = 'cancelled';
                $locked->save();

                foreach ($locked->items as $item) {
                    if ($item->product_id) {
                        $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                        if ($product) {
                            $product->increment('inventory_quantity', $item->quantity);
                            $product->is_in_stock = ($product->inventory_quantity > 0);
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
