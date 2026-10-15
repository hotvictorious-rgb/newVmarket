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

        if ($lane) {
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
                'rate_tier' => 'custom_override',
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
                'message' => 'Doorstep delivery is currently suspended or unserviceable along this route corridor.',
                'rate_tier' => 'corridor_suspended',
            ];
        }

        // 3-Tier Zonal Distance Fallback (100% Nationwide Coverage for unscheduled routes)
        $destLga = \App\Models\Lga::with('state')->find($destinationLgaId);
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
                    'message' => "Doorstep delivery is currently suspended to " . ($destLga->state->name ?? 'this state') . ".",
                    'rate_tier' => 'state_corridor_suspended',
                ];
            }
        }

        if ($shopLga && $destLga) {
            $bulkySurcharge = (string)\App\Models\SystemSetting::get('zone_bulky_cargo_surcharge', '1000.00');
            
            // Tier 1: Intra-LGA (Municipal same-LGA)
            if ($shopLga->id === $destLga->id) {
                $baseFee = (string)\App\Models\SystemSetting::get('zone_intra_lga_fee', '500.00');
                $eta = (string)\App\Models\SystemSetting::get('zone_intra_lga_eta', '2-4 hours');
                $fee = ($packageTier === 'bulky') ? BcMathService::add($baseFee, $bulkySurcharge) : $baseFee;

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
                ];
            }

            // Tier 2: Inter-LGA (Regional, same state)
            if ($shopLga->state_id === $destLga->state_id) {
                $baseFee = (string)\App\Models\SystemSetting::get('zone_inter_lga_fee', '1500.00');
                $eta = (string)\App\Models\SystemSetting::get('zone_inter_lga_eta', 'Same day / 12-24 hours');
                $fee = ($packageTier === 'bulky') ? BcMathService::add($baseFee, $bulkySurcharge) : $baseFee;

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
                ];
            }

            // Tier 3: Inter-State (National freight)
            $baseFee = (string)\App\Models\SystemSetting::get('zone_inter_state_fee', '3500.00');
            $eta = (string)\App\Models\SystemSetting::get('zone_inter_state_eta', '2-4 business days');
            $fee = ($packageTier === 'bulky') ? BcMathService::add($baseFee, $bulkySurcharge) : $baseFee;

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
}
