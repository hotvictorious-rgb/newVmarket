<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FulfillmentAvailabilityService;
use App\Models\CustomerAddress;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FulfillmentApiController extends Controller
{
    protected FulfillmentAvailabilityService $fulfillmentService;

    public function __construct(FulfillmentAvailabilityService $fulfillmentService)
    {
        $this->fulfillmentService = $fulfillmentService;
    }

    /**
     * Checks fulfillment availability.
     * Supports both:
     * 1) Customer Cart-Centric Schema: { address_id/destination_lga_id, cart_items: [...] }
     * 2) Single-Shop Corridor Schema: { fulfillment_type, shop_id, destination_lga_id }
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        // 1. Detect Customer/Cart-Centric Availability Query (Section 19 / API Contract 4.1)
        if ($request->has('cart_items') || $request->has('items') || (!$request->filled('shop_id') && ($request->filled('address_id') || $request->filled('destination_lga_id')))) {
            $cartItems = $request->input('cart_items') ?? $request->input('items') ?? [];
            $destLgaId = $request->input('destination_lga_id');

            if (!$destLgaId && $request->filled('address_id')) {
                $address = CustomerAddress::find($request->input('address_id'));
                if ($address) {
                    $destLgaId = $address->lga_id;
                }
            }

            $result = $this->fulfillmentService->checkCartFulfillmentAvailability($destLgaId ? (int)$destLgaId : null, (array)$cartItems);

            return response()->json($result, $result['available'] ? 200 : 422);
        }

        // 2. Single-Shop Corridor Resolution (Existing Contract)
        $validated = $request->validate([
            'fulfillment_type' => 'required|in:doorstep_delivery,in_shop_pickup',
            'shop_id' => 'required|integer|exists:shops,id',
            'destination_lga_id' => 'nullable|required_if:fulfillment_type,doorstep_delivery|integer|exists:lgas,id',
            'package_tier' => 'nullable|string|in:standard,fragile,bulky',
        ]);

        if ($validated['fulfillment_type'] === 'doorstep_delivery') {
            $result = $this->fulfillmentService->checkDeliveryAvailability(
                (int)$validated['shop_id'],
                (int)$validated['destination_lga_id'],
                $validated['package_tier'] ?? 'standard'
            );

            return response()->json([
                'status' => $result['is_available'],
                'message' => $result['is_available'] ? 'Delivery corridor is active.' : $result['message'],
                'data' => $result,
            ], $result['is_available'] ? 200 : 422);
        }

        // In-shop pickup check
        $pickupResult = $this->fulfillmentService->checkPickupAvailability((int)$validated['shop_id']);

        return response()->json([
            'status' => $pickupResult['is_available'],
            'message' => $pickupResult['is_available'] ? 'Shop is active for in-person inspection and pickup.' : $pickupResult['message'],
            'data' => $pickupResult,
        ], $pickupResult['is_available'] ? 200 : 422);
    }

    /**
     * Single-lane delivery fee query (API Contract Sec 4.2).
     * Request: { shop_id: 6, address_id: 4 } or { shop_id: 6, destination_lga_id: 69 }
     */
    public function deliveryFee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'address_id' => 'nullable|integer|exists:customer_addresses,id',
            'destination_lga_id' => 'nullable|integer|exists:lgas,id',
            'package_tier' => 'nullable|string|in:standard,fragile,bulky',
        ]);

        $shop = Shop::with('lga')->findOrFail($validated['shop_id']);
        $destLgaId = $validated['destination_lga_id'] ?? null;

        if (!$destLgaId && !empty($validated['address_id'])) {
            $address = CustomerAddress::find($validated['address_id']);
            $destLgaId = $address ? $address->lga_id : null;
        }

        if (!$destLgaId) {
            return response()->json([
                'status' => false,
                'message' => 'Either destination_lga_id or address_id must be provided.',
            ], 422);
        }

        $result = $this->fulfillmentService->checkDeliveryAvailability(
            $shop->id,
            (int)$destLgaId,
            $validated['package_tier'] ?? 'standard'
        );

        return response()->json([
            'status' => $result['is_available'],
            'origin_lga_id' => $shop->lga_id,
            'destination_lga_id' => (int)$destLgaId,
            'delivery_fee' => $result['fee'] ?? '0.00',
            'estimated_delivery_time' => $result['estimated_time_text'] ?? '2-6 hours',
            'currency' => 'NGN',
            'message' => $result['is_available'] ? 'Delivery fee calculated successfully.' : ($result['message'] ?? 'Corridor unserviced.'),
        ], $result['is_available'] ? 200 : 422);
    }
}
