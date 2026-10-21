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
use App\Models\SupplierOffer;
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
            'fulfillment_type' => 'required|in:doorstep_delivery',
            'customer_address_id' => 'nullable|required_if:fulfillment_type,doorstep_delivery|integer|exists:customer_addresses,id',
            // Legacy clients may send shop_id; it does not select or constrain suppliers.
            'shop_id' => 'nullable|integer|exists:shops,id',
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
                        'delivery_base_fee' => $existingIntent->payload_snapshot['delivery_base_fee'] ?? '0.00',
                        'delivery_bulky_surcharge_per_unit' => $existingIntent->payload_snapshot['delivery_bulky_surcharge_per_unit'] ?? '0.00',
                        'delivery_bulky_quantity' => $existingIntent->payload_snapshot['delivery_bulky_quantity'] ?? 0,
                        'delivery_bulky_surcharge_total' => BcMathService::mul(
                            (string) ($existingIntent->payload_snapshot['delivery_bulky_surcharge_per_unit'] ?? '0.00'),
                            (string) ($existingIntent->payload_snapshot['delivery_bulky_quantity'] ?? 0)
                        ),
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

        // Customer checkout is delivery-only. Supplier shops are resolved privately
        // per item; the legacy request shop_id is ignored for sourcing decisions.

        $merchandiseSubtotal = '0.00';
        $itemsSnapshot = [];
        $primarySourceShop = null;
        $deliveryBulkyQuantity = 0;
        $deliveryBulkySurchargePerUnit = '0.00';
        $sourcingService = app(\App\Services\SourcingAllocationService::class);
        foreach ($validated['items'] as $item) {
            $product = Product::marketplaceEligible()->findOrFail($item['product_id']);

            // Inventory check
            if ($product->inventory_quantity < $item['quantity']) {
                return response()->json([
                    'status' => false,
                    'message' => "Insufficient stock for {$product->name}. Only {$product->inventory_quantity} available.",
                ], 422);
            }

            $resolvedOffer = $sourcingService->resolveCheapestOffer($product, (int) $item['quantity'], null, 'doorstep_delivery');
            if (!$resolvedOffer) {
                return response()->json([
                    'status' => false,
                    'message' => "{$product->name} is temporarily unavailable because no eligible Uyo supplier can fulfil it.",
                ], 422);
            }

            $sourceShop = Shop::withTrashed()->with(['lga.state', 'vendor.user'])->find($resolvedOffer['shop_id']);
            if (!$sourceShop || $sourceShop->trashed() || !$sourceShop->is_active) {
                return response()->json(['status' => false, 'message' => 'An eligible Uyo supplier shop is unavailable.'], 422);
            }
            $sourceLga = strtolower(trim((string) ($sourceShop->lga->name ?? '')));
            $sourceState = strtolower(trim((string) ($sourceShop->lga->state->name ?? '')));
            $sourceVendor = \App\Models\VendorProfile::withTrashed()->with('user')->find($resolvedOffer['vendor_id']);
            if ($sourceLga !== 'uyo' || $sourceState !== 'akwa ibom'
                || (int) $sourceShop->vendor_id !== (int) $resolvedOffer['vendor_id']
                || !$sourceVendor || $sourceVendor->trashed() || $sourceVendor->marketplace_status !== 'active'
                || !$sourceVendor->user || $sourceVendor->user->status !== 'active') {
                return response()->json(['status' => false, 'message' => 'Every supplier must have an active supplier account and shop in Uyo, Akwa Ibom State.'], 422);
            }
            $primarySourceShop ??= $sourceShop;
            if (in_array(strtolower((string) ($product->package_tier ?? 'bike')), ['keke', 'bulky'], true)) {
                $deliveryBulkyQuantity += (int) $item['quantity'];
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
        $deliveryBaseFee = '0.00';
        if (!$primarySourceShop) {
            return response()->json(['status' => false, 'message' => 'No eligible supplier is available for this order.'], 422);
        }
        $address = CustomerAddress::where('user_id', $user->id)->findOrFail($validated['customer_address_id']);
        $availability = $this->fulfillmentService->checkDeliveryAvailability($primarySourceShop->id, $address->lga_id, 'bike');
        if (!$availability['is_available']) {
            return response()->json(['status' => false, 'message' => $availability['message']], 422);
        }
        $deliveryBaseFee = (string) $availability['fee'];
        $deliveryBulkySurchargePerUnit = (string) ($availability['bulky_surcharge'] ?? '0.00');
        $deliveryFee = BcMathService::add(
            $deliveryBaseFee,
            BcMathService::mul($deliveryBulkySurchargePerUnit, (string) $deliveryBulkyQuantity)
        );

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
            'shop_id' => $primarySourceShop->id,
            'fulfillment_type' => $validated['fulfillment_type'],
            'address_id' => $validated['customer_address_id'] ?? null,
            'items' => $itemsSnapshot,
            'merchandise_subtotal' => $merchandiseSubtotal,
            'delivery_fee' => $deliveryFee,
            'delivery_base_fee' => $deliveryBaseFee,
            'delivery_bulky_surcharge_per_unit' => $deliveryBulkySurchargePerUnit,
            'delivery_bulky_quantity' => $deliveryBulkyQuantity,
            'cashback_applied' => $cashbackApplied,
            'wallet_credit_applied' => $walletCreditApplied,
            'total_payable' => $totalPayable,
            'timestamp' => Carbon::now()->timestamp,
            // Keep the globally unique fingerprint distinct for intentional separate attempts
            // created within the same second with different idempotency keys.
            'intent_nonce' => (string) Str::uuid(),
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
            'shop_id' => $primarySourceShop->id,
            'merchandise_subtotal' => $merchandiseSubtotal,
            'delivery_fee' => $deliveryFee,
            'cashback_applied' => $cashbackApplied,
            'wallet_credit_applied' => $walletCreditApplied,
            'total_payable' => $totalPayable,
            'payload_snapshot' => $payload,
            'status' => 'pending',
            'expires_at' => Carbon::now()->addHours(2),
            ]);
        } catch (\Illuminate\Database\QueryException $exception) {
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
                                'delivery_base_fee' => $existingIntent->payload_snapshot['delivery_base_fee'] ?? '0.00',
                                'delivery_bulky_surcharge_per_unit' => $existingIntent->payload_snapshot['delivery_bulky_surcharge_per_unit'] ?? '0.00',
                                'delivery_bulky_quantity' => $existingIntent->payload_snapshot['delivery_bulky_quantity'] ?? 0,
                                'delivery_bulky_surcharge_total' => BcMathService::mul(
                                    (string) ($existingIntent->payload_snapshot['delivery_bulky_surcharge_per_unit'] ?? '0.00'),
                                    (string) ($existingIntent->payload_snapshot['delivery_bulky_quantity'] ?? 0)
                                ),
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
                'delivery_base_fee' => $intent->payload_snapshot['delivery_base_fee'] ?? '0.00',
                'delivery_bulky_surcharge_per_unit' => $intent->payload_snapshot['delivery_bulky_surcharge_per_unit'] ?? '0.00',
                'delivery_bulky_quantity' => $intent->payload_snapshot['delivery_bulky_quantity'] ?? 0,
                'delivery_bulky_surcharge_total' => BcMathService::mul(
                    (string) ($intent->payload_snapshot['delivery_bulky_surcharge_per_unit'] ?? '0.00'),
                    (string) ($intent->payload_snapshot['delivery_bulky_quantity'] ?? 0)
                ),
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
        return response()->json([
            'status' => false,
            'code' => 'SUPPORT_MANAGED_PICKUP_ONLY',
            'message' => 'Public pickup reservations are disabled. Please call or WhatsApp Victorious MARKET support to ask whether pickup can be arranged for your complete order.',
        ], 410);
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
            ->with(['product:id,name,thumbnail,unit_price'])
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
            ->with(['product:id,name,thumbnail,unit_price', 'order:id,order_number,order_status,payment_status'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        $data = $reservations->getCollection()->map(function (PickupReservation $reservation) {
            return [
                'reservation_code' => $reservation->reservation_code,
                'status' => $reservation->status,
                'quantity' => $reservation->quantity,
                'estimated_total' => $reservation->estimated_total,
                'expires_at' => $reservation->expires_at,
                'created_at' => $reservation->created_at,
                'product' => $reservation->product ? [
                    'name' => $reservation->product->name,
                    'thumbnail' => $reservation->product->thumbnail,
                    'unit_price' => $reservation->product->unit_price,
                ] : null,
                'order' => $reservation->order ? [
                    'order_number' => $reservation->order->order_number,
                    'order_status' => $reservation->order->order_status,
                    'payment_status' => $reservation->order->payment_status,
                ] : null,
                'support_message' => 'Contact Victorious MARKET support before travelling to any collection point.',
            ];
        });

        return response()->json([
            'status' => true,
            'reservations' => $data,
            'data' => $data,
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'total' => $reservations->total(),
            ],
        ]);
    }

    public function payPickupReservation(Request $request, string $code): JsonResponse
    {
        return response()->json([
            'status' => false,
            'code' => 'SUPPORT_MANAGED_PICKUP_ONLY',
            'message' => 'Public pickup reservation payment is disabled. Contact Victorious MARKET support to arrange any approved pickup exception.',
        ], 410);
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
