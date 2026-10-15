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
    public function checkDeliveryAvailability(int $shopId, int $destinationLgaId, string $packageTier = 'standard'): array
    {
        $shop = Shop::with('lga')->find($shopId);
        if (!$shop) {
            return [
                'is_available' => false,
                'message' => 'Shop not found.',
                'fee' => '0.00',
            ];
        }

        $lane = DeliveryLane::where('origin_lga_id', $shop->lga_id)
            ->where('destination_lga_id', $destinationLgaId)
            ->where('is_active', true)
            ->first();

        if (!$lane) {
            return [
                'is_available' => false,
                'message' => "No active delivery corridor from {$shop->lga->name} to destination LGA.",
                'fee' => '0.00',
            ];
        }

        $fee = BcMathService::add($lane->base_fee, '0.00');
        if ($packageTier === 'bulky') {
            $fee = BcMathService::add($fee, $lane->bulky_surcharge);
        }

        return [
            'is_available' => true,
            'lane_id' => $lane->id,
            'origin_lga_id' => $shop->lga_id,
            'destination_lga_id' => $destinationLgaId,
            'fee' => $fee,
            'estimated_delivery_hours_min' => $lane->estimated_delivery_hours_min,
            'estimated_delivery_hours_max' => $lane->estimated_delivery_hours_max,
            'estimated_time_text' => "{$lane->estimated_delivery_hours_min}-{$lane->estimated_delivery_hours_max} hours",
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
}
