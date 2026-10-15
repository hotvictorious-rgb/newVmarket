<?php

namespace App\Services;

class BcMathService
{
    public const SCALE = 2;

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul($a, $b, self::SCALE);
    }

    public static function div(string $a, string $b): string
    {
        return bcdiv($a, $b, self::SCALE);
    }

    public static function comp(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    /**
     * Mathematical Commercial Split Engine (90 / 5 / 5 Invariant)
     * Proof: Vendor 90% + Cashback 5% + Platform 5% == Merchandise 100% (Delta = ₦0.00)
     */
    public static function computeCommercialSplits(string $merchandiseSubtotal, string $deliveryFee = '0.00'): array
    {
        // 90% guaranteed to accredited merchant
        $vendorNet = self::mul($merchandiseSubtotal, '0.90');

        // 5% customer loyalty reward funded from commission
        $cashback = self::mul($merchandiseSubtotal, '0.05');

        // 5% platform retained operating margin
        $totalCommission = self::mul($merchandiseSubtotal, '0.10');
        $platformRetained = self::sub($totalCommission, $cashback);

        // Verification invariant check: Δ == 0.00
        $sum = self::add(self::add($vendorNet, $cashback), $platformRetained);
        $delta = self::sub($merchandiseSubtotal, $sum);

        if (self::comp($delta, '0.00') !== 0) {
            $platformRetained = self::add($platformRetained, $delta);
        }

        return [
            'merchandise_subtotal' => $merchandiseSubtotal,
            'vendor_net_90' => $vendorNet,
            'customer_cashback_5' => $cashback,
            'platform_retained_5' => $platformRetained,
            'delivery_fee_segregated' => $deliveryFee,
            'grand_total' => self::add($merchandiseSubtotal, $deliveryFee),
        ];
    }
}
