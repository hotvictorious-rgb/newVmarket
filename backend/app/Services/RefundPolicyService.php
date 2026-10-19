<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnCase;
use App\Models\ReturnItem;

class RefundPolicyService
{
    /**
     * Calculate item-level net allocation taking proportional discounts, coupons,
     * and redeemed wallet/cashback credits into account.
     * Invariant: refundable_amount <= line_subtotal
     */
    public function calculateItemAllocation(Order $order, OrderItem $orderItem, int $quantity): array
    {
        $quantity = max(0, min($quantity, (int)$orderItem->quantity));
        $itemSubtotal = bcmul((string)$orderItem->unit_price, (string)$quantity, 2);

        if (bccomp($itemSubtotal, '0.00', 2) <= 0) {
            return [
                'unit_price' => (string)$orderItem->unit_price,
                'quantity' => 0,
                'line_total' => '0.00',
                'allocated_discount' => '0.00',
                'refundable_amount' => '0.00',
                'refundable_kobo' => 0,
            ];
        }

        // Sum of all non-cash payment sources / discounts applied to merchandise
        $totalOrderDiscounts = '0.00';
        if (!empty($order->cashback_redeemed)) {
            $totalOrderDiscounts = bcadd($totalOrderDiscounts, (string)$order->cashback_redeemed, 2);
        }
        if (!empty($order->wallet_credit_applied)) {
            $totalOrderDiscounts = bcadd($totalOrderDiscounts, (string)$order->wallet_credit_applied, 2);
        }

        $allocatedDiscount = '0.00';
        $merchSubtotal = (string)$order->merchandise_subtotal;
        if (bccomp($totalOrderDiscounts, '0.00', 2) > 0 && bccomp($merchSubtotal, '0.00', 2) > 0) {
            $lineShare = bcdiv($itemSubtotal, $merchSubtotal, 6);
            $allocatedDiscount = bcmul($totalOrderDiscounts, $lineShare, 2);
            // Cap discount at item subtotal
            if (bccomp($allocatedDiscount, $itemSubtotal, 2) > 0) {
                $allocatedDiscount = $itemSubtotal;
            }
        }

        $refundableAmount = bcsub($itemSubtotal, $allocatedDiscount, 2);
        if (bccomp($refundableAmount, '0.00', 2) < 0) {
            $refundableAmount = '0.00';
        }

        $refundableKobo = (int) bcmul($refundableAmount, '100', 0);

        return [
            'unit_price' => (string)$orderItem->unit_price,
            'quantity' => $quantity,
            'line_total' => $itemSubtotal,
            'allocated_discount' => $allocatedDiscount,
            'refundable_amount' => $refundableAmount,
            'refundable_kobo' => $refundableKobo,
        ];
    }

    /**
     * Authoritative calculation of Customer Refund and Liability Allocation.
     * Enforces VMARKET rules:
     * 1. Customer Remorse: Customer refund = eligible merchandise only. Original delivery fee is retained by courier (NOT deducted twice).
     * 2. Vendor Fault: Customer refund = merchandise + delivery fee. Vendor funds the delivery cost.
     * 3. Logistics Fault: Customer refund = merchandise + delivery fee. Logistics partner funds the loss.
     * 4. Platform earns ₦0 commission on refunded items and failed deliveries.
     */
    public function calculateRefundSettlement(
        Order $order,
        array $itemAllocations,
        string $faultParty = 'customer_remorse',
        bool $isEntireOrderRejected = false
    ): array {
        $totalMerchandiseRefund = '0.00';
        $totalMerchandiseRefundKobo = 0;

        foreach ($itemAllocations as $alloc) {
            $totalMerchandiseRefund = bcadd($totalMerchandiseRefund, (string)($alloc['refundable_amount'] ?? '0.00'), 2);
            $totalMerchandiseRefundKobo += (int)($alloc['refundable_kobo'] ?? 0);
        }

        $deliveryFee = bcadd((string)$order->delivery_fee, '0', 2);
        $eligibleDeliveryRefund = '0.00';
        $vendorDeliveryLiability = '0.00';
        $logisticsLossLiability = '0.00';

        if ($faultParty === 'customer_remorse') {
            // Customer remorse: Delivery service was successfully rendered; customer receives product refund only
            $eligibleDeliveryRefund = '0.00';
        } elseif ($faultParty === 'vendor_fault') {
            // Vendor fault: Customer did not receive what was ordered; full delivery refunded if entire delivery failed
            if ($isEntireOrderRejected && bccomp($deliveryFee, '0.00', 2) > 0) {
                $eligibleDeliveryRefund = $deliveryFee;
                $vendorDeliveryLiability = $deliveryFee;
            }
        } elseif ($faultParty === 'logistics_fault') {
            // Transit damage: Logistics failed delivery obligations
            if (bccomp($deliveryFee, '0.00', 2) > 0) {
                $eligibleDeliveryRefund = $deliveryFee;
            }
            $logisticsLossLiability = bcadd($totalMerchandiseRefund, $eligibleDeliveryRefund, 2);
        }

        $totalCustomerRefund = bcadd($totalMerchandiseRefund, $eligibleDeliveryRefund, 2);
        $totalCustomerRefundKobo = (int) bcmul($totalCustomerRefund, '100', 0);

        return [
            'fault_party' => $faultParty,
            'merchandise_refundable' => $totalMerchandiseRefund,
            'eligible_delivery_refund' => $eligibleDeliveryRefund,
            'total_customer_refund' => $totalCustomerRefund,
            'total_customer_refund_kobo' => $totalCustomerRefundKobo,
            'vendor_delivery_liability' => $vendorDeliveryLiability,
            'logistics_loss_liability' => $logisticsLossLiability,
            'is_entire_order_rejected' => $isEntireOrderRejected,
        ];
    }
}
