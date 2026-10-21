<?php

namespace App\Services;

use App\Models\SystemSetting;
use InvalidArgumentException;

class CustomerDeliveryPricingService
{
    /**
     * Customer delivery pricing is a flat base fee per order plus a per-physical-unit
     * bulky surcharge. It is deliberately independent of company corridor payables.
     */
    public function quote(int $bulkyQuantity): array
    {
        if ($bulkyQuantity < 0) {
            throw new InvalidArgumentException('Bulky quantity cannot be negative.');
        }

        $baseFee = $this->moneySetting('zone_intra_lga_fee', '500.00');
        $bulkySurcharge = $this->moneySetting('zone_bulky_cargo_surcharge', '1000.00');

        return [
            'base_fee' => $baseFee,
            'bulky_surcharge_per_unit' => $bulkySurcharge,
            'bulky_quantity' => $bulkyQuantity,
            'total_fee' => self::calculateFee($baseFee, $bulkySurcharge, $bulkyQuantity),
        ];
    }

    public static function calculateFee(string $baseFee, string $bulkySurcharge, int $bulkyQuantity): string
    {
        if ($bulkyQuantity < 0) {
            throw new InvalidArgumentException('Bulky quantity cannot be negative.');
        }
        if (BcMathService::comp($baseFee, '0.00') < 0 || BcMathService::comp($bulkySurcharge, '0.00') < 0) {
            throw new InvalidArgumentException('Customer delivery fees must be non-negative amounts.');
        }

        return BcMathService::add(
            $baseFee,
            BcMathService::mul($bulkySurcharge, (string) $bulkyQuantity)
        );
    }

    private function moneySetting(string $key, string $fallback): string
    {
        $value = SystemSetting::get($key, $fallback);
        if (!is_numeric($value) || BcMathService::comp((string) $value, '0.00') < 0) {
            throw new InvalidArgumentException("Customer delivery pricing setting '{$key}' must be a non-negative amount.");
        }

        return BcMathService::add((string) $value, '0.00');
    }
}
