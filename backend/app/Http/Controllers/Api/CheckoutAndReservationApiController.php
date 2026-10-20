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
            'redeem_wallet_credit' => 'prohibited',
            'rider_id' => 'prohibited',
            'logistics_company_id' => 'prohibited',
        ], [
            'items.*.quantity.max' => 'Maximum allowed quantity per line item is 1,000 units.',
        ]);

        $user = $request->user();

        // An idempotency key is optional for backward compatibility; clients should
        // send the same key for every retry of one logical checkout attempt.
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey !== null) {
            $idempotencyKey = trim($idempotencyKey);
            if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
                return response()->json([
                    'status' => false,
                    'message' => 'Idempotency-Key must contain between 1 and 100 characters.',
                ], 422);
            }

            $canonicalRequest = [
                'fulfillment_type' => $validated['fulfillment_type'],
                'customer_address_id' => $validated['customer_address_id'] ?? null,
                'shop_id' => (int) $validated['shop_id'],
                'items' => array_map(static function (array $item): array {
                    return [
                        'product_id' => (int) $item['product_id'],
                        'quantity' => (int) $item['quantity'],
                    ];
                }, $validated['items']),
                'redeem_cashback' => (bool) ($validated['redeem_cashback'] ?? false),
            ];
            usort($canonicalRequest['items'], static fn (array $a, array $b): int =>
                [$a['product_id'], $a['quantity']] <=> [$b['product_id'], $b['quantity']]
            );
            $idempotencyRequestHash = hash('sha256', json_encode($canonicalRequest));

            $existingIntent = CheckoutIntent::where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingIntent) {
                if (!hash_equals((string) $existingIntent->idempotency_request_hash, $idempotencyRequestHash)) {
                    return response()->json([
                        'status' => false,
                        'message' => 'This Idempotency-Key was already used for a different checkout request.',
                    ], 409);
                }

                if ($existingIntent->isExpired() || $existingIntent->status === 'expired') {
                    return response()->json([
                        'status' => false,
                        'message' => 'This checkout attempt has expired. Start a new attempt with a new Idempotency-Key.',
                    ], 409);
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Existing checkout intent returned for idempotent retry.',
                    'data' => [
                        'intent_id' => $existingIntent->id,
                        'fingerprint' => $existingIntent->fingerprint,
                        'merchandise_subtotal' => $existingIntent->merchandise_subtotal,
                        'delivery_fee' => $existingIntent->delivery_fee,
                        'cashback_applied' => $existingIntent->cashback_applied,
                        'wallet_credit_applied' => $existingIntent->wallet_credit_applied,
                        'total_payable' => $existingIntent->total_payable,
                        'expires_at' => $existingIntent->expires_at->toIso8601String(),
                    ],
                ], 200);
            }
        }

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

        // Rider and central fleet assignment are deliberately deferred until after checkout.

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

        // Legacy refund wallet credit is not checkout tender at launch.
        // Cashback is the only customer balance that may reduce merchandise subtotal.
        $subtotalAfterCashback = BcMathService::sub($merchandiseSubtotal, $cashbackApplied);
        $totalPayable = BcMathService::add($subtotalAfterCashback, $deliveryFee);
        $walletCreditApplied = '0.00';

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

        try {
            $intent = CheckoutIntent::create([
            'user_id' => $user->id,
            'idempotency_key' => $idempotencyKey,
            'idempotency_request_hash' => $idempotencyKey !== null ? $idempotencyRequestHash : null,
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
        } catch (\\Illuminate\\Database\\QueryException $exception) {
            // The unique (user_id, idempotency_key) constraint is the concurrency
            // arbiter. If a simultaneous retry won the insert, replay that row.
            if ($idempotencyKey !== null) {
                $existingIntent = CheckoutIntent::where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingIntent) {
                    if (hash_equals((string) $existingIntent->idempotency_request_hash, $idempotencyRequestHash)) {
                        return response()->json([
                            'status' => true,
                            'message' => 'Existing checkout intent returned for idempotent retry.',
                            'data' => [
                                'intent_id' => $existingIntent->id,
                                'fingerprint' => $existingIntent->fingerprint,
                                'merchandise_subtotal' => $existingIntent->merchandise_subtotal,
                                'delivery_fee' => $existingIntent->delivery_fee,
                                'cashback_applied' => $existingIntent->cashback_applied,
                                'wallet_credit_applied' => $existingIntent->wallet_credit_applied,
                                'total_payable' => $existingIntent->total_payable,
                                'expires_at' => $existingIntent->expires_at->toIso8601String(),
                            ],
                        ], 200);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => 'This Idempotency-Key was already used for a different checkout request.',
                    ], 409);
                }
            }

            throw $exception;
        }

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

    public function showPickupReservation(Request $request, string $code): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Authentication is required to view pickup reservation details.',
            ], 401);
        }

        $reservation = PickupReservation::where('reservation_code', strtoupper(trim($code)))
            ->where('customer_id', $user->id)
            ->with(['shop:id,name,address,lga_id', 'product:id,name,thumbnail,unit_price'])
            ->first();

        if (!$reservation) {
            return response()->json(['status' => false, 'message' => 'Reservation not found or unauthorized access.'], 404);
        }

        // Never serialize the model wholesale: it can contain customer handover
        // OTPs and internal reservation/payment metadata. Return only the fields
        // required for customer-facing reservation status.
        return response()->json([
            'status' => true,
            'data' => [
                'reservation_code' => $reservation->reservation_code,
                'status' => $reservation->status,
                'quantity' => $reservation->quantity,
                'total_amount' => $reservation->total_amount,
                'expires_at' => $reservation->expires_at,
                'created_at' => $reservation->created_at,
                'shop' => $reservation->shop ? [
                    'id' => $reservation->shop->id,
                    'name' => $reservation->shop->name,
                    'address' => $reservation->shop->address,
                ] : null,
                'product' => $reservation->product ? [
                    'id' => $reservation->product->id,
                    'name' => $reservation->product->name,
                    'thumbnail' => $reservation->product->thumbnail,
                    'unit_price' => $reservation->product->unit_price,
                ] : null,
            ],
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
            ->with(['shop:id,name,address,lga_id', 'product:id,name,thumbnail,unit_price', 'order:id,order_number,order_status,payment_status'])
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
            'message' => 'Pickup payment initialized. After payment is verified, open your order status page for the customer pickup OTP. Keep your reservation pass for the cashier.',
            'data' => [
                'order_number' => $order->order_number,
                'reservation_code' => $code,
                'grand_total' => $order->grand_total,
                'paystack' => $paystackInit,
            ],
        ], 200);
    }

    public function cancelPickupReservation(Request $request, string $code): JsonResponse
    {
        $user = $request->user();
        $result = DB::transaction(function () use ($user, $code) {
            $reservation = PickupReservation::where('customer_id', $user->id)
                ->where('reservation_code', strtoupper(trim($code)))
                ->lockForUpdate()
                ->first();

            if (!$reservation) {
                return ['success' => false, 'code' => 404, 'message' => 'Reservation not found.'];
            }

            // Payment/order creation locks this same reservation row. Re-check its
            // current state after acquiring the lock so cancellation cannot race a
            // successful pay transition and leave a paid order with a canceled hold.
            if (in_array($reservation->status, ['order_placed', 'canceled', 'cancelled', 'expired', 'payment_completed'], true)) {
                return ['success' => false, 'code' => 422, 'message' => "Reservation cannot be cancelled in status {$reservation->status}."];
            }

            $reservation->status = 'canceled';
            $reservation->save();

            return ['success' => true, 'code' => 200, 'message' => 'Pickup reservation cancelled successfully.'];
        });

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
        ], $result['code']);
    }
}
