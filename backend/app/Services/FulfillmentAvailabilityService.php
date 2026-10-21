<?php

namespace App\Services;

use App\Models\CustomerAddress;
use App\Models\DeliveryLane;
use App\Models\Product;
use App\Models\Shop;

class FulfillmentAvailabilityService
{
    /**
     * Checks directional doorstep delivery availability & fee between Shop LGA and Destination LGA.
     */
    public function checkDeliveryAvailability(int $shopId, int $destinationLgaId, string $packageTier = 'bike'): array
    {
        $shop = Shop::withTrashed()->with(['lga', 'vendor.user'])->find($shopId);
        if (!$shop || $shop->trashed() || !$shop->is_active) {
            return [
                'is_available' => false,
                'message' => 'The selected shop branch is currently inactive or deactivated.',
                'fee' => '0.00',
            ];
        }

        // Owner policy: all sourcing shops must be physically based in Uyo, Akwa Ibom.
        $originLgaName = strtolower(trim((string) ($shop->lga->name ?? '')));
        $originStateName = strtolower(trim((string) ($shop->lga->state->name ?? '')));
        if ($originLgaName !== 'uyo' || $originStateName !== 'akwa ibom') {
            return [
                'is_available' => false,
                'lane_id' => null,
                'origin_lga_id' => $shop->lga_id,
                'destination_lga_id' => $destinationLgaId,
                'fee' => '0.00',
                'message' => 'Victorious MARKET sourcing and dispatch origins must be based in Uyo, Akwa Ibom State.',
                'rate_tier' => 'origin_outside_uyo',
                'delivery_mode' => null,
                'is_collection_point' => false,
                'collection_points' => [],
            ];
        }

        if (!$shop->vendor || $shop->vendor->trashed() || $shop->vendor->marketplace_status !== 'active') {
            return [
                'is_available' => false,
                'message' => 'The merchant providing this item is currently not active on the marketplace.',
                'fee' => '0.00',
            ];
        }

        if ($shop->vendor->user && $shop->vendor->user->status !== 'active') {
            return [
                'is_available' => false,
                'message' => 'The merchant user account is inactive.',
                'fee' => '0.00',
            ];
        }

        $destLga = \App\Models\Lga::with('state')->find($destinationLgaId);
        if (!$destLga) {
            return [
                'is_available' => false,
                'message' => 'Destination LGA not found or invalid.',
                'fee' => '0.00',
            ];
        }

        // Route serviceability is controlled by the explicit directional route matrix, not vendor rider coverage.

        $isKekeOrBulky = in_array(strtolower($packageTier), ['keke', 'bulky'], true);

        $lane = DeliveryLane::where('origin_lga_id', $shop->lga_id)
            ->where('destination_lga_id', $destinationLgaId)
            ->where('is_active', true)
            ->first();

        if ($lane) {
            // Active lane is used for delivery pricing/serviceability only. New orders are
            // vendor-managed, so an inactive legacy fleet assignment must not block vendor delivery.

            $fee = BcMathService::add($lane->base_fee, '0.00');
            if ($isKekeOrBulky) {
                $fee = BcMathService::add($fee, $lane->bulky_surcharge);
            }

            $isCrossLga = ($shop->lga_id !== $destinationLgaId);
            $collectionPoints = $isCrossLga ? $this->resolveCollectionPoints($destinationLgaId, $lane->assigned_logistics_company_id) : [];

            return [
                'is_available' => true,
                'lane_id' => $lane->id,
                'origin_lga_id' => $shop->lga_id,
                'destination_lga_id' => $destinationLgaId,
                'fee' => $fee,
                'bulky_surcharge' => number_format((float) $lane->bulky_surcharge, 2, '.', ''),
                'estimated_delivery_hours_min' => $lane->estimated_delivery_hours_min,
                'estimated_delivery_hours_max' => $lane->estimated_delivery_hours_max,
                'estimated_time_text' => "{$lane->estimated_delivery_hours_min}-{$lane->estimated_delivery_hours_max} hours",
                'rate_tier' => 'custom_override',
                'delivery_mode' => $isCrossLga ? 'logistics_managed' : 'doorstep',
                'is_collection_point' => $isCrossLga,
                'collection_points' => $collectionPoints,
            ];
        }

        // Check if an explicit corridor exists that was deactivated (marked unserviceable by admin)
        $deactivatedLane = DeliveryLane::where('origin_lga_id', $shop->lga_id)
            ->where('destination_lga_id', $destinationLgaId)
            ->where('is_active', false)
            ->first();

        if ($deactivatedLane) {
            return [
                'is_available' => false,
                'lane_id' => $deactivatedLane->id,
                'origin_lga_id' => $shop->lga_id,
                'destination_lga_id' => $destinationLgaId,
                'fee' => '0.00',
                'message' => 'Delivery is currently suspended or unserviceable along this route corridor.',
                'rate_tier' => 'corridor_suspended',
                'is_collection_point' => ($shop->lga_id !== $destinationLgaId),
                'collection_points' => [],
            ];
        }

        // Fail closed for routes outside the explicitly enabled directional lane matrix.
        // Only Uyo-local delivery may use the local default fee without a separate lane.
        $originLga = $shop->lga;
        $isUyoLocal = $originLga
            && (int) $originLga->id === (int) $destLga->id
            && strtolower(trim((string) ($originLga->name ?? ''))) === 'uyo'
            && strtolower(trim((string) ($originLga->state->name ?? ''))) === 'akwa ibom';

        if ($isUyoLocal) {
            $bulkySurcharge = number_format((float) \App\Models\SystemSetting::get('zone_bulky_cargo_surcharge', '1000.00'), 2, '.', '');
            $baseFee = number_format((float) \App\Models\SystemSetting::get('zone_intra_lga_fee', '500.00'), 2, '.', '');
            $fee = $isKekeOrBulky ? BcMathService::add($baseFee, $bulkySurcharge) : $baseFee;
            return [
                'is_available' => true,
                'lane_id' => null,
                'origin_lga_id' => $shop->lga_id,
                'destination_lga_id' => $destinationLgaId,
                'fee' => number_format((float) $fee, 2, '.', ''),
                'bulky_surcharge' => $bulkySurcharge,
                'estimated_delivery_hours_min' => 2,
                'estimated_delivery_hours_max' => 4,
                'estimated_time_text' => (string) \App\Models\SystemSetting::get('zone_intra_lga_eta', '2-4 hours'),
                'rate_tier' => 'tier_1_intra_lga',
                'delivery_mode' => 'doorstep',
                'is_collection_point' => false,
                'collection_points' => [],
            ];
        }

        return [
            'is_available' => false,
            'message' => "Delivery corridor unavailable.",
            'fee' => '0.00',
        ];
    }

