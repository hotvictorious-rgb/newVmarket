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

        // VENDOR COVERAGE RULE:
        // Admin sets standard delivery fees; vendors configure the individual LGAs and states
        // their physical shop's riders can physically service. Unsupported destinations fail closed.
        if (!$shop->coversDestination($destinationLgaId, $destLga->state_id)) {
            $shopLgaName = $shop->lga ? $shop->lga->name : 'its physical branch';
            return [
                'is_available' => false,
                'lane_id' => null,
                'origin_lga_id' => $shop->lga_id,
                'destination_lga_id' => $destinationLgaId,
                'fee' => '0.00',
                'message' => "Merchant outlet ({$shop->name} in {$shopLgaName} LGA) does not offer doorstep delivery to {$destLga->name} LGA. In-shop pickup is available at the physical outlet.",
                'rate_tier' => 'vendor_unserviced_area',
                'is_collection_point' => false,
                'collection_points' => [],
            ];
        }

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

        // 3-Tier Zonal Distance Fallback (Nationwide Coverage for unscheduled routes)
        $shopLga = $shop->lga;

        // Check if out-of-state corridor to this entire destination state is suspended
        if ($shopLga && $destLga && $shopLga->state_id !== $destLga->state_id) {
            $stateSuspended = DeliveryLane::where('origin_lga_id', $shop->lga_id)
                ->whereHas('destinationLga', function ($q) use ($destLga) {
                    $q->where('state_id', $destLga->state_id);
                })
                ->where('is_active', false)
                ->exists();

            if ($stateSuspended) {
                return [
                    'is_available' => false,
                    'lane_id' => null,
                    'origin_lga_id' => $shop->lga_id,
                    'destination_lga_id' => $destinationLgaId,
                    'fee' => '0.00',
                    'message' => "Delivery is currently suspended to " . ($destLga->state->name ?? 'this state') . ".",
                    'rate_tier' => 'state_corridor_suspended',
                    'is_collection_point' => true,
                    'collection_points' => [],
                ];
            }
        }

        if ($shopLga && $destLga) {
            $bulkySurcharge = number_format((float)\App\Models\SystemSetting::get('zone_bulky_cargo_surcharge', '1000.00'), 2, '.', '');
            
            // Tier 1: Intra-LGA (Municipal same-LGA) -> Doorstep Delivery
            if ($shopLga->id === $destLga->id) {
                $baseFee = number_format((float)\App\Models\SystemSetting::get('zone_intra_lga_fee', '500.00'), 2, '.', '');
                $eta = (string)\App\Models\SystemSetting::get('zone_intra_lga_eta', '2-4 hours');
                $fee = $isKekeOrBulky ? BcMathService::add($baseFee, $bulkySurcharge) : $baseFee;
                $fee = number_format((float)$fee, 2, '.', '');

                return [
                    'is_available' => true,
                    'lane_id' => null,
                    'origin_lga_id' => $shop->lga_id,
                    'destination_lga_id' => $destinationLgaId,
                    'fee' => $fee,
                    'estimated_delivery_hours_min' => 2,
                    'estimated_delivery_hours_max' => 4,
                    'estimated_time_text' => $eta,
                    'rate_tier' => 'tier_1_intra_lga',
                    'is_collection_point' => false,
                    'collection_points' => [],
                ];
            }

            // Tier 2: Inter-LGA (Regional, same state) -> Collection Point Pickup
            if ($shopLga->state_id === $destLga->state_id) {
                $baseFee = number_format((float)\App\Models\SystemSetting::get('zone_inter_lga_fee', '1500.00'), 2, '.', '');
                $eta = (string)\App\Models\SystemSetting::get('zone_inter_lga_eta', 'Same day / 12-24 hours');
                $fee = $isKekeOrBulky ? BcMathService::add($baseFee, $bulkySurcharge) : $baseFee;
                $fee = number_format((float)$fee, 2, '.', '');
                $collectionPoints = $this->resolveCollectionPoints($destinationLgaId);

                return [
                    'is_available' => true,
                    'lane_id' => null,
                    'origin_lga_id' => $shop->lga_id,
                    'destination_lga_id' => $destinationLgaId,
                    'fee' => $fee,
                    'estimated_delivery_hours_min' => 12,
                    'estimated_delivery_hours_max' => 24,
                    'estimated_time_text' => $eta,
                    'rate_tier' => 'tier_2_inter_lga',
                    'is_collection_point' => !empty($collectionPoints),
                    'collection_points' => $collectionPoints,
                ];
            }

            // Tier 3: Inter-State (National corridor)
            $baseFee = number_format((float)\App\Models\SystemSetting::get('zone_inter_state_fee', '3500.00'), 2, '.', '');
            $eta = (string)\App\Models\SystemSetting::get('zone_inter_state_eta', '2-4 business days');
            $fee = $isKekeOrBulky ? BcMathService::add($baseFee, $bulkySurcharge) : $baseFee;
            $fee = number_format((float)$fee, 2, '.', '');
            $collectionPoints = $this->resolveCollectionPoints($destinationLgaId);

            return [
                'is_available' => true,
                'lane_id' => null,
                'origin_lga_id' => $shop->lga_id,
                'destination_lga_id' => $destinationLgaId,
                'fee' => $fee,
                'estimated_delivery_hours_min' => 48,
                'estimated_delivery_hours_max' => 96,
                'estimated_time_text' => $eta,
                'rate_tier' => 'tier_3_inter_state',
                'is_collection_point' => !empty($collectionPoints),
                'collection_points' => $collectionPoints,
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

