<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FulfillmentAvailabilityService;
use App\Services\CustomerDeliveryPricingService;
use App\Services\BcMathService;
use App\Models\CustomerAddress;
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
     * Supports customer cart-centric availability only. The legacy single-shop contract is deprecated.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        // 1. Detect Customer/Cart-Centric Availability Query (Section 19 / API Contract 4.1)
        if ($request->has('cart_items') || $request->has('items') || (!$request->filled('shop_id') && ($request->filled('address_id') || $request->filled('destination_lga_id')))) {
            $cartItems = $request->input('cart_items') ?? $request->input('items') ?? [];
            $destLgaId = $request->input('destination_lga_id');

            if (!$destLgaId && $request->filled('address_id')) {
                $addressQuery = CustomerAddress::whereKey($request->input('address_id'));
                if ($request->user()) {
                    $addressQuery->where('user_id', $request->user()->id);
                }
                $address = $addressQuery->first();
                if ($address) {
                    $destLgaId = $address->lga_id;
                }
            }

            $result = $this->fulfillmentService->checkCartFulfillmentAvailability($destLgaId ? (int)$destLgaId : null, (array)$cartItems);
            $delivery = $result['fulfillment_options']['delivery'] ?? null;
            if (is_array($delivery) && !empty($delivery['available'])) {
                $pricing = app(CustomerDeliveryPricingService::class)->quote((int) ($delivery['bulky_quantity'] ?? 0));
                $result['fulfillment_options']['delivery']['base_route_fee'] = $pricing['base_fee'];
                $result['fulfillment_options']['delivery']['bulky_surcharge_per_unit'] = $pricing['bulky_surcharge_per_unit'];
                $result['fulfillment_options']['delivery']['bulky_surcharge_total'] = BcMathService::mul(
                    $pricing['bulky_surcharge_per_unit'],
                    (string) $pricing['bulky_quantity']
                );
                $result['fulfillment_options']['delivery']['total_shipping_fee'] = $pricing['total_fee'];
            }

            return response()->json($result, $result['available'] ? 200 : 422);
        }

        // The old single-shop contract exposed supplier-specific availability and
        // pickup details. New retailer checkout must be evaluated as one complete cart.
        return response()->json([
            'status' => false,
            'code' => 'CART_LEVEL_AVAILABILITY_REQUIRED',
            'message' => 'Supplier/shop selection and public pickup availability are not supported. Send the complete cart and delivery destination to check availability.',
        ], 410);
    }

    /**
     * Customer-safe order-level delivery fee query. No supplier/shop selection is accepted.
     */
    public function deliveryFee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
            'address_id' => 'nullable|integer|exists:customer_addresses,id',
            'destination_lga_id' => 'nullable|integer|exists:lgas,id',
        ]);

        $destinationLgaId = $validated['destination_lga_id'] ?? null;
        if (!empty($validated['address_id'])) {
            $addressQuery = CustomerAddress::whereKey($validated['address_id']);
            if ($request->user()) {
                $addressQuery->where('user_id', $request->user()->id);
            }
            $address = $addressQuery->firstOrFail();
            $destinationLgaId = $address->lga_id;
        }

        if (!$destinationLgaId) {
            return response()->json([
                'status' => false,
                'message' => 'Provide a valid delivery address or destination LGA.',
            ], 422);
        }

        $result = $this->fulfillmentService->checkCartFulfillmentAvailability(
            (int) $destinationLgaId,
            $validated['items']
        );
        $delivery = $result['fulfillment_options']['delivery'] ?? [];
        $pricing = null;
        if (!empty($delivery['available'])) {
            $pricing = app(CustomerDeliveryPricingService::class)->quote((int) ($delivery['bulky_quantity'] ?? 0));
        }

        return response()->json([
            'status' => (bool) ($delivery['available'] ?? false),
            'base_route_fee' => $pricing['base_fee'] ?? '0.00',
            'bulky_surcharge_per_unit' => $pricing['bulky_surcharge_per_unit'] ?? '0.00',
            'bulky_quantity' => $pricing['bulky_quantity'] ?? ($delivery['bulky_quantity'] ?? 0),
            'bulky_surcharge_total' => $pricing ? BcMathService::mul($pricing['bulky_surcharge_per_unit'], (string) $pricing['bulky_quantity']) : '0.00',
            'delivery_fee' => $pricing['total_fee'] ?? '0.00',
            'currency' => 'NGN',
            'message' => ($delivery['available'] ?? false)
                ? 'Order-level delivery fee calculated successfully.'
                : ($result['message'] ?? 'The complete order cannot currently be delivered to this destination.'),
        ], ($delivery['available'] ?? false) ? 200 : 422);
    }
}
