<?php

namespace App\Services;

use App\Models\CashbackReservation;
use App\Models\CustomerAddress;
use App\Models\CustomerCashbackLedger;
use App\Models\CustomerWalletCredit;
use App\Models\DeliveryLane;
use App\Models\FinancialJournalEntry;
use App\Models\LogisticsCollectionPoint;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutExecutionService
{
    public function __construct(
        protected FulfillmentAvailabilityService $fulfillmentService,
        protected PaystackService $paystackService
    ) {}

    /**
     * Authoritative Order Checkout Execution Engine.
     * Pessimistically locks catalog stock, applies cashback/wallet credits,
     * routes logistics, and prepares Paystack payment intent.
     */
    public function execute(User $user, array $validated): array
    {
        // Guard 0: Customer Account Invariant
        if ($user && $user->status !== 'active') {
            return [
                'success' => false,
                'status_code' => 403,
                'message' => 'Your customer account is deactivated or suspended.',
            ];
        }

        $shop = Shop::withTrashed()->with(['lga', 'vendor.user'])->findOrFail($validated['shop_id']);

        // Guard 1: Shop Branch Invariant
        if ($shop->trashed() || !$shop->is_active) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'The selected shop branch is currently inactive.',
            ];
        }

        // Guard 2: Vendor Entity & User Account Invariant
        if (!$shop->vendor || $shop->vendor->trashed() || $shop->vendor->marketplace_status !== 'active') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'The merchant providing this item is currently not active on the marketplace.',
            ];
        }

        if (!$shop->vendor->user || $shop->vendor->user->status !== 'active') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'The merchant user account is inactive.',
            ];
        }

        // Guard 3: Explicit Logistics Company Invariant
        if (!empty($validated['logistics_company_id'])) {
            $logistics = LogisticsCompanyProfile::withTrashed()->with('user')->find($validated['logistics_company_id']);
            if (!$logistics || $logistics->trashed() || $logistics->status !== 'active') {
                return [
                    'success' => false,
                    'status_code' => 422,
                    'message' => 'The selected logistics company is currently inactive or suspended.',
                ];
            }
            if ($logistics->user && $logistics->user->status !== 'active') {
                return [
                    'success' => false,
                    'status_code' => 422,
                    'message' => 'The logistics company user account is inactive.',
                ];
            }
        }

        // Guard 4: Explicit Designated Courier / Rider Invariant
        if (!empty($validated['rider_id'])) {
            $rider = RiderProfile::withTrashed()->with(['user', 'logisticsCompany.user'])->find($validated['rider_id']);
            if (!$rider || $rider->trashed() || !$rider->is_active || ($rider->user && $rider->user->status !== 'active')) {
                return [
                    'success' => false,
                    'status_code' => 422,
                    'message' => 'The designated delivery rider is currently inactive or suspended.',
                ];
            }
            if ($rider->logisticsCompany && ($rider->logisticsCompany->trashed() || $rider->logisticsCompany->status !== 'active' || ($rider->logisticsCompany->user && $rider->logisticsCompany->user->status !== 'active'))) {
                return [
                    'success' => false,
                    'status_code' => 422,
                    'message' => 'The logistics fleet for this delivery rider is currently deactivated or suspended.',
                ];
            }
        }

        $result = DB::transaction(function () use ($validated, $user, $shop) {
            $merchandiseSubtotal = '0.00';
            $itemsToCreate = [];

            foreach ($validated['items'] as $item) {
                $product = Product::marketplaceEligible()->lockForUpdate()->findOrFail($item['product_id']);

                if ((int)$product->shop_id !== (int)$shop->id) {
                    return [
                        'error' => true,
                        'message' => "Product '{$product->name}' does not belong to the selected shop branch.",
                    ];
                }

                if ((int)$product->vendor_id !== (int)$shop->vendor_id) {
                    return [
                        'error' => true,
                        'message' => "Product '{$product->name}' does not belong to the selected merchant.",
                    ];
                }

                if ($product->inventory_quantity < $item['quantity']) {
                    return [
                        'error' => true,
                        'message' => "Insufficient stock for {$product->name}. Only {$product->inventory_quantity} available.",
                    ];
                }

                $lineTotal = BcMathService::mul($product->unit_price, (string)$item['quantity']);
                $merchandiseSubtotal = BcMathService::add($merchandiseSubtotal, $lineTotal);

                if (bccomp($merchandiseSubtotal, '100000000.00', 2) > 0) {
                    return [
                        'error' => true,
                        'message' => 'Merchandise subtotal exceeds Paystack maximum single transaction limit of ₦100,000,000.00.',
                    ];
                }

                $itemsToCreate[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->unit_price,
                    'line_total' => $lineTotal,
                ];
            }

            // Delivery Fee & Corridor Resolution
            $deliveryFee = '0.00';
            $destLgaId = $shop->lga_id;
            $laneId = null;
            $collectionPointId = null;

            if ($validated['fulfillment_type'] === 'doorstep_delivery') {
                $address = CustomerAddress::where('user_id', $user->id)->findOrFail($validated['customer_address_id']);
                $destLgaId = $address->lga_id;

                $orderPackageTier = 'bike';
                foreach ($itemsToCreate as $it) {
                    $tier = $it['product']->package_tier ?? 'bike';
                    if (in_array(strtolower($tier), ['keke', 'bulky'], true)) {
                        $orderPackageTier = 'keke';
                        break;
                    }
                }

                $availability = $this->fulfillmentService->checkDeliveryAvailability($shop->id, $destLgaId, $orderPackageTier);
                if (!$availability['is_available']) {
                    return [
                        'error' => true,
                        'message' => $availability['message'],
                    ];
                }

                $deliveryFee = $availability['fee'];
                $laneId = $availability['lane_id'] ?? null;
                $collectionPointId = $availability['collection_point_id'] ?? null;
            }

            // Cashback Redemption Check under Pessimistic Balance Reservation
            $cashbackApplied = '0.00';
            if (!empty($validated['redeem_cashback'])) {
                $now = Carbon::now();
                $availableLotsSum = CustomerCashbackLedger::where('customer_id', $user->id)
                    ->where('status', 'available')
                    ->where('matures_at', '<=', $now)
                    ->where('expires_at', '>', $now)
                    ->lockForUpdate()
                    ->sum('amount');

                $activeReservedSum = CashbackReservation::where('customer_id', $user->id)
                    ->where('status', 'reserved')
                    ->where('expires_at', '>', $now)
                    ->lockForUpdate()
                    ->sum('amount');

                $effectiveAvailable = bcsub((string)$availableLotsSum, (string)$activeReservedSum, 2);
                if (bccomp($effectiveAvailable, '0.00', 2) > 0) {
                    $maxRedemption = BcMathService::mul($merchandiseSubtotal, '0.50');
                    $cashbackApplied = bccomp($effectiveAvailable, $maxRedemption) > 0 ? $maxRedemption : $effectiveAvailable;
                }
            }

            // Mathematical Splits (90% Vendor Net, 5% Margin, 5% Cashback)
            $vendorNet90 = BcMathService::mul($merchandiseSubtotal, '0.90');
            $customerCashback5 = BcMathService::mul($merchandiseSubtotal, '0.05');
            $platformMargin5 = BcMathService::mul($merchandiseSubtotal, '0.05');

            // Logistics Splits (90% Rider/Fleet, 10% Platform Delivery Fee)
            $logisticsNet90 = BcMathService::mul($deliveryFee, '0.90');
            $platformDelivery10 = BcMathService::mul($deliveryFee, '0.10');

            // Instant Refund Wallet Credit redemption (100% spendable, covers up to 100% of order payable)
            $subtotalAfterCashback = BcMathService::sub($merchandiseSubtotal, $cashbackApplied);
            $totalBeforeWallet = BcMathService::add($subtotalAfterCashback, $deliveryFee);

            $walletCreditApplied = '0.00';
            if (!empty($validated['redeem_wallet_credit'])) {
                $availableWallet = CustomerWalletCredit::getAvailableBalance($user->id);
                $walletCreditApplied = BcMathService::comp($availableWallet, $totalBeforeWallet) > 0
                    ? $totalBeforeWallet
                    : $availableWallet;
            }

            $grandTotal = BcMathService::sub($totalBeforeWallet, $walletCreditApplied);

            if (bccomp($grandTotal, '100000000.00', 2) > 0) {
                return [
                    'error' => true,
                    'message' => 'Payable amount exceeds Paystack maximum single transaction limit of ₦100,000,000.00.',
                ];
            }

            $isFullyWalletPaid = (BcMathService::comp($grandTotal, '0.00') === 0);
            $paymentMethod = $isFullyWalletPaid ? 'wallet_credit' : ($validated['payment_method'] ?? 'paystack');
            $orderStatus = $isFullyWalletPaid ? 'confirmed' : 'pending';
            $paymentStatus = $isFullyWalletPaid ? 'paid' : 'pending';
            $paidAt = $isFullyWalletPaid ? Carbon::now() : null;

            // Assign active accredited courier from fleet if collection point assigned or doorstep delivery
            $rider = null;
            $logisticsCompanyId = $validated['logistics_company_id'] ?? null;
            $riderId = $validated['rider_id'] ?? null;

            if ($riderId) {
                $rider = RiderProfile::withTrashed()->with(['user', 'logisticsCompany.user'])->find($riderId);
                if (!$rider || $rider->trashed() || !$rider->is_active || ($rider->user && $rider->user->status !== 'active')) {
                    return [
                        'error' => true,
                        'message' => 'The designated delivery rider is currently inactive or suspended.',
                    ];
                }
                if ($rider->logisticsCompany && ($rider->logisticsCompany->trashed() || $rider->logisticsCompany->status !== 'active' || ($rider->logisticsCompany->user && $rider->logisticsCompany->user->status !== 'active'))) {
                    return [
                        'error' => true,
                        'message' => 'The logistics fleet for this delivery rider is currently deactivated or suspended.',
                    ];
                }
                if (!$logisticsCompanyId && $rider->logistics_company_id) {
                    $logisticsCompanyId = $rider->logistics_company_id;
                }
            }

            if ($collectionPointId) {
                $cp = LogisticsCollectionPoint::find($collectionPointId);
                if ($cp && $cp->logistics_company_id) {
                    if (!$logisticsCompanyId) {
                        $logisticsCompanyId = $cp->logistics_company_id;
                    }
                    if (!$rider) {
                        $rider = RiderProfile::where('logistics_company_id', $cp->logistics_company_id)
                            ->where('is_active', true)
                            ->where('is_online', true)
                            ->whereHas('user', fn($q) => $q->where('status', 'active'))
                            ->whereHas('logisticsCompany', fn($q) => $q->whereNull('deleted_at')->where('status', 'active'))
                            ->first()
                            ?? RiderProfile::where('logistics_company_id', $cp->logistics_company_id)
                                ->where('is_active', true)
                                ->whereHas('user', fn($q) => $q->where('status', 'active'))
                                ->whereHas('logisticsCompany', fn($q) => $q->whereNull('deleted_at')->where('status', 'active'))
                                ->first();
                    }
                }
            }

            if (!$rider && $validated['fulfillment_type'] === 'doorstep_delivery') {
                $riderQuery = RiderProfile::where('is_active', true)
                    ->whereHas('user', fn($q) => $q->where('status', 'active'))
                    ->whereHas('logisticsCompany', fn($q) => $q->whereNull('deleted_at')->where('status', 'active'));

                if ($logisticsCompanyId) {
                    $riderQuery->where('logistics_company_id', $logisticsCompanyId);
                }

                $rider = (clone $riderQuery)->where('is_online', true)->first() ?? $riderQuery->first();
                if ($rider && $rider->logistics_company_id && !$logisticsCompanyId) {
                    $logisticsCompanyId = $rider->logistics_company_id;
                }
            }

            if (!$logisticsCompanyId && $laneId) {
                $lane = DeliveryLane::find($laneId);
                if ($lane && $lane->assigned_logistics_company_id) {
                    $logisticsCompanyId = $lane->assigned_logistics_company_id;
                }
            }

            // Post-resolution check on logisticsCompanyId
            if ($logisticsCompanyId) {
                $logistics = LogisticsCompanyProfile::withTrashed()->with('user')->find($logisticsCompanyId);
                if (!$logistics || $logistics->trashed() || $logistics->status !== 'active') {
                    return [
                        'error' => true,
                        'message' => 'The selected logistics company is currently inactive or suspended.',
                    ];
                }
                if ($logistics->user && $logistics->user->status !== 'active') {
                    return [
                        'error' => true,
                        'message' => 'The logistics company user account is inactive.',
                    ];
                }
            }

            $orderNumber = 'VM-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            $paymentRef = Order::generateUniquePaymentReference();

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $user->id,
                'vendor_id' => $shop->vendor_id,
                'shop_id' => $shop->id,
                'collection_point_id' => $collectionPointId,
                'rider_id' => $rider ? $rider->id : null,
                'logistics_company_id' => $logisticsCompanyId,
                'fulfillment_type' => $validated['fulfillment_type'],
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'paid_at' => $paidAt,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentRef,
                'guest_access_token' => bin2hex(random_bytes(32)),
                'origin_lga_id' => $shop->lga_id,
                'destination_lga_id' => $destLgaId,
                'delivery_lane_id' => $laneId,
                'merchandise_subtotal' => $merchandiseSubtotal,
                'delivery_fee' => $deliveryFee,
                'cashback_redeemed' => $cashbackApplied,
                'wallet_credit_applied' => $walletCreditApplied,
                'grand_total' => $grandTotal,
                'vendor_net_90' => $vendorNet90,
                'customer_cashback_5' => $customerCashback5,
                'platform_margin_5' => $platformMargin5,
                'logistics_fee_retained' => $logisticsNet90,
                'platform_delivery_fee' => $platformDelivery10,
                'pickup_verification_code' => Order::generateUniquePickupOtp(),
                'verification_code' => Order::generateUniqueDeliveryOtp(),
                'vendor_settlement_status' => 'held',
                'expires_at' => Carbon::now()->addMinutes(30),
            ]);

            // Atomic Cashback Pre-Payment Reservation
            if (bccomp($cashbackApplied, '0.00', 2) > 0) {
                CashbackReservation::create([
                    'customer_id' => $user->id,
                    'order_id' => $order->id,
                    'amount' => $cashbackApplied,
                    'status' => $isFullyWalletPaid ? 'consumed' : 'reserved',
                    'expires_at' => Carbon::now()->addMinutes(30),
                ]);
            }

            // Deduct inventory and insert items
            foreach ($itemsToCreate as $itemData) {
                $itemData['product']->decrement('inventory_quantity', $itemData['quantity']);
                $itemData['product']->is_in_stock = ($itemData['product']->inventory_quantity > 0);
                $itemData['product']->save();

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $itemData['product']->id,
                    'product_name' => $itemData['product']->name,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'line_total' => $itemData['line_total'],
                ]);
            }

            // If order is 100% paid via wallet credit, settle double-entry books immediately!
            if ($isFullyWalletPaid) {
                CustomerWalletCredit::spendWalletBalance($user->id, $order->id, $walletCreditApplied);

                // Debit: Customer Wallet Liability
                FinancialJournalEntry::create([
                    'order_id' => $order->id,
                    'transaction_reference' => $paymentRef,
                    'event_id' => 'EVT-WALLET-FULL-' . $paymentRef,
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => $user->id,
                    'account_name' => 'customer_wallet_liability',
                    'entry_type' => 'debit',
                    'amount' => $walletCreditApplied,
                    'currency' => 'NGN',
                    'description' => "100% Wallet refund credit redemption for Order #{$order->order_number}",
                ]);

                // Debit: Cashback Liability Redeemed (if any)
                if (bccomp($cashbackApplied, '0.00', 2) > 0) {
                    FinancialJournalEntry::create([
                        'order_id' => $order->id,
                        'transaction_reference' => $paymentRef,
                        'event_id' => 'EVT-CB-RED-' . $paymentRef,
                        'beneficiary_type' => User::class,
                        'beneficiary_id' => $user->id,
                        'account_name' => 'cashback_liability',
                        'entry_type' => 'debit',
                        'amount' => $cashbackApplied,
                        'currency' => 'NGN',
                        'description' => "Customer cashback redeemed for Order #{$order->order_number}",
                    ]);
                }

                // Credit: Vendor Payable (90% Merchandise Net)
                FinancialJournalEntry::create([
                    'order_id' => $order->id,
                    'transaction_reference' => $paymentRef,
                    'event_id' => 'EVT-VND-ESCROW-' . $order->id . '-' . $paymentRef,
                    'beneficiary_type' => VendorProfile::class,
                    'beneficiary_id' => $shop->vendor_id,
                    'account_name' => 'vendor_payable',
                    'entry_type' => 'credit',
                    'amount' => $vendorNet90,
                    'currency' => 'NGN',
                    'description' => "Merchant 90% Net Payable held in escrow for Order #{$order->order_number}",
                ]);

                // Credit: Customer Cashback Liability (5% Merchandise Split)
                if (bccomp($customerCashback5, '0.00', 2) > 0) {
                    FinancialJournalEntry::create([
                        'order_id' => $order->id,
                        'transaction_reference' => $paymentRef,
                        'event_id' => 'EVT-CB-ACCRUE-' . $order->id . '-' . $paymentRef,
                        'beneficiary_type' => User::class,
                        'beneficiary_id' => $user->id,
                        'account_name' => 'cashback_liability',
                        'entry_type' => 'credit',
                        'amount' => $customerCashback5,
                        'currency' => 'NGN',
                        'description' => "Customer 5% Cashback Reward Liability accrued for Order #{$order->order_number}",
                    ]);

                    CustomerCashbackLedger::updateOrCreate([
                        'order_id' => $order->id,
                    ], [
                        'customer_id' => $user->id,
                        'amount' => $customerCashback5,
                        'status' => 'pending_maturation',
                        'matures_at' => Carbon::now()->addDays(365),
                        'expires_at' => Carbon::now()->addDays(730),
                    ]);
                }

                // Credit: Platform Revenue (5% Merchandise Cut)
                if (bccomp($platformMargin5, '0.00', 2) > 0) {
                    FinancialJournalEntry::create([
                        'order_id' => $order->id,
                        'transaction_reference' => $paymentRef,
                        'event_id' => 'EVT-PLT-REV-' . $order->id . '-' . $paymentRef,
                        'beneficiary_type' => User::class,
                        'beneficiary_id' => null,
                        'account_name' => 'platform_revenue',
                        'entry_type' => 'credit',
                        'amount' => $platformMargin5,
                        'currency' => 'NGN',
                        'description' => "Victorious Market 5% Platform Revenue Commission for Order #{$order->order_number}",
                    ]);
                }

                // Credit: Logistics Fleet Payable (90% Delivery Net)
                if (bccomp($logisticsNet90, '0.00', 2) > 0 && $logisticsCompanyId) {
                    FinancialJournalEntry::create([
                        'order_id' => $order->id,
                        'transaction_reference' => $paymentRef,
                        'event_id' => 'EVT-LOG-ESCROW-' . $order->id . '-' . $paymentRef,
                        'beneficiary_type' => LogisticsCompanyProfile::class,
                        'beneficiary_id' => $logisticsCompanyId,
                        'account_name' => 'logistics_payable',
                        'entry_type' => 'credit',
                        'amount' => $logisticsNet90,
                        'currency' => 'NGN',
                        'description' => "Logistics 90% Delivery Fee held in escrow for Order #{$order->order_number}",
                    ]);
                }

                // Credit: Platform Delivery Commission (10% Delivery Cut)
                if (bccomp($platformDelivery10, '0.00', 2) > 0) {
                    FinancialJournalEntry::create([
                        'order_id' => $order->id,
                        'transaction_reference' => $paymentRef,
                        'event_id' => 'EVT-PLT-DELIV-' . $order->id . '-' . $paymentRef,
                        'beneficiary_type' => User::class,
                        'beneficiary_id' => null,
                        'account_name' => 'platform_revenue',
                        'entry_type' => 'credit',
                        'amount' => $platformDelivery10,
                        'currency' => 'NGN',
                        'description' => "Victorious Market 10% Delivery Logistics Platform Fee for Order #{$order->order_number}",
                    ]);
                }
            }

            return ['error' => false, 'order' => $order];
        });

        if (!empty($result['error'])) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => $result['message'],
            ];
        }

        $order = $result['order'];

        // Initialize Paystack Checkout Transaction OUTSIDE the DB transaction (only if cash payable > ₦0.00)
        $paystackInit = null;
        if ($order->payment_method === 'paystack' && bccomp((string)$order->grand_total, '0.00', 2) > 0) {
            $callbackUrl = route('storefront') . '?order_success=' . $order->order_number;
            $paystackInit = $this->paystackService->initializeTransaction($order, $callbackUrl, $user->email);
        }

        $message = ($order->payment_status === 'paid')
            ? 'Order created and paid in full with refund wallet credit.'
            : 'Order created successfully. Proceed to payment.';

        return [
            'success' => true,
            'message' => $message,
            'order' => $order,
            'paystack' => $paystackInit,
        ];
    }
}
