<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CheckoutIntent;
use App\Models\CustomerAddress;
use App\Models\CustomerCashbackLedger;
use App\Models\DeliveryLane;
use App\Models\PickupReservation;
use App\Models\Product;
use App\Models\Shop;
use App\Services\BcMathService;
use App\Services\FulfillmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutAndReservationApiController extends Controller
{
    protected FulfillmentAvailabilityService $fulfillmentService;

    public function __construct(FulfillmentAvailabilityService $fulfillmentService)
    {
        $this->fulfillmentService = $fulfillmentService;
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
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'redeem_cashback' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $shop = Shop::findOrFail($validated['shop_id']);

        // Calculate merchandise subtotal using BCMath
        $merchandiseSubtotal = '0.00';
        $itemsSnapshot = [];

        foreach ($validated['items'] as $item) {
            $product = Product::marketplaceEligible()->findOrFail($item['product_id']);
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
            $availability = $this->fulfillmentService->checkDeliveryAvailability($shop->id, $address->lga_id);

            if (!$availability['is_available']) {
                return response()->json([
                    'status' => false,
                    'message' => $availability['message'],
                ], 422);
            }

            $deliveryFee = $availability['fee'];
        }

        // Customer Cashback Redemption check
        $cashbackApplied = '0.00';
        if (!empty($validated['redeem_cashback'])) {
            $availableCashback = CustomerCashbackLedger::where('customer_id', $user->id)
                ->where('status', 'available')
                ->where('expires_at', '>', Carbon::now())
                ->sum('amount');

            $availableCashbackStr = number_format((float)$availableCashback, 2, '.', '');
            // Cap cashback redemption at 50% of merchandise subtotal or available balance
            $maxRedemption = BcMathService::mul($merchandiseSubtotal, '0.50');
            $cashbackApplied = BcMathService::comp($availableCashbackStr, $maxRedemption) > 0 ? $maxRedemption : $availableCashbackStr;
        }

        $totalPayable = BcMathService::add(BcMathService::sub($merchandiseSubtotal, $cashbackApplied), $deliveryFee);

        $payload = [
            'user_id' => $user->id,
            'shop_id' => $shop->id,
            'fulfillment_type' => $validated['fulfillment_type'],
            'address_id' => $validated['customer_address_id'] ?? null,
            'items' => $itemsSnapshot,
            'merchandise_subtotal' => $merchandiseSubtotal,
            'delivery_fee' => $deliveryFee,
            'cashback_applied' => $cashbackApplied,
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
            'quantity' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();
        $shop = Shop::findOrFail($validated['shop_id']);
        if (!$shop->pickup_enabled) {
            return response()->json(['status' => false, 'message' => 'This shop does not support in-shop pickup.'], 422);
        }

        $product = Product::marketplaceEligible()->findOrFail($validated['product_id']);
        $quantity = $validated['quantity'] ?? 1;
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
            ->with(['shop:id,name,address,phone,lga_id', 'product:id,name,thumbnail,unit_price'])
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
        $userId = $request->user()->id;

        $available = CustomerCashbackLedger::where('customer_id', $userId)
            ->where('status', 'available')
            ->where('expires_at', '>', Carbon::now())
            ->sum('amount');

        $pending = CustomerCashbackLedger::where('customer_id', $userId)
            ->where('status', 'pending_maturation')
            ->sum('amount');

        return response()->json([
            'status' => true,
            'data' => [
                'available_cashback' => number_format((float)$available, 2, '.', ''),
                'pending_maturation' => number_format((float)$pending, 2, '.', ''),
                'currency' => 'NGN',
            ],
        ]);
    }
}
