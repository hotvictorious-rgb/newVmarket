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
     * Mathematical Commercial Split Engine
     * 
     * 1. Merchandise Subtotal Invariant (90 / 5 / 5):
     *    - Vendor Net: 90% guaranteed to merchant
     *    - Customer Cashback: 5% customer loyalty reward
     *    - Platform Retained Margin: 5% Victorious Market operating margin
     *    Proof: Vendor 90% + Cashback 5% + Platform 5% == Merchandise 100% (Δ = ₦0.00)
     *
     * 2. Delivery Fee Invariant (90 / 10 Split):
     *    - Logistics Company: 90% credited to corporate wallet
     *    - Victorious Market Platform: 10% platform delivery fee cut
     *    Proof: Logistics 90% + Platform Delivery Cut 10% == Delivery Fee 100% (Δ = ₦0.00)
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

        // Verification invariant check: Δ == 0.00 on merchandise
        $merchandiseSum = self::add(self::add($vendorNet, $cashback), $platformRetained);
        $merchandiseDelta = self::sub($merchandiseSubtotal, $merchandiseSum);

        if (self::comp($merchandiseDelta, '0.00') !== 0) {
            $platformRetained = self::add($platformRetained, $merchandiseDelta);
        }

        // Delivery fee split: 90% Logistics Company / 10% Victorious Market
        $logisticsNet = self::mul($deliveryFee, '0.90');
        $platformDeliveryCut = self::sub($deliveryFee, $logisticsNet);

        // Verification invariant check: Δ == 0.00 on delivery fee
        $deliverySum = self::add($logisticsNet, $platformDeliveryCut);
        $deliveryDelta = self::sub($deliveryFee, $deliverySum);

        if (self::comp($deliveryDelta, '0.00') !== 0) {
            $platformDeliveryCut = self::add($platformDeliveryCut, $deliveryDelta);
        }

        // Platform Total Retained = Merchandise 5% + Delivery Fee 10%
        $platformTotalRetained = self::add($platformRetained, $platformDeliveryCut);

        return [
            'merchandise_subtotal' => $merchandiseSubtotal,
            'vendor_net_90' => $vendorNet,
            'customer_cashback_5' => $cashback,
            'platform_retained_5' => $platformRetained,
            'platform_vendor_commission_5' => $platformRetained,
            'delivery_fee_segregated' => $deliveryFee,
            'logistics_net_90' => $logisticsNet,
            'platform_delivery_cut_10' => $platformDeliveryCut,
            'platform_delivery_commission_10' => $platformDeliveryCut,
            'platform_total_retained' => $platformTotalRetained,
            'grand_total' => self::add($merchandiseSubtotal, $deliveryFee),
        ];
    }
}