    /**
     * Checks in-shop pickup availability for a shop.
     */
    public function checkPickupAvailability(int $shopId): array
    {
        $shop = Shop::with(['lga', 'state'])->find($shopId);
        if (!$shop || !$shop->pickup_enabled) {
            return [
                'is_available' => false,
                'message' => 'In-shop pickup is not enabled for this shop.',
            ];
        }

        return [
            'is_available' => true,
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'address' => $shop->address,
            'lga' => $shop->lga->name ?? null,
            'state' => $shop->state->name ?? null,
            'prep_time_minutes' => $shop->pickup_prep_time_minutes,
            'opening_time' => $shop->pickup_opening_time,
            'closing_time' => $shop->pickup_closing_time,
        ];
    }

    /**
     * Resolves active Collection Points in destination LGA.
     */
    protected function resolveCollectionPoints(int $destinationLgaId, ?int $assignedCompanyId = null): array
    {
        $query = \App\Models\LogisticsCollectionPoint::where('lga_id', $destinationLgaId)
            ->where('is_active', true)
            ->whereHas('logisticsCompany', function ($q) {
                $q->whereNull('deleted_at')
                    ->where('status', 'active')
                    ->whereHas('user', fn($uq) => $uq->where('status', 'active'));
            })
            ->with('logisticsCompany');

        if ($assignedCompanyId) {
            $query->where('logistics_company_id', $assignedCompanyId);
        }

        return $query->get()->map(function ($cp) {
            return [
                'id' => $cp->id,
                'name' => $cp->name,
                'code' => $cp->code,
                'company_name' => $cp->logisticsCompany->company_name ?? 'Accredited Logistics Company',
                'address' => $cp->address,
                'landmark' => $cp->landmark,
                'contact_phone' => $cp->contact_phone,
                'operating_hours' => $cp->operating_hours,
            ];
        })->toArray();
    }

