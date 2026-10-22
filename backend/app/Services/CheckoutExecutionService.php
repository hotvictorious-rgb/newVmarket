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
use App\Models\OrderSupplierAssignment;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class CheckoutExecutionService
{
    public function __construct(
        protected FulfillmentAvailabilityService $fulfillmentService,
        protected PaystackService $paystackService,
        protected ?SourcingAllocationService $sourcingService = null
    ) {
        $this->sourcingService = $sourcingService ?? app(SourcingAllocationService::class);
    }

    /**
     * Authoritative Order Checkout Execution Engine.
     * Pessimistically locks catalog stock, applies cashback/wallet credits,
     * routes logistics, and prepares Paystack payment intent.
     */
    public function execute(User $user, array $validated): array
    {
        if (($validated['fulfillment_type'] ?? null) !== 'doorstep_delivery') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Standard online checkout supports delivery only. Contact Victorious MARKET support to ask whether a pickup exception can be arranged.',
            ];
        }

        // Launch policy: legacy refund wallet credit is not checkout tender.
        // Cashback redemption is handled separately and remains subject to its 50% cap.
        if (!empty($validated['redeem_wallet_credit']) || (($validated['payment_method'] ?? null) === 'wallet_credit')) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Wallet credit cannot be used to pay for orders at launch. Use an approved Paystack payment method or reserve for in-shop payment through the authorised workflow.',
            ];
        }

        // Durable checkout retry guard. The unique DB key is the concurrency arbiter.
        $idempotencyKey = $validated['_checkout_idempotency_key'] ?? null;
        $requestHash = $validated['_checkout_request_hash'] ?? null;
        if ($idempotencyKey !== null) {
            $existingOrder = Order::where('customer_id', $user->id)
                ->where('checkout_idempotency_key', $idempotencyKey)
                ->first();

            if ($existingOrder) {
                if (!hash_equals((string) $existingOrder->checkout_request_hash, (string) $requestHash)) {
                    return [
                        'success' => false,
                        'status_code' => 409,
                        'message' => 'This Idempotency-Key was already used for different order checkout details.',
                    ];
                }

                return $this->replayExistingOrder($existingOrder);
            }
        }

        // Guard 0: Customer Account Invariant
        if ($user && $user->status !== 'active') {
            return [
                'success' => false,
                'status_code' => 403,
                'message' => 'Your customer account is deactivated or suspended.',
            ];
        }

        // Retailer checkout has no customer-selected supplier/shop. Each line is
        // resolved independently to an eligible private supplier offer inside checkout.
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

        // Launch policy: customers cannot choose a rider or central logistics fleet at checkout.
        // Vendor-managed fulfilment assigns a vendor rider after order acceptance, if one is needed.
        if (!empty($validated['rider_id']) || !empty($validated['logistics_company_id'])) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Customers cannot choose a logistics company or rider. Victorious MARKET assigns an approved logistics company after payment and supplier availability confirmation.',
            ];
        }

        try {
            $result = DB::transaction(function () use ($validated, $user, $idempotencyKey, $requestHash) {
            $merchandiseSubtotal = '0.00';
            $itemsToCreate = [];

            foreach ($validated['items'] as $item) {
                $product = Product::marketplaceEligible()->lockForUpdate()->find($item['product_id']);

                if (!$product) {
                    $rawProduct = Product::find($item['product_id']);
                    return [
                        'error' => true,
                        'status_code' => $rawProduct ? 422 : 404,
                        'message' => $rawProduct
                            ? "Product '{$rawProduct->name}' has expired or is not currently eligible for purchase. The vendor must re-confirm product freshness."
                            : "Product ID {$item['product_id']} not found.",
                    ];
                }

                // Retailer checkout is order-centric: each product may resolve to a
                // different private supplier. The request's legacy shop_id is not a
                // customer-selected supplier assignment.

                if (!$product->is_in_stock || !$product->isFresh()) {
                    return [
                        'error' => true,
                        'message' => "Product '{$product->name}' is currently out of stock.",
                    ];
                }

                if (isset($product->inventory_quantity) && (int)$product->inventory_quantity > 0 && (int)$product->inventory_quantity < $item['quantity']) {
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

                $itemsForSourcing[] = [
                    'product' => $product,
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => $product->unit_price,
                    'line_total' => $lineTotal,
                ];
            }

            $sourcingPlan = $this->sourcingService->resolveOptimalWholeOrderPlan(
                $itemsForSourcing,
                $validated['fulfillment_type']
            );

            if (!$sourcingPlan) {
                return [
                    'error' => true,
                    'status_code' => 422,
                    'message' => 'Cart items are temporarily unavailable because no eligible Uyo supplier can fulfil them.',
                ];
            }

            $itemsToCreate = [];
            foreach ($sourcingPlan['items'] as $planLine) {
                $itemsToCreate[] = [
                    'product' => $planLine['product'],
                    'quantity' => $planLine['quantity'],
                    'unit_price' => $planLine['product']->unit_price,
                    'line_total' => BcMathService::mul($planLine['product']->unit_price, (string) $planLine['quantity']),
                    'resolved_offer' => $planLine['resolved_offer'],
                ];
            }

            // The first selected supplier shop is retained only as a legacy order anchor.
            // Actual supplier identity and cost are snapshotted independently per order item.
            $primaryOffer = $itemsToCreate[0]['resolved_offer'] ?? null;
            $primarySourceShop = $primaryOffer
                ? Shop::withTrashed()->with(['lga.state', 'vendor.user'])->find($primaryOffer['shop_id'])
                : null;
            if (!$primarySourceShop || $primarySourceShop->trashed() || !$primarySourceShop->is_active) {
                return ['error' => true, 'status_code' => 422, 'message' => 'No active Uyo supplier shop is available for this order.'];
            }
            foreach ($itemsToCreate as $itemData) {
                $offer = $itemData['resolved_offer'];
                $sourceShop = Shop::withTrashed()->with(['lga.state', 'vendor.user'])->find($offer['shop_id']);
                if (!$sourceShop || $sourceShop->trashed() || !$sourceShop->is_active) {
                    return ['error' => true, 'status_code' => 422, 'message' => 'Every selected supplier must have an active shop in Uyo, Akwa Ibom State.'];
                }
                $sourceLga = strtolower(trim((string) ($sourceShop->lga->name ?? '')));
                $sourceState = strtolower(trim((string) ($sourceShop->lga->state->name ?? '')));
                $sourceVendor = VendorProfile::withTrashed()->with('user')->find($offer['vendor_id']);
                if (
                    $sourceLga !== 'uyo'
                    || $sourceState !== 'akwa ibom'
                    || (int) $sourceShop->vendor_id !== (int) $offer['vendor_id']
                    || !$sourceVendor
                    || $sourceVendor->trashed()
                    || $sourceVendor->marketplace_status !== 'active'
                    || !$sourceVendor->user
                    || $sourceVendor->user->status !== 'active'
                ) {
                    return ['error' => true, 'status_code' => 422, 'message' => 'Every selected supplier must have an active supplier account and shop in Uyo, Akwa Ibom State.'];
                }
            }

            // Delivery Fee & Corridor Resolution
            $deliveryFee = '0.00';
            $deliveryBaseFee = '0.00';
            $deliveryBulkySurcharge = '0.00';
            $deliveryBulkyQuantity = 0;
            $destLgaId = $primarySourceShop->lga_id;
            $laneId = null;
            $collectionPointId = null;

            if ($validated['fulfillment_type'] === 'doorstep_delivery') {
                if (!empty($validated['customer_address_id'])) {
                    $address = CustomerAddress::where('user_id', $user->id)->findOrFail($validated['customer_address_id']);
                    $destLgaId = $address->lga_id;
                } elseif (!empty($validated['destination_lga_id'])) {
                    $destLgaId = (int)$validated['destination_lga_id'];
                } else {
                    $destLgaId = $primarySourceShop->lga_id;
                }

                // One customer-facing order pays one base route fee. Each physical unit
                // marked keke/bulky adds the configured bulky surcharge once; do not
                // convert the whole order into one shared bulky charge.
                $availability = $this->fulfillmentService->checkDeliveryAvailability($primarySourceShop->id, $destLgaId, 'bike');
                if (!$availability['is_available']) {
                    return [
                        'error' => true,
                        'message' => $availability['message'],
                    ];
                }

                $deliveryBaseFee = (string) $availability['fee'];
                $laneId = $availability['lane_id'] ?? null;
                $bulkySurcharge = (string) ($availability['bulky_surcharge'] ?? '0.00');
                foreach ($itemsToCreate as $it) {
                    $tier = strtolower((string) ($it['product']->package_tier ?? 'bike'));
                    if (in_array($tier, ['keke', 'bulky'], true)) {
                        $deliveryBulkyQuantity += (int) $it['quantity'];
                    }
                }
                $deliveryBulkySurcharge = $bulkySurcharge;
                $deliveryFee = BcMathService::add(
                    $deliveryBaseFee,
                    BcMathService::mul($deliveryBulkySurcharge, (string) $deliveryBulkyQuantity)
                );
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

            // Instant Refund Wallet Credit & Net Merchandise after Cashback
            $subtotalAfterCashback = BcMathService::sub($merchandiseSubtotal, $cashbackApplied);
            $totalBeforeWallet = BcMathService::add($subtotalAfterCashback, $deliveryFee);

            // Retailer accounting: snapshot the actual wholesale cost per item. Supplier payables
            // are cost-based, never a merchandise commission percentage.
            $supplierCostTotal = '0.00';
            foreach ($itemsToCreate as $itemData) {
                $offer = $itemData['resolved_offer'] ?? null;
                $unitCost = $offer['wholesale_cost'] ?? null;
                if ($unitCost === null || BcMathService::comp((string) $unitCost, '0.00') <= 0) {
                    return [
                        'error' => true,
                        'status_code' => 422,
                        'message' => 'The selected supplier offer has no valid wholesale cost snapshot. Checkout cannot proceed.',
                    ];
                }
                $supplierCostTotal = BcMathService::add(
                    $supplierCostTotal,
                    BcMathService::mul((string) $unitCost, (string) $itemData['quantity'])
                );
            }

            // Cashback is 5% of eligible merchandise funded by real money through
            // Paystack after cashback redemption. Delivery is excluded.
            $customerCashback5 = BcMathService::mul($subtotalAfterCashback, '0.05');
            $vendorNet90 = '0.00'; // no new 90% marketplace merchandise commission
            $platformMargin5 = '0.00'; // retailer margin is computed from price - supplier cost - cashback
            $retailerMerchandiseMargin = BcMathService::sub(
                BcMathService::sub($merchandiseSubtotal, $supplierCostTotal),
                $customerCashback5
            );

            // Logistics company payable is decoupled from customer delivery fee.
            // The retired 90% haircut rule is never used.
            $lane = $laneId ? DeliveryLane::find($laneId) : null;
            $defaultCompanyPayable = $lane
                ? BcMathService::add((string) $lane->base_fee, BcMathService::mul((string) $lane->bulky_surcharge, (string) $deliveryBulkyQuantity))
                : $deliveryFee;
            $deliverySubsidy = BcMathService::comp($defaultCompanyPayable, $deliveryFee) > 0
                ? BcMathService::sub($defaultCompanyPayable, $deliveryFee)
                : '0.00';

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
            $paymentMethod = $isFullyWalletPaid ? 'wallet_credit' : ($validated['payment_method'] ?? ($validated['fulfillment_type'] === 'in_shop_pickup' ? 'in_shop_counter' : 'paystack'));
            $orderStatus = $isFullyWalletPaid ? 'confirmed' : ($validated['fulfillment_type'] === 'in_shop_pickup' ? 'ready_for_pickup' : 'pending');
            $paymentStatus = $isFullyWalletPaid ? 'paid' : 'pending';
            $paidAt = $isFullyWalletPaid ? Carbon::now() : null;

            // Customer cannot choose a logistics company or rider; VM assigns an approved company after supplier availability is confirmed.
            $rider = null;
            $logisticsCompanyId = $lane ? $lane->assigned_logistics_company_id : null;

            $orderNumber = 'VM-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            $paymentRef = Order::generateUniquePaymentReference();

            $order = Order::create([
                'order_number' => $orderNumber,
                'checkout_idempotency_key' => $idempotencyKey,
                'checkout_request_hash' => $requestHash,
                'paystack_initialization_status' => 'not_started',
                'customer_id' => $user->id,
                'customer_address_id' => $validated['customer_address_id'] ?? null,
                'vendor_id' => $primarySourceShop->vendor_id,
                'shop_id' => $primarySourceShop->id,
                'collection_point_id' => $collectionPointId,
                'rider_id' => $rider ? $rider->id : null,
                'logistics_company_id' => $logisticsCompanyId,
                'fulfillment_type' => $validated['fulfillment_type'],
                'retailer_model_order' => true,
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'paid_at' => $paidAt,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentRef,
                'guest_access_token' => bin2hex(random_bytes(32)),
                'origin_lga_id' => $primarySourceShop->lga_id,
                'destination_lga_id' => $destLgaId,
                'delivery_lane_id' => $laneId,
                'merchandise_subtotal' => $merchandiseSubtotal,
                'delivery_fee' => $deliveryFee,
                'delivery_base_fee_snapshot' => $deliveryBaseFee,
                'delivery_bulky_surcharge_per_unit_snapshot' => $deliveryBulkySurcharge,
                'delivery_bulky_quantity_snapshot' => $deliveryBulkyQuantity,
                'supplier_cost_total_snapshot' => $supplierCostTotal,
                'fulfillment_supplier_cost_total_snapshot' => $supplierCostTotal,
                'retailer_merchandise_margin' => $retailerMerchandiseMargin,
                'sourcing_plan_locations_count' => $sourcingPlan['locations_count'],
                'logistics_company_payable_default_amount' => $defaultCompanyPayable,
                'logistics_company_payable_amount' => $defaultCompanyPayable,
                'logistics_subsidy_amount' => $deliverySubsidy,
                'cashback_redeemed' => $cashbackApplied,
                'wallet_credit_applied' => $walletCreditApplied,
                'grand_total' => $grandTotal,
                'vendor_net_90' => $vendorNet90,
                'customer_cashback_5' => $customerCashback5,
                'platform_margin_5' => $platformMargin5,
                // Legacy vendor-delivery allocation fields are deliberately zero for
                // retailer orders. New accounting uses logistics_company_payable_amount
                // and the captured-fee delta in OrderSettlementService.
                'logistics_fee_retained' => '0.00',
                'platform_delivery_fee' => '0.00',
                'pickup_verification_code' => Order::generateUniquePickupOtp(),
                'verification_code' => Order::generateUniqueDeliveryOtp(),
                'vendor_settlement_status' => 'held',
                'sourcing_status' => $isFullyWalletPaid ? 'awaiting_supplier_confirmation' : 'awaiting_payment',
                'selected_supplier_id' => null,
                'selected_shop_id' => null,
                'sourced_at' => Carbon::now(),
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

            // Order creation binds the 30-minute payment safety reservation with immutable supplier snapshots
            foreach ($itemsToCreate as $itemData) {
                $resolvedOffer = $itemData['resolved_offer'] ?? null;
                $snapshotData = $this->sourcingService->buildOrderItemSnapshot(
                    $resolvedOffer,
                    $itemData['product'],
                    $itemData['quantity'],
                    (string)$itemData['unit_price'],
                    (string)$itemData['line_total']
                );
                $snapshotData['order_id'] = $order->id;

                OrderItem::create($snapshotData);
            }

            // Record one internal assignment row per distinct supplier shop. Item-level
            // snapshots remain the financial authority; these rows coordinate acceptance.
            $supplierGroups = [];
            foreach ($itemsToCreate as $itemData) {
                $offer = $itemData['resolved_offer'];
                $groupKey = (int) $offer['vendor_id'] . ':' . (int) $offer['shop_id'];
                $supplierGroups[$groupKey] = [
                    'supplier_vendor_id' => (int) $offer['vendor_id'],
                    'supplier_shop_id' => (int) $offer['shop_id'],
                ];
            }
            foreach ($supplierGroups as $supplierGroup) {
                OrderSupplierAssignment::create([
                    'order_id' => $order->id,
                    'supplier_vendor_id' => $supplierGroup['supplier_vendor_id'],
                    'supplier_shop_id' => $supplierGroup['supplier_shop_id'],
                    'status' => $isFullyWalletPaid ? 'awaiting_supplier_confirmation' : 'awaiting_payment',
                    'assigned_at' => $isFullyWalletPaid ? Carbon::now() : null,
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
                    'beneficiary_id' => $primarySourceShop->vendor_id,
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
                        'matures_at' => null, // Starts only after verified fulfilment and the 24-hour claim window.
                        'expires_at' => null, // The 365-day validity period starts at maturity.
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
        } catch (\Illuminate\Database\QueryException $exception) {
            // If another request with this key won the unique insert, return its order.
            if ($idempotencyKey !== null) {
                $existingOrder = Order::where('customer_id', $user->id)
                    ->where('checkout_idempotency_key', $idempotencyKey)
                    ->first();
                if ($existingOrder) {
                    if (!hash_equals((string) $existingOrder->checkout_request_hash, (string) $requestHash)) {
                        return ['success' => false, 'status_code' => 409, 'message' => 'This Idempotency-Key was already used for different order checkout details.'];
                    }
                    return $this->replayExistingOrder($existingOrder);
                }
            }
            throw $exception;
        }

        if (!empty($result['error'])) {
            return [
                'success' => false,
                'error' => true,
                'status_code' => $result['status_code'] ?? 422,
                'message' => $result['message'],
            ];
        }

        $order = $result['order'];

        // Initialize Paystack Checkout Transaction OUTSIDE the DB transaction (only if cash payable > ₦0.00)
        $paystackInit = null;
        if ($order->payment_method === 'paystack' && bccomp((string)$order->grand_total, '0.00', 2) > 0) {
            // Persist "initializing" before the network call. A process crash or timeout
            // must not cause a retry to blindly create a second provider transaction.
            if ($idempotencyKey !== null) {
                $order->forceFill(['paystack_initialization_status' => 'initializing'])->save();
            }

            $callbackUrl = $validated['callback_url']
                ?? (Route::has('payment.paystack.callback') ? route('payment.paystack.callback') : route('storefront') . '?order_success=' . $order->order_number);
            $paystackInit = $this->paystackService->initializeTransaction($order, $callbackUrl, $user->email);

            if ($idempotencyKey !== null) {
                if (!empty($paystackInit['success']) && !empty($paystackInit['authorization_url'])) {
                    $order->forceFill([
                        'paystack_initialization_status' => 'initialized',
                        'paystack_authorization_url' => $paystackInit['authorization_url'],
                        'paystack_access_code' => $paystackInit['access_code'] ?? null,
                    ])->save();
                } else {
                    // Only missing configuration proves no request was sent. Other errors
                    // may be ambiguous, so preserve the key and require reconciliation.
                    $status = (($paystackInit['code'] ?? null) === 'CONFIG_MISSING') ? 'failed' : 'uncertain';
                    $order->forceFill(['paystack_initialization_status' => $status])->save();
                }
            }
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

    /**
     * Replays an existing checkout without creating another order or calling Paystack again.
     * An in-flight/uncertain initialization is surfaced for reconciliation, never retried blindly.
     */
    private function replayExistingOrder(Order $order): array
    {
        $paystack = null;
        if ($order->payment_method === 'paystack' && bccomp((string) $order->grand_total, '0.00', 2) > 0) {
            if ($order->paystack_initialization_status === 'initialized' && $order->paystack_authorization_url) {
                $paystack = [
                    'success' => true,
                    'authorization_url' => $order->paystack_authorization_url,
                    'access_code' => $order->paystack_access_code,
                    'reference' => $order->payment_reference,
                ];
            } else {
                $paystack = [
                    'success' => false,
                    'code' => in_array($order->paystack_initialization_status, ['initializing', 'uncertain'], true)
                        ? 'INITIALIZATION_UNCERTAIN'
                        : 'INITIALIZATION_NOT_AVAILABLE',
                    'message' => 'This order already exists. Payment initialization will not be repeated automatically; reconcile its provider state before retrying.',
                ];
            }
        }

        return [
            'success' => true,
            'replayed' => true,
            'http_status' => 200,
            'message' => 'Existing order returned for idempotent checkout retry.',
            'order' => $order,
            'paystack' => $paystack,
        ];
    }

}
