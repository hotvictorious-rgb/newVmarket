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

        // Group cart items by shop
        $shopGroups = [];
        foreach ($cartItems as $item) {
            $productId = $item['product_id'] ?? $item['id'] ?? null;
            if (!$productId) {
                continue;
            }

            $product = Product::with(['shop.lga', 'shop.state', 'vendor.user'])->find($productId);
            if (!$product || !$product->shop || $product->shop->trashed() || !$product->shop->is_active) {
                continue;
            }
            if (!$product->vendor || $product->vendor->trashed() || $product->vendor->marketplace_status !== 'active' || ($product->vendor->user && $product->vendor->user->status !== 'active')) {
                continue;
            }

            $shopId = $product->shop->id;
            if (!isset($shopGroups[$shopId])) {
                $shopGroups[$shopId] = [
                    'shop' => $product->shop,
                    'vendor' => $product->vendor,
                    'products' => [],
                ];
            }
            $shopGroups[$shopId]['products'][] = [
                'product' => $product,
                'quantity' => $item['quantity'] ?? 1,
            ];
        }

        if (empty($shopGroups)) {
            return [
                'status' => false,
                'available' => false,
                'message' => 'No active products or shops found for cart items.',
                'fulfillment_options' => [],
            ];
        }

        $vendorLanes = [];
        $totalShippingFee = '0.00';
        $allDeliverable = true;
        $anyDeliverable = false;

        $pickupShops = [];
        $anyPickupAvailable = false;

        foreach ($shopGroups as $shopId => $group) {
            $shop = $group['shop'];

            // 1. Delivery evaluation
            if ($destinationLgaId) {
                $groupTier = 'bike';
                foreach ($group['products'] as $groupProduct) {
                    $pTier = $groupProduct['product']->package_tier ?? 'bike';
                    if (in_array(strtolower($pTier), ['keke', 'bulky'], true)) {
                        $groupTier = 'keke';
                        break;
                    }
                }

                $deliveryRes = $this->checkDeliveryAvailability($shop->id, $destinationLgaId, $groupTier);
                $isLaneAvail = $deliveryRes['is_available'] ?? false;
                $fee = $isLaneAvail ? ($deliveryRes['fee'] ?? '0.00') : '0.00';

                if ($isLaneAvail) {
                    $anyDeliverable = true;
                    $totalShippingFee = BcMathService::add($totalShippingFee, $fee);
                } else {
                    $allDeliverable = false;
                }

                $vendorLanes[] = [
                    'shop_id' => $shop->id,
                    'seller_id' => $shop->vendor_id,
                    'shop_name' => $shop->name,
                    'origin_lga' => $shop->lga ? ['id' => $shop->lga->id, 'name' => $shop->lga->name] : null,
                    'delivery_fee' => $fee,
                    'estimated_delivery_time' => $deliveryRes['estimated_time_text'] ?? '24-48 hours',
                    'is_available' => $isLaneAvail,
                    'unavailability_reason' => $isLaneAvail ? null : ($deliveryRes['message'] ?? 'Lane unserviced'),
                    'collection_points' => $deliveryRes['collection_points'] ?? [],
                ];
            } else {
                $allDeliverable = false;
            }

            // 2. Pickup evaluation
            $pickupRes = $this->checkPickupAvailability($shop->id);
            if (!empty($pickupRes['is_available'])) {
                $anyPickupAvailable = true;
                $pickupShops[] = [
                    'shop_id' => $shop->id,
                    'name' => $shop->name,
                    'address' => $shop->address,
                    'pickup_time_slots' => ['08:00 - 11:00', '11:00 - 14:00', '14:00 - 18:00'],
                    'pickup_instructions' => 'Bring your 6-digit reservation code for inspection.',
                    'opening_time' => $shop->pickup_opening_time ?? '08:00',
                    'closing_time' => $shop->pickup_closing_time ?? '18:00',
                ];
            }
        }

        $overallAvailable = ($destinationLgaId && $allDeliverable) || $anyPickupAvailable;

        return [
            'status' => true,
            'available' => $overallAvailable,
            'fulfillment_options' => [
                'delivery' => [
                    'available' => (bool)($destinationLgaId && $allDeliverable),
                    'total_shipping_fee' => $totalShippingFee,
                    'currency' => 'NGN',
                    'destination_lga' => $destLga ? ['id' => $destLga->id, 'name' => $destLga->name] : null,
                    'vendor_lanes' => $vendorLanes,
                ],
                'in_shop_pickup' => [
                    'available' => $anyPickupAvailable,
                    'shops' => $pickupShops,
                ],
            ],
        ];
    }
}