    /**
     * Evaluates multi-item cart fulfillment availability across merchant shops, delivery lanes and pickup options.
     */
    public function checkCartFulfillmentAvailability(?int $destinationLgaId, array $cartItems): array
    {
        if (empty($cartItems)) {
            return [
                'status' => false,
                'available' => false,
                'message' => 'Cart is empty.',
                'fulfillment_options' => [],
            ];
        }

        $destLga = $destinationLgaId ? \App\Models\Lga::with('state')->find($destinationLgaId) : null;
        if (!$destLga) {
            return [
                'status' => true,
                'available' => false,
                'message' => 'Select a valid delivery destination to check availability.',
                'fulfillment_options' => [
                    'delivery' => ['available' => false, 'total_shipping_fee' => '0.00', 'currency' => 'NGN'],
                    'in_shop_pickup' => ['available' => false, 'message' => 'Pickup requests must be arranged through Victorious MARKET support.'],
                ],
            ];
        }

        $sourcingService = app(\App\Services\SourcingAllocationService::class);
        $baseFee = null;
        $bulkySurchargePerUnit = null;
        $bulkyQuantity = 0;

        foreach ($cartItems as $item) {
            $productId = $item['product_id'] ?? $item['id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 1);
            if (!$productId || $quantity < 1) {
                return [
                    'status' => false,
                    'available' => false,
                    'message' => 'One or more cart items are invalid.',
                    'fulfillment_options' => [],
                ];
            }

            $product = Product::marketplaceEligible()->find($productId);
            if (!$product || !$product->is_in_stock || !$product->isFresh()) {
                return [
                    'status' => true,
                    'available' => false,
                    'message' => 'One or more products are no longer available. Refresh the cart and try again.',
                    'fulfillment_options' => [
                        'delivery' => ['available' => false, 'total_shipping_fee' => '0.00', 'currency' => 'NGN'],
                        'in_shop_pickup' => ['available' => false, 'message' => 'Pickup requests must be arranged through Victorious MARKET support.'],
                    ],
                ];
            }

            if (isset($product->inventory_quantity) && (int) $product->inventory_quantity > 0 && (int) $product->inventory_quantity < $quantity) {
                return [
                    'status' => true,
                    'available' => false,
                    'message' => 'One or more products do not have enough confirmed quantity.',
                    'fulfillment_options' => [
                        'delivery' => ['available' => false, 'total_shipping_fee' => '0.00', 'currency' => 'NGN'],
                        'in_shop_pickup' => ['available' => false, 'message' => 'Pickup requests must be arranged through Victorious MARKET support.'],
                    ],
                ];
            }

            $offer = $sourcingService->resolveCheapestOffer($product, $quantity, null, 'doorstep_delivery');
            if (!$offer) {
                return [
                    'status' => true,
                    'available' => false,
                    'message' => 'One or more products have no eligible supplier available in Uyo.',
                    'fulfillment_options' => [
                        'delivery' => ['available' => false, 'total_shipping_fee' => '0.00', 'currency' => 'NGN'],
                        'in_shop_pickup' => ['available' => false, 'message' => 'Pickup requests must be arranged through Victorious MARKET support.'],
                    ],
                ];
            }

            $availability = $this->checkDeliveryAvailability((int) $offer['shop_id'], (int) $destinationLgaId, 'bike');
            if (empty($availability['is_available'])) {
                return [
                    'status' => true,
                    'available' => false,
                    'message' => 'The complete order cannot currently be delivered to this destination.',
                    'fulfillment_options' => [
                        'delivery' => ['available' => false, 'total_shipping_fee' => '0.00', 'currency' => 'NGN'],
                        'in_shop_pickup' => ['available' => false, 'message' => 'Pickup requests must be arranged through Victorious MARKET support.'],
                    ],
                ];
            }

            $itemBaseFee = (string) ($availability['fee'] ?? '0.00');
            $itemBulkySurcharge = (string) ($availability['bulky_surcharge'] ?? '0.00');
            if ($baseFee === null) {
                $baseFee = $itemBaseFee;
                $bulkySurchargePerUnit = $itemBulkySurcharge;
            } elseif (
                BcMathService::comp($baseFee, $itemBaseFee) !== 0
                || BcMathService::comp((string) $bulkySurchargePerUnit, $itemBulkySurcharge) !== 0
            ) {
                return [
                    'status' => true,
                    'available' => false,
                    'message' => 'The complete order needs a delivery-fee review before checkout.',
                    'fulfillment_options' => [
                        'delivery' => ['available' => false, 'total_shipping_fee' => '0.00', 'currency' => 'NGN'],
                        'in_shop_pickup' => ['available' => false, 'message' => 'Pickup requests must be arranged through Victorious MARKET support.'],
                    ],
                ];
            }

            if (in_array(strtolower((string) ($product->package_tier ?? 'bike')), ['keke', 'bulky'], true)) {
                $bulkyQuantity += $quantity;
            }
        }

        $baseFee = $baseFee ?? '0.00';
        $bulkySurchargePerUnit = $bulkySurchargePerUnit ?? '0.00';
        $bulkySurchargeTotal = BcMathService::mul($bulkySurchargePerUnit, (string) $bulkyQuantity);
        $totalFee = BcMathService::add($baseFee, $bulkySurchargeTotal);

        return [
            'status' => true,
            'available' => true,
            'fulfillment_options' => [
                'delivery' => [
                    'available' => true,
                    'base_route_fee' => $baseFee,
                    'bulky_surcharge_per_unit' => $bulkySurchargePerUnit,
                    'bulky_quantity' => $bulkyQuantity,
                    'bulky_surcharge_total' => $bulkySurchargeTotal,
                    'total_shipping_fee' => $totalFee,
                    'currency' => 'NGN',
                    'destination_lga' => ['id' => $destLga->id, 'name' => $destLga->name],
                ],
                'in_shop_pickup' => [
                    'available' => false,
                    'message' => 'Pickup requests must be arranged through Victorious MARKET support.',
                ],
            ],
        ];
    }

}

