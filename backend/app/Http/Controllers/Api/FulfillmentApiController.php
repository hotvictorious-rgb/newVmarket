<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FulfillmentAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FulfillmentApiController extends Controller
{
    protected FulfillmentAvailabilityService $fulfillmentService;

    public function __construct(FulfillmentAvailabilityService $fulfillmentService)
    {
        $this->fulfillmentService = $fulfillmentService;
    }

    public function checkAvailability(Request $request): JsonResponse
    {
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
}
