<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\CustomerCashbackLedger;
use App\Models\Order;
use App\Models\PickupReservation;
use App\Models\RiderProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderSettlementService
{
    /**
     * Handshake 1: Rider collects package from Vendor.
     * Verifies the secret 6-digit pickup_verification_code.
     */
    public function verifyRiderPickupOtp(Order $order, string $otp, RiderProfile $rider): array
    {
        return DB::transaction(function () use ($order, $otp, $rider) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!in_array($lockedOrder->order_status, ['confirmed', 'processing', 'ready_for_pickup'])) {
                return ['success' => false, 'message' => "Order cannot be picked up in status {$lockedOrder->order_status}."];
            }

            if (!hash_equals((string)$lockedOrder->pickup_verification_code, trim($otp))) {
                return ['success' => false, 'message' => 'Invalid pickup verification OTP.'];
            }

            $lockedOrder->order_status = 'out_for_delivery';
            $lockedOrder->rider_id = $rider->id;
            $lockedOrder->save();

            return [
                'success' => true,
                'message' => 'Pickup verified successfully. Order is out for delivery.',
                'order' => $lockedOrder,
            ];
        });
    }

    /**
     * Handshake 2A: Rider arrives at Customer Doorstep.
     * Records arrival time and signals customer phone to open verification prompt.
     */
    public function markRiderArrived(Order $order, RiderProfile $rider): array
    {
        return DB::transaction(function () use ($order, $rider) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if ($lockedOrder->order_status !== 'out_for_delivery') {
                return [
                    'success' => false,
                    'message' => "Order cannot be marked arrived in status {$lockedOrder->order_status}.",
                ];
            }

            $lockedOrder->rider_arrived_at = Carbon::now();
            if (!$lockedOrder->rider_id) {
                $lockedOrder->rider_id = $rider->id;
            }
            $lockedOrder->save();

            // Multi-channel SMS & WhatsApp alert to customer with secret OTP
            NotificationDispatchService::dispatchRiderArrived($lockedOrder);

            return [
                'success' => true,
                'message' => 'Arrival signaled! Handshake prompt is now active on the customer\'s phone.',
                'order' => $lockedOrder,
            ];
        });
    }

    /**
     * Handshake 2B: Customer enters 6-digit PIN on their own device with optional photo snap.
     * Verifies verification_code, sets delivered, enqueues cashback, and credits rider wallet.
     */
    public function verifyCustomerDeliveryOtp(
        Order $order,
        string $otp,
        ?RiderProfile $rider = null,
        ?string $proofImagePath = null,
        ?string $customerNotes = null
    ): array {
        return DB::transaction(function () use ($order, $otp, $rider, $proofImagePath, $customerNotes) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if ($lockedOrder->order_status !== 'out_for_delivery') {
                return ['success' => false, 'message' => 'Order is not currently out for delivery.'];
            }

            if (!hash_equals((string)$lockedOrder->verification_code, trim($otp))) {
                return ['success' => false, 'message' => 'Invalid customer delivery verification OTP. Please check your phone SMS or screen.'];
            }

            $now = Carbon::now();
            $lockedOrder->order_status = 'delivered';
            $lockedOrder->delivery_confirmed_by_customer = true;
            $lockedOrder->received_at = $now;
            $lockedOrder->refund_window_expires_at = $now->copy()->addHours(24);
            $lockedOrder->vendor_settlement_status = 'held';

            if ($proofImagePath) {
                $lockedOrder->proof_of_delivery_image = $proofImagePath;
            }

            if ($customerNotes) {
                $lockedOrder->customer_delivery_notes = $customerNotes;
            }

            if (!$lockedOrder->rider_id && $rider) {
                $lockedOrder->rider_id = $rider->id;
            }

            $lockedOrder->save();

            // Corporate B2B Settlement: Credit 90% of delivery fee to accredited Logistics Company Corporate Wallet
            // Victorious Market retains 10% platform delivery fee cut
            $targetRider = $rider ?? ($lockedOrder->rider_id ? RiderProfile::with('logisticsCompany')->find($lockedOrder->rider_id) : null);
            $logisticsCompany = null;
            if ($lockedOrder->collection_point_id) {
                $collectionPoint = \App\Models\LogisticsCollectionPoint::find($lockedOrder->collection_point_id);
                $logisticsCompany = $collectionPoint ? $collectionPoint->company : null;
            }
            if (!$logisticsCompany && $targetRider && $targetRider->logistics_company_id) {
                $logisticsCompany = $targetRider->logisticsCompany;
            }
            if (!$logisticsCompany && $lockedOrder->delivery_lane_id) {
                $lane = \App\Models\DeliveryLane::find($lockedOrder->delivery_lane_id);
                $logisticsCompany = $lane ? $lane->assignedLogisticsCompany : null;
            }
            if (!$logisticsCompany) {
                $logisticsCompany = \App\Models\LogisticsCompanyProfile::where('status', 'active')->first();
            }

            // Authoritative 90% Logistics Net Calculation via BCMath
            $logisticsNetCut = $lockedOrder->logistics_fee_retained;
            if (empty($logisticsNetCut) || bccomp((string)$logisticsNetCut, '0.00', 2) === 0) {
                $logisticsNetCut = bcmul((string)($lockedOrder->delivery_fee ?? '0.00'), '0.90', 2);
            }

            if ($logisticsCompany && bccomp((string)$logisticsNetCut, '0.00', 2) > 0) {
                $logisticsCompany->creditWallet((string)$logisticsNetCut);
            }

            if ($targetRider) {
                $currentBalance = (float) ($targetRider->current_balance ?? 0);
                $deliveryCut = (float) ($lockedOrder->delivery_fee ?? 0);
                $targetRider->current_balance = number_format($currentBalance + $deliveryCut, 2, '.', '');
                $targetRider->save();
            }

            // Enqueue 5% Customer Cashback reward with 24h maturation lock
            CustomerCashbackLedger::updateOrCreate([
                'order_id' => $lockedOrder->id,
            ], [
                'customer_id' => $lockedOrder->customer_id,
                'amount' => $lockedOrder->customer_cashback_5,
                'status' => 'pending_maturation',
                'matures_at' => $lockedOrder->refund_window_expires_at,
                'expires_at' => $lockedOrder->refund_window_expires_at->copy()->addDays(365),
            ]);

            // Multi-channel notifications to Logistics Fleet, Customer, and Vendor
            NotificationDispatchService::dispatchOrderDelivered($lockedOrder);

            return [
                'success' => true,
                'message' => 'Package delivery confirmed successfully! 24-hour return window is now active.',
                'order' => $lockedOrder,
            ];
        });
    }

    /**
     * Handshake 3: In-Shop Physical Inspection Sign-Off.
     * Store employee marks reservation inspected and accepted by customer.
     */
    public function approvePickupInspection(PickupReservation $reservation, User $staffUser): array
    {
        if ($staffUser->isVendorEmployee()) {
            $employee = $staffUser->vendorEmployeeProfile;
            if (!$employee || !$employee->canAccessBranch($reservation->shop_id)) {
                return ['success' => false, 'message' => 'Unauthorized: Branch isolation prevents access to this shop.'];
            }
        }

        if ($reservation->status !== 'reserved') {
            return ['success' => false, 'message' => "Reservation cannot be inspected in status {$reservation->status}."];
        }

        if ($reservation->isExpired()) {
            $reservation->status = 'expired';
            $reservation->save();
            return ['success' => false, 'message' => 'This reservation has expired.'];
        }

        $reservation->status = 'inspected_accepted';
        $reservation->inspected_at = Carbon::now();
        $reservation->inspected_by_staff_id = $staffUser->id;
        $reservation->save();

        return [
            'success' => true,
            'message' => 'Inspection approved. Customer digital payment is now unlocked.',
            'reservation' => $reservation,
        ];
    }

    /**
     * Handshake 4: In-Shop Counter Handover OTP Verification.
     * Goods released to customer after verified digital payment.
     */
    public function verifyPickupHandoverOtp(Order $order, string $otp, User $staffUser): array
    {
        if ($staffUser->isVendorEmployee()) {
            $employee = $staffUser->vendorEmployeeProfile;
            if (!$employee || !$employee->canAccessBranch($order->shop_id)) {
                return ['success' => false, 'message' => 'Unauthorized: Branch isolation prevents access to this shop.'];
            }
        }

        return DB::transaction(function () use ($order, $otp, $staffUser) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if ($lockedOrder->fulfillment_type !== 'in_shop_pickup') {
                return ['success' => false, 'message' => 'This is not an in-shop pickup order.'];
            }

            if ($lockedOrder->payment_status !== 'paid') {
                return ['success' => false, 'message' => 'Goods cannot be handed over before verified payment.'];
            }

            if (!hash_equals((string)$lockedOrder->verification_code, trim($otp))) {
                return ['success' => false, 'message' => 'Invalid pickup handover OTP.'];
            }

            $now = Carbon::now();
            $lockedOrder->order_status = 'delivered';
            $lockedOrder->received_at = $now;
            $lockedOrder->refund_window_expires_at = $now->copy()->addHours(24);
            $lockedOrder->vendor_settlement_status = 'held';
            $lockedOrder->save();

            // Mark associated reservation handed over
            PickupReservation::where('order_id', $lockedOrder->id)->update([
                'status' => 'handed_over',
            ]);

            // Enqueue 5% Customer Cashback reward
            CustomerCashbackLedger::updateOrCreate([
                'order_id' => $lockedOrder->id,
            ], [
                'customer_id' => $lockedOrder->customer_id,
                'amount' => $lockedOrder->customer_cashback_5,
                'status' => 'pending_maturation',
                'matures_at' => $lockedOrder->refund_window_expires_at,
                'expires_at' => $lockedOrder->refund_window_expires_at->copy()->addDays(365),
            ]);

            return [
                'success' => true,
                'message' => 'Goods handed over successfully. 24-hour return window has commenced.',
                'order' => $lockedOrder,
            ];
        });
    }

    /**
     * Evaluates order post-receipt maturation (called via scheduler/cron).
     * Transitions vendor settlement to 'eligible' and cashback to 'available' if 24h expired with zero disputes.
     */
    public function evaluateMaturation(Order $order): void
    {
        if ($order->order_status !== 'delivered' || !$order->isReturnWindowExpired()) {
            return;
        }

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            // Check if active dispute exists
            $hasDispute = $lockedOrder->dispute()->whereIn('status', ['filed', 'under_review'])->exists();
            if ($hasDispute) {
                $lockedOrder->vendor_settlement_status = 'disputed';
                $lockedOrder->save();

                CustomerCashbackLedger::where('order_id', $lockedOrder->id)->update([
                    'status' => 'quarantined_dispute',
                ]);
                return;
            }

            if ($lockedOrder->vendor_settlement_status === 'held') {
                $lockedOrder->vendor_settlement_status = 'eligible';
                $lockedOrder->save();
            }

            CustomerCashbackLedger::where('order_id', $lockedOrder->id)
                ->where('status', 'pending_maturation')
                ->update(['status' => 'available']);
        });
    }

    /**
     * Super Admin Manual Settlement Disbursement with Mandatory Bank Transfer Proof.
     */
    public function disburseVendorSettlement(Order $order, User $adminUser, string $proofPath): array
    {
        return DB::transaction(function () use ($order, $adminUser, $proofPath) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if ($lockedOrder->vendor_settlement_status !== 'eligible') {
                return [
                    'success' => false,
                    'message' => "Order is not eligible for disbursement (current state: {$lockedOrder->vendor_settlement_status}).",
                ];
            }

            $lockedOrder->vendor_settlement_status = 'settled';
            $lockedOrder->settled_at = Carbon::now();
            $lockedOrder->settled_by_admin_id = $adminUser->id;
            $lockedOrder->settlement_proof_path = $proofPath;
            $lockedOrder->save();

            // Audit log
            AdminAuditLog::create([
                'user_id' => $adminUser->id,
                'action' => 'vendor_settlement_disbursed',
                'target_type' => Order::class,
                'target_id' => $lockedOrder->id,
                'new_values' => [
                    'vendor_net_90' => $lockedOrder->vendor_net_90,
                    'proof_path' => $proofPath,
                    'settled_at' => $lockedOrder->settled_at->toIso8601String(),
                ],
            ]);

            return [
                'success' => true,
                'message' => "Settlement of ₦{$lockedOrder->vendor_net_90} disbursed successfully.",
                'order' => $lockedOrder,
            ];
        });
    }
}
