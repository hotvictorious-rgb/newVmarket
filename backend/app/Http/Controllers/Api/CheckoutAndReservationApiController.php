<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CheckoutIntent;
use App\Models\CustomerAddress;
use App\Models\CustomerCashbackLedger;
use App\Models\CustomerWalletCredit;
use App\Models\DeliveryLane;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupReservation;
use App\Models\Product;
use App\Models\Shop;
use App\Services\BcMathService;
use App\Services\FulfillmentAvailabilityService;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutAndReservationApiController extends Controller
{
    protected FulfillmentAvailabilityService $fulfillmentService;
    protected PaystackService $paystackService;

    public function __construct(
        FulfillmentAvailabilityService $fulfillmentService,
        PaystackService $paystackService
    ) {
        $this->fulfillmentService = $fulfillmentService;
        $this->paystackService = $paystackService;
    }

    /**
     * Creates a Two-Phase Checkout Intent with deterministic SHA-256 fingerprint.
     */
    public function createIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fulfillment_type' => 'required|in:doorstep_delivery,in_shop_pickup',
            'customer_address_id' => 'nullable|required_if:fulfillment_type,doorstep_delivery|integer|exists:customer_addresses,id',
            'shop_id' => 'required|integer|exists:shops,id',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
            'redeem_cashback' => 'nullable|boolean',
            'redeem_wallet_credit' => 'nullable|boolean|declined',
            'rider_id' => 'nullable|integer|exists:rider_profiles,id',
            'logistics_company_id' => 'nullable|integer|exists:logistics_company_profiles,id',
        ], [
            'items.*.quantity.max' => 'Maximum allowed quantity per line item is 1,000 units.',
        ]);

        $user = $request->user();

        // Guard 0: Customer Account Invariant
        if ($user && $user->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'Your customer account is deactivated or suspended.',
            ], 403);
        }

        $shop = Shop::withTrashed()->with(['vendor.user'])->findOrFail($validated['shop_id']);

        // Guard 1: Shop Branch Invariant
        if ($shop->trashed() || !$shop->is_active) {
            return response()->json([
                'status' => false,
                'message' => 'The selected shop branch is currently inactive.',
            ], 422);
        }

        // Guard 2: Vendor Entity & User Account Invariant
        if (!$shop->vendor || $shop->vendor->trashed() || $shop->vendor->marketplace_status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'The merchant providing this item is currently not active on the marketplace.',
            ], 422);
        }

        if (!$shop->vendor->user || $shop->vendor->user->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'The merchant user account is inactive.',
            ], 422);
        }

        // Guard 3: Explicit Logistics Company Invariant
        if (!empty($validated['logistics_company_id'])) {
            $logistics = \App\Models\LogisticsCompanyProfile::withTrashed()->with('user')->find($validated['logistics_company_id']);
            if (!$logistics || $logistics->trashed() || $logistics->status !== 'active') {
                return response()->json([
                    'status' => false,
                    'message' => 'The selected logistics company is currently inactive or suspended.',
                ], 422);
            }
            if ($logistics->user && $logistics->user->status !== 'active') {
                return response()->json([
                    'status' => false,
                    'message' => 'The logistics company user account is inactive.',
                ], 422);
            }
        }

        // Guard 4: Explicit Designated Courier / Rider Invariant
        if (!empty($validated['rider_id'])) {
            $rider = \App\Models\RiderProfile::withTrashed()->with(['user', 'vendor.user', 'logisticsCompany.user'])->find($validated['rider_id']);
            if (!$rider || $rider->trashed() || !$rider->is_active || ($rider->user && $rider->user->status !== 'active')) {
                return response()->json([
                    'status' => false,
                    'message' => 'The designated delivery rider is currently inactive or suspended.',
                ], 422);
            }
            if ($rider->vendor_id && (int)$rider->vendor_id !== (int)$shop->vendor_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'The designated delivery rider does not belong to the merchant for this order.',
                ], 422);
            }
            if ($rider->vendor && ($rider->vendor->trashed() || $rider->vendor->marketplace_status !== 'active' || ($rider->vendor->user && $rider->vendor->user->status !== 'active'))) {
                return response()->json([
                    'status' => false,
                    'message' => 'The merchant organization for this delivery rider is currently deactivated or suspended.',
                ], 422);
            }
            if ($rider->logisticsCompany && ($rider->logisticsCompany->trashed() || $rider->logisticsCompany->status !== 'active' || ($rider->logisticsCompany->user && $rider->logisticsCompany->user->status !== 'active'))) {
                return response()->json([
                    'status' => false,
                    'message' => 'The logistics fleet for this delivery rider is currently deactivated or suspended.',
                ], 422);
            }
        }

        // Calculate merchandise subtotal using BCMath
        $merchandiseSubtotal = '0.00';
        $itemsSnapshot = [];

        $orderPackageTier = 'bike';
        foreach ($validated['items'] as $item) {
            $product = Product::marketplaceEligible()->findOrFail($item['product_id']);

            // Multi-tenant check: product must belong to the selected shop branch
            if ((int)$product->shop_id !== (int)$shop->id) {
                return response()->json([
                    'status' => false,
                    'message' => "Product '{$product->name}' does not belong to the selected shop branch.",
                ], 422);
            }

            // Inventory check
            if ($product->inventory_quantity < $item['quantity']) {
                return response()->json([
                    'status' => false,
                    'message' => "Insufficient stock for {$product->name}. Only {$product->inventory_quantity} available.",
                ], 422);
            }

            if (in_array(strtolower($product->package_tier ?? ''), ['keke', 'bulky', 'fragile'], true)) {
                $orderPackageTier = 'keke';
            }

            $lineTotal = BcMathService::mul($product->unit_price, (string)$item['quantity']);
            $merchandiseSubtotal = BcMathService::add($merchandiseSubtotal, $lineTotal);

            $itemsSnapshot[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit_price' => $product->unit_price,
                'quantity' => $item['quantity'],
                'line_total' => $lineTotal,
            ];
        }

        $deliveryFee = '0.00';
        if ($validated['fulfillment_type'] === 'doorstep_delivery') {
            $address = CustomerAddress::where('user_id', $user->id)->findOrFail($validated['customer_address_id']);
            $availability = $this->fulfillmentService->checkDeliveryAvailability($shop->id, $address->lga_id, $orderPackageTier);

            if (!$availability['is_available']) {
                return response()->json([
                    'status' => false,
                    'message' => $availability['message'],
                ], 422);
            }

            $deliveryFee = $availability['fee'];
        }

        // Customer Cashback Redemption check (50% merchandise cap, 24h maturation)
        $cashbackApplied = '0.00';
        if (!empty($validated['redeem_cashback'])) {
            $availableCashback = CustomerCashbackLedger::where('customer_id', $user->id)
                ->where('status', 'available')
                ->where('matures_at', '<=', Carbon::now())
                ->where('expires_at', '>', Carbon::now())
                ->sum('amount');

            $availableCashbackStr = BcMathService::add((string)($availableCashback ?? '0'), '0.00');
            // Cap cashback redemption at 50% of merchandise subtotal or available balance
            $maxRedemption = BcMathService::mul($merchandiseSubtotal, '0.50');
            $cashbackApplied = BcMathService::comp($availableCashbackStr, $maxRedemption) > 0 ? $maxRedemption : $availableCashbackStr;
        }

        // Customer Instant Refund Wallet Credit redemption (100% spendable, covers up to 100% of order payable)
        $subtotalAfterCashback = BcMathService::sub($merchandiseSubtotal, $cashbackApplied);
        $totalBeforeWallet = BcMathService::add($subtotalAfterCashback, $deliveryFee);

        $walletCreditApplied = '0.00';
        if (!empty($validated['redeem_wallet_credit'])) {
            $availableWallet = CustomerWalletCredit::getAvailableBalance($user->id);
            $walletCreditApplied = BcMathService::comp($availableWallet, $totalBeforeWallet) > 0
                ? $totalBeforeWallet
                : $availableWallet;
        }

        $totalPayable = BcMathService::sub($totalBeforeWallet, $walletCreditApplied);

        if (bccomp($totalPayable, '100000000.00', 2) > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Payable amount exceeds Paystack maximum single transaction limit of ₦100,000,000.00.',
            ], 422);
        }

        $payload = [
            'user_id' => $user->id,
            'shop_id' => $shop->id,
            'fulfillment_type' => $validated['fulfillment_type'],
            'address_id' => $validated['customer_address_id'] ?? null,
            'items' => $itemsSnapshot,
            'merchandise_subtotal' => $merchandiseSubtotal,
            'delivery_fee' => $deliveryFee,
            'cashback_applied' => $cashbackApplied,
            'wallet_credit_applied' => $walletCreditApplied,
            'total_payable' => $totalPayable,
            'timestamp' => Carbon::now()->timestamp,
        ];

        $fingerprint = CheckoutIntent::generateFingerprint($payload);

        $intent = CheckoutIntent::create([
            'user_id' => $user->id,
            'fingerprint' => $fingerprint,
            'fulfillment_type' => $validated['fulfillment_type'],
            'customer_address_id' => $validated['customer_address_id'] ?? null,
            'shop_id' => $shop->id,
            'merchandise_subtotal' => $merchandiseSubtotal,
            'delivery_fee' => $deliveryFee,
            'cashback_applied' => $cashbackApplied,
            'wallet_credit_applied' => $walletCreditApplied,
            'total_payable' => $totalPayable,
            'payload_snapshot' => $payload,
            'status' => 'pending',
            'expires_at' => Carbon::now()->addHours(2),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Checkout intent created successfully.',
            'data' => [
                'intent_id' => $intent->id,
                'fingerprint' => $intent->fingerprint,
                'merchandise_subtotal' => $intent->merchandise_subtotal,
                'delivery_fee' => $intent->delivery_fee,
                'cashback_applied' => $intent->cashback_applied,
                'wallet_credit_applied' => $intent->wallet_credit_applied,
                'total_payable' => $intent->total_payable,
                'expires_at' => $intent->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Creates an In-Shop Pickup Reservation (Inspect First, Pay Later Engine).
     */
    public function createPickupReservation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1|max:1000',
        ], [
            'quantity.max' => 'Reservation quantity cannot exceed 1,000 units.',
        ]);

        $user = $request->user();
        if ($user && $user->status !== 'active') {
            return response()->json(['status' => false, 'message' => 'Your customer account is deactivated or suspended.'], 403);
        }

        $shop = Shop::withTrashed()->with('vendor.user')->findOrFail($validated['shop_id']);
        if ($shop->trashed() || !$shop->is_active) {
            return response()->json(['status' => false, 'message' => 'This shop branch is not active.'], 422);
        }
        if (!$shop->pickup_enabled) {
            return response()->json(['status' => false, 'message' => 'This shop does not support in-shop pickup.'], 422);
        }

        if (!$shop->vendor || $shop->vendor->trashed() || $shop->vendor->marketplace_status !== 'active') {
            return response()->json(['status' => false, 'message' => 'The merchant providing this item is currently not active on the marketplace.'], 422);
        }

        if (!$shop->vendor->user || $shop->vendor->user->status !== 'active') {
            return response()->json(['status' => false, 'message' => 'The merchant user account is inactive.'], 422);
        }

        $product = Product::marketplaceEligible()->find($validated['product_id']);
        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'This product listing has expired or is not eligible for pickup reservation. The vendor must re-confirm product freshness.',
            ], 422);
        }

        if ((int)$product->shop_id !== (int)$shop->id) {
            return response()->json(['status' => false, 'message' => 'Product does not belong to the selected pickup shop branch.'], 422);
        }

        if ((int)$shop->vendor_id !== (int)$product->vendor_id) {
            return response()->json(['status' => false, 'message' => 'Shop does not belong to the product merchant.'], 422);
        }

        $quantity = $validated['quantity'] ?? 1;
        if (!$product->is_in_stock) {
            return response()->json([
                'status' => false,
                'message' => "{$product->name} is currently out of stock.",
            ], 422);
        }

        $estimatedTotal = BcMathService::mul($product->unit_price, (string)$quantity);

        $reservation = PickupReservation::create([
            'reservation_code' => PickupReservation::generateCode(),
            'customer_id' => $user->id,
            'vendor_id' => $shop->vendor_id,
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->unit_price,
            'estimated_total' => $estimatedTotal,
            'status' => 'reserved',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Pickup reservation created successfully.',
            'data' => [
                'reservation_code' => $reservation->reservation_code,
                'shop_name' => $shop->name,
                'shop_address' => $shop->address,
                'product_name' => $product->name,
                'quantity' => $reservation->quantity,
                'estimated_total' => $reservation->estimated_total,
                'status' => $reservation->status,
                'expires_at' => $reservation->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    public function showPickupReservation(string $code): JsonResponse
    {
        $reservation = PickupReservation::where('reservation_code', strtoupper(trim($code)))
            ->with(['shop:id,name,address,lga_id', 'product:id,name,thumbnail,unit_price'])
            ->first();

        if (!$reservation) {
            return response()->json(['status' => false, 'message' => 'Reservation code not found.'], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $reservation,
        ]);
    }

    public function cashbackBalance(Request $request): JsonResponse
    {
        return $this->cashbackSummary($request);
    }

    public function cashbackSummary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $available = (float)CustomerCashbackLedger::where('customer_id', $userId)
            ->where('status', 'available')
            ->where('matures_at', '<=', Carbon::now())
            ->where('expires_at', '>', Carbon::now())
            ->sum('amount');

        $pending = (float)CustomerCashbackLedger::where('customer_id', $userId)
            ->where('status', 'pending_maturation')
            ->sum('amount');

        $lifetimeEarned = (float)CustomerCashbackLedger::where('customer_id', $userId)
            ->whereIn('status', ['available', 'redeemed', 'pending_maturation', 'expired'])
            ->where('amount', '>', 0)
            ->sum('amount');

        $lifetimeRedeemed = (float)Order::where('customer_id', $userId)
            ->where('payment_status', 'paid')
            ->sum('cashback_redeemed');

        $pointsBalance = (int)round($available);

        return response()->json([
            'status' => true,
            'summary' => [
                'points_balance' => $pointsBalance,
                'equivalent_naira' => number_format($available, 2, '.', ''),
                'currency' => 'NGN',
                'lifetime_earned_points' => (int)round($lifetimeEarned),
                'lifetime_redeemed_points' => (int)round($lifetimeRedeemed),
                'pending_settlement_points' => (int)round($pending),
            ],
            'data' => [
                'available_cashback' => number_format($available, 2, '.', ''),
                'pending_maturation' => number_format($pending, 2, '.', ''),
                'currency' => 'NGN',
            ],
        ]);
    }

    public function cashbackList(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $limit = $request->integer('limit', 20);

        $entries = CustomerCashbackLedger::where('customer_id', $userId)
            ->with('order:id,order_number')
            ->orderBy('id', 'desc')
            ->paginate($limit);

        return response()->json([
            'status' => true,
            'data' => $entries->items(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    public function listPickupReservations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $reservations = PickupReservation::where('customer_id', $userId)
            ->with(['shop:id,name,address,lga_id', 'product:id,name,thumbnail,unit_price', 'order:id,order_number,pickup_verification_code,order_status,payment_status'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return response()->json([
            'status' => true,
            'reservations' => $reservations->items(),
            'data' => $reservations->items(),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'total' => $reservations->total(),
            ],
        ]);
    }

    public function payPickupReservation(Request $request, string $code): JsonResponse
    {
        $user = $request->user();
        $code = strtoupper(trim($code));

        $result = DB::transaction(function () use ($user, $code, $request) {
            $reservation = PickupReservation::where('customer_id', $user->id)
                ->where('reservation_code', $code)
                ->lockForUpdate()
                ->first();

            if (!$reservation) {
                return ['error' => true, 'code' => 404, 'message' => 'Reservation not found or unauthorized.'];
            }

            if ($reservation->status === 'order_placed') {
                return ['error' => true, 'code' => 422, 'message' => 'An order has already been placed for this reservation.'];
            }

            if ($reservation->status === 'cancelled' || $reservation->status === 'expired') {
                return ['error' => true, 'code' => 422, 'message' => "Reservation is {$reservation->status} and cannot be paid."];
            }

            if ($reservation->status !== 'inspected_accepted') {
                return [
                    'error' => true,
                    'code' => 422,
                    'message' => 'Reservation must be inspected and accepted at merchant shop before payment can be initialized.',
                ];
            }

            $product = Product::marketplaceEligible()->lockForUpdate()->find($reservation->product_id);
            if (!$product) {
                return [
                    'error' => true,
                    'code' => 422,
                    'message' => 'Product has expired or is no longer eligible for checkout. The vendor must re-confirm product freshness.',
                ];
            }
            if ($product->available_quantity < $reservation->quantity) {
                return [
                    'error' => true,
                    'code' => 422,
                    'message' => "Insufficient stock remaining for {$product->name}.",
                ];
            }

            $shop = Shop::withTrashed()->with('vendor.user')->findOrFail($reservation->shop_id);
            if ($shop->trashed() || !$shop->is_active) {
                return [
                    'error' => true,
                    'code' => 422,
                    'message' => 'The selected shop branch is currently inactive.',
                ];
            }

            if (!$shop->vendor || $shop->vendor->trashed() || $shop->vendor->marketplace_status !== 'active') {
                return [
                    'error' => true,
                    'code' => 422,
                    'message' => 'The merchant providing this item is currently not active on the marketplace.',
                ];
            }

            if (!$shop->vendor->user || $shop->vendor->user->status !== 'active') {
                return [
                    'error' => true,
                    'code' => 422,
                    'message' => 'The merchant user account is inactive.',
                ];
            }

            $merchandiseSubtotal = $reservation->estimated_total;

            // Optional cashback redemption with pessimistic balance reservation
            $cashbackApplied = '0.00';
            if ($request->boolean('use_cashback') || $request->boolean('redeem_cashback')) {
                $now = Carbon::now();
                $availableLotsSum = CustomerCashbackLedger::where('customer_id', $user->id)
                    ->where('status', 'available')
                    ->where('matures_at', '<=', $now)
                    ->where('expires_at', '>', $now)
                    ->lockForUpdate()
                    ->sum('amount');

                $activeReservedSum = \App\Models\CashbackReservation::where('customer_id', $user->id)
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

            $totalPayable = BcMathService::sub($merchandiseSubtotal, $cashbackApplied);

            if (bccomp($totalPayable, '100000000.00', 2) > 0) {
                return [
                    'error' => true,
                    'code' => 422,
                    'message' => 'Payable amount exceeds Paystack maximum single transaction limit of ₦100,000,000.00.',
                ];
            }

            $vendorNet90 = BcMathService::mul($merchandiseSubtotal, '0.90');
            $customerCashback5 = BcMathService::mul($merchandiseSubtotal, '0.05');
            $platformMargin5 = BcMathService::mul($merchandiseSubtotal, '0.05');

            $orderNumber = 'VM-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            $paymentRef = Order::generateUniquePaymentReference();
            $pickupOtp = Order::generateUniquePickupOtp();

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $user->id,
                'vendor_id' => $shop->vendor_id,
                'shop_id' => $shop->id,
                'fulfillment_type' => 'in_shop_pickup',
                'order_status' => 'confirmed',
                'payment_status' => 'pending',
                'payment_method' => 'paystack',
                'payment_reference' => $paymentRef,
                'guest_access_token' => bin2hex(random_bytes(32)),
                'origin_lga_id' => $shop->lga_id,
                'destination_lga_id' => $shop->lga_id,
                'merchandise_subtotal' => $merchandiseSubtotal,
                'delivery_fee' => '0.00',
                'cashback_redeemed' => $cashbackApplied,
                'grand_total' => $totalPayable,
                'vendor_net_90' => $vendorNet90,
                'customer_cashback_5' => $customerCashback5,
                'platform_margin_5' => $platformMargin5,
                'pickup_verification_code' => $pickupOtp,
                'verification_code' => Order::generateUniqueDeliveryOtp(),
                'vendor_settlement_status' => 'held',
                'expires_at' => Carbon::now()->addMinutes(30),
            ]);

            // Atomic Cashback Pre-Payment Reservation
            if (bccomp($cashbackApplied, '0.00', 2) > 0) {
                \App\Models\CashbackReservation::create([
                    'customer_id' => $user->id,
                    'order_id' => $order->id,
                    'amount' => $cashbackApplied,
                    'status' => 'reserved',
                    'expires_at' => Carbon::now()->addMinutes(30),
                ]);
            }

            // Verify product availability under the two-status model (In Stock vs Out of Stock)
            if (!$product->is_in_stock) {
                return ['error' => true, 'message' => "Product '{$product->name}' is currently out of stock."];
            }

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $reservation->quantity,
                'unit_price' => $reservation->unit_price,
                'line_total' => $reservation->estimated_total,
            ]);

            // Mark reservation as payment completed and bind order
            $reservation->status = 'payment_completed';
            $reservation->order_id = $order->id;
            $reservation->save();

            return ['error' => false, 'order' => $order, 'reservation' => $reservation];
        });

        if (!empty($result['error'])) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 422);
        }

        $order = $result['order'];
        $callbackUrl = route('storefront') . '?order_success=' . $order->order_number;
        $paystackInit = $this->paystackService->initializeTransaction($order, $callbackUrl, $user->email);

        return response()->json([
            'status' => true,
            'message' => 'Pickup payment initialized. Present 6-digit PIN upon payment completion.',
            'data' => [
                'order_number' => $order->order_number,
                'reservation_code' => $code,
                'pickup_otp' => $order->pickup_verification_code,
                'grand_total' => $order->grand_total,
                'paystack' => $paystackInit,
            ],
        ], 200);
    }

    public function cancelPickupReservation(Request $request, string $code): JsonResponse
    {
        $user = $request->user();
        $reservation = PickupReservation::where('customer_id', $user->id)
            ->where('reservation_code', strtoupper(trim($code)))
            ->first();

        if (!$reservation) {
            return response()->json(['status' => false, 'message' => 'Reservation not found.'], 404);
        }

        if (in_array($reservation->status, ['order_placed', 'canceled', 'cancelled'])) {
            return response()->json(['status' => false, 'message' => "Reservation cannot be cancelled in status {$reservation->status}."], 422);
        }

        $reservation->status = 'canceled';
        $reservation->save();

        return response()->json([
            'status' => true,
            'message' => 'Pickup reservation cancelled successfully.',
        ]);
    }
}
