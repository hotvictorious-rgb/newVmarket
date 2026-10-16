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
     * Authoritative Paystack Order Settlement Core.
     * Enforces pessimistic locking, actor IDOR check, exact kobo invariant,
     * currency check, idempotency against replay attacks, state transition,
     * 5% customer cashback maturation queue, and immutable audit log.
     */
    public function settlePaystackOrder(Order $order, array $paystackData, ?User $actorUser = null, string $channel = 'paystack_webhook'): array
    {
        return DB::transaction(function () use ($order, $paystackData, $actorUser, $channel) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder) {
                return ['success' => false, 'status' => 'NOT_FOUND', 'message' => 'Order record not found under lock.'];
            }

            // 1. Actor IDOR Authorization Check
            if ($actorUser) {
                if ($actorUser->status !== 'active') {
                    return ['success' => false, 'status' => 'UNAUTHORIZED', 'message' => 'Unauthorized: User account is inactive.'];
                }
                if ($lockedOrder->customer_id && !$actorUser->isSuperAdmin() && (int)$lockedOrder->customer_id !== (int)$actorUser->id) {
                    return ['success' => false, 'status' => 'UNAUTHORIZED', 'message' => 'Unauthorized: You are not authorized to settle this customer order.'];
                }
            }

            // 2. IDEMPOTENCY GUARD: Already settled payment (Prevents duplicate captures / cashback replays)
            if ($lockedOrder->payment_status === 'paid') {
                return [
                    'success' => true,
                    'status' => 'ALREADY_PAID',
                    'is_replayed' => true,
                    'message' => 'Payment has already been successfully verified and settled.',
                    'order' => $lockedOrder,
                ];
            }

            // 3. Status Invariant
            $txStatus = strtolower((string)($paystackData['status'] ?? ''));
            if ($txStatus !== 'success') {
                return [
                    'success' => false,
                    'status' => 'STATUS_NOT_SUCCESS',
                    'message' => "Gateway status '{$txStatus}' is not successful.",
                ];
            }

            // 4. Currency Invariant
            $currency = strtoupper((string)($paystackData['currency'] ?? ''));
            if ($currency !== 'NGN') {
                return [
                    'success' => false,
                    'status' => 'CURRENCY_MISMATCH',
                    'message' => "Currency mismatch: gateway sent '{$currency}', expected 'NGN'.",
                ];
            }

            // 5. Exact Mathematical Integer Kobo Equality Guard (BCMath Zero Epsilon)
            $grandTotalNormalized = bcadd((string)$lockedOrder->grand_total, '0', 2);
            $expectedKobo = (int) bcmul($grandTotalNormalized, '100', 0);
            $actualKobo = (int) ($paystackData['amount'] ?? 0);

            if ($actualKobo !== $expectedKobo) {
                return [
                    'success' => false,
                    'status' => 'AMOUNT_MISMATCH',
                    'message' => "Mathematical amount mismatch: expected {$expectedKobo} kobo, gateway paid {$actualKobo} kobo.",
                ];
            }

            // 6. Reference Invariant
            $reference = (string)($paystackData['reference'] ?? '');
            if (!empty($lockedOrder->payment_reference) && !hash_equals((string)$lockedOrder->payment_reference, $reference)) {
                return [
                    'success' => false,
                    'status' => 'REFERENCE_MISMATCH',
                    'message' => "Payment reference mismatch: order expected '{$lockedOrder->payment_reference}', received '{$reference}'.",
                ];
            }

            // 7. Customer Identity Invariant
            $payerEmail = strtolower(trim((string)($paystackData['customer']['email'] ?? '')));
            $orderCustomerEmail = strtolower(trim((string)($lockedOrder->customer->email ?? '')));
            if (!empty($payerEmail) && !empty($orderCustomerEmail) && $payerEmail !== $orderCustomerEmail) {
                return [
                    'success' => false,
                    'status' => 'CUSTOMER_MISMATCH',
                    'message' => "Customer identity mismatch: payer email '{$payerEmail}' does not match order owner '{$orderCustomerEmail}'.",
                ];
            }

            // 8. Atomic State Transition
            $now = Carbon::now();
            $oldPaymentStatus = $lockedOrder->payment_status;
            $lockedOrder->payment_status = 'paid';
            $lockedOrder->paid_at = $now;
            $lockedOrder->payment_channel = (string)($paystackData['channel'] ?? 'paystack');
            $lockedOrder->paystack_transaction_id = (string)($paystackData['id'] ?? $reference);
            if ($lockedOrder->order_status === 'pending') {
                $lockedOrder->order_status = 'confirmed';
            }
            $lockedOrder->vendor_settlement_status = 'held';
            $lockedOrder->save();

            // 9. Register 5% Customer Cashback Reward in Ledger (24h maturation lock)
            if ($lockedOrder->customer_id && bccomp((string)$lockedOrder->customer_cashback_5, '0.00', 2) > 0) {
                CustomerCashbackLedger::updateOrCreate([
                    'order_id' => $lockedOrder->id,
                ], [
                    'customer_id' => $lockedOrder->customer_id,
                    'amount' => $lockedOrder->customer_cashback_5,
                    'status' => 'pending_maturation',
                    'matures_at' => $now->copy()->addHours(24),
                    'expires_at' => $now->copy()->addDays(365),
                ]);
            }

            // 10. Immutable Audit Trail
            AdminAuditLog::create([
                'user_id' => $actorUser ? $actorUser->id : null,
                'action' => 'paystack_payment_verified',
                'target_type' => Order::class,
                'target_id' => $lockedOrder->id,
                'old_values' => [
                    'payment_status' => $oldPaymentStatus,
                ],
                'new_values' => [
                    'payment_status' => 'paid',
                    'payment_channel' => $lockedOrder->payment_channel,
                    'paystack_reference' => $reference,
                    'paystack_transaction_id' => $lockedOrder->paystack_transaction_id,
                    'paid_at' => $lockedOrder->paid_at->toIso8601String(),
                    'grand_total' => $lockedOrder->grand_total,
                    'amount_kobo' => $actualKobo,
                    'channel_source' => $channel,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            // 11. Multi-Channel Alert to Customer & Vendor
            NotificationDispatchService::dispatchOrderPlaced($lockedOrder);

            return [
                'success' => true,
                'status' => 'PAID',
                'is_replayed' => false,
                'message' => 'Payment verified and order successfully confirmed.',
                'order' => $lockedOrder,
            ];
        });
    }
    /**
     * Handshake 1A: Vendor signals courier arrival / handover readiness at branch counter.
     * Sets vendor_handover_ready_at, updates order status to ready_for_pickup,
     * and reveals the secret 6-digit pickup_verification_code to the vendor.
     */
    public function markVendorHandoverReady(Order $order, $merchantUser = null): array
    {
        return DB::transaction(function () use ($order, $merchantUser) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            // Authorization & Branch Isolation Guard for Merchant Owner / Counter Staff
            if ($merchantUser) {
                if ($merchantUser->status !== 'active') {
                    return ['success' => false, 'message' => 'Unauthorized: User account is inactive.'];
                }

                if ($merchantUser->isVendorEmployee()) {
                    $employee = $merchantUser->vendorEmployeeProfile;
                    if (!$employee || !$employee->is_active || !$employee->canAccessBranch($lockedOrder->shop_id)) {
                        return ['success' => false, 'message' => 'Unauthorized: Branch isolation prevents access to this order.'];
                    }
                } elseif ($merchantUser->isVendor()) {
                    $vendor = $merchantUser->vendorProfile;
                    if (!$vendor || (int)$vendor->id !== (int)$lockedOrder->vendor_id) {
                        return ['success' => false, 'message' => 'Unauthorized: Merchant ownership barrier prevents access to this order.'];
                    }
                    if ($vendor->marketplace_status !== 'active') {
                        return ['success' => false, 'message' => 'Unauthorized: Merchant marketplace account is suspended.'];
                    }
                }
            }

            if (!in_array($lockedOrder->order_status, ['confirmed', 'processing', 'ready_for_pickup'])) {
                return [
                    'success' => false,
                    'message' => "Order cannot be staged for handover in status {$lockedOrder->order_status}.",
                ];
            }

            $lockedOrder->vendor_handover_ready_at = Carbon::now();
            $lockedOrder->order_status = 'ready_for_pickup';
            $lockedOrder->pickup_failed_attempts = 0;
            $lockedOrder->pickup_locked_until = null;
            $lockedOrder->save();

            // Dispatch handover authorized alert with OTP to merchant counter & alert assigned courier
            NotificationDispatchService::dispatchVendorHandoverReady($lockedOrder);

            return [
                'success' => true,
                'message' => 'Handover authorized! Show the 6-digit pickup code to the courier at the counter.',
                'pickup_otp' => $lockedOrder->pickup_verification_code,
                'order' => $lockedOrder,
            ];
        });
    }

    /**
     * Handshake 1B: Rider collects package from Vendor.
     * Courier inputs the secret 6-digit pickup_verification_code on their own phone,
     * with optional package condition photo proof.
     * Enforces strict authorization, vendor readiness, non-collision, and timed lockout protection.
     */
    public function verifyRiderPickupOtp(
        Order $order,
        string $otp,
        RiderProfile $rider,
        ?string $proofImagePath = null,
        ?string $pickupNotes = null
    ): array {
        return DB::transaction(function () use ($order, $otp, $rider, $proofImagePath, $pickupNotes) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!in_array($lockedOrder->order_status, ['confirmed', 'processing', 'ready_for_pickup'])) {
                return ['success' => false, 'message' => "Order cannot be picked up in status {$lockedOrder->order_status}."];
            }

            // Strict 6-Digit Format Validation
            if (!preg_match('/^[0-9]{6}$/', trim($otp))) {
                return ['success' => false, 'message' => 'Pickup verification code must be exactly 6 digits.'];
            }

            // Courier Operational Clearance Check
            if (!$rider->is_active) {
                return ['success' => false, 'message' => 'Courier account is currently inactive.'];
            }
            if ($rider->user && $rider->user->status !== 'active') {
                return ['success' => false, 'message' => 'Courier account is currently suspended.'];
            }
            if ($rider->logisticsCompany && $rider->logisticsCompany->status !== 'active') {
                return ['success' => false, 'message' => 'Courier logistics fleet is currently suspended.'];
            }

            // Merchant Operational Clearance Check
            if ($lockedOrder->vendor && $lockedOrder->vendor->marketplace_status !== 'active') {
                return ['success' => false, 'message' => 'Merchant marketplace account is currently suspended.'];
            }

            // Enforce Rider Assignment Authorization
            if ($lockedOrder->rider_id && (int)$lockedOrder->rider_id !== (int)$rider->id) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized: This order is assigned to another dispatch courier.',
                ];
            }

            // Enforce Vendor Handover Readiness
            if (!$lockedOrder->isVendorHandoverReady() && $lockedOrder->order_status !== 'ready_for_pickup') {
                return [
                    'success' => false,
                    'message' => 'Merchant handover has not been authorized yet. Please ask merchant counter staff to click "Handover Ready" on their screen.',
                ];
            }

            // Timed Lockout Protection: Check if pickup is currently locked
            if ($lockedOrder->isPickupLocked()) {
                $minutesRemaining = max(1, Carbon::now()->diffInMinutes($lockedOrder->pickup_locked_until));
                return [
                    'success' => false,
                    'message' => "Pickup verification locked due to excessive failed attempts. Try again in {$minutesRemaining} minute(s) or request merchant re-authorization at counter.",
                ];
            }

            // Mathematical Equality Check (Constant-time hash comparison prevents side-channel leaks)
            if (!hash_equals((string)$lockedOrder->pickup_verification_code, trim($otp))) {
                $lockedOrder->increment('pickup_failed_attempts');
                if ($lockedOrder->pickup_failed_attempts >= 5) {
                    $lockedOrder->pickup_locked_until = Carbon::now()->addMinutes(15);
                    $lockedOrder->save();
                    return [
                        'success' => false,
                        'message' => 'Pickup verification temporarily locked for 15 minutes due to 5 consecutive incorrect attempts. Merchant can re-authorize at counter.',
                    ];
                }
                return [
                    'success' => false,
                    'message' => 'Invalid merchant pickup verification OTP. Please verify with counter staff.',
                ];
            }

            // Reset failed attempts & lockout upon successful authentication
            $lockedOrder->pickup_failed_attempts = 0;
            $lockedOrder->pickup_locked_until = null;
            $lockedOrder->order_status = 'out_for_delivery';
            $lockedOrder->pickup_confirmed_by_rider = true;
            $lockedOrder->picked_up_at = Carbon::now();
            $lockedOrder->rider_id = $rider->id;

            if ($proofImagePath) {
                $lockedOrder->pickup_proof_image = $proofImagePath;
            }

            if ($pickupNotes) {
                $lockedOrder->pickup_notes = $pickupNotes;
            }

            $lockedOrder->save();

            // Multi-channel alert to customer and vendor
            NotificationDispatchService::dispatchRiderPickedUp($lockedOrder);

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

            // Enforce Rider Assignment Authorization
            if ($lockedOrder->rider_id && (int)$lockedOrder->rider_id !== (int)$rider->id) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized: This delivery is assigned to another dispatch courier.',
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

            // Strict 6-Digit Format Validation
            if (!preg_match('/^[0-9]{6}$/', trim($otp))) {
                return ['success' => false, 'message' => 'Delivery verification code must be exactly 6 digits.'];
            }

            // Lockout Protection: Check if delivery verification is currently locked
            if ($lockedOrder->isDeliveryLocked()) {
                $minutesRemaining = max(1, Carbon::now()->diffInMinutes($lockedOrder->delivery_locked_until));
                return [
                    'success' => false,
                    'message' => "Delivery verification locked due to excessive failed attempts. Try again in {$minutesRemaining} minute(s).",
                ];
            }

            // Courier Hardening: Validate courier assignment & active status
            if ($rider) {
                if (!$rider->is_active) {
                    return ['success' => false, 'message' => 'Unauthorized: Dispatch courier profile is suspended or inactive.'];
                }
                if ($rider->user && $rider->user->status !== 'active') {
                    return ['success' => false, 'message' => 'Unauthorized: Courier user account is inactive.'];
                }
                if ($rider->logisticsCompany && $rider->logisticsCompany->status !== 'active') {
                    return ['success' => false, 'message' => 'Unauthorized: Parent logistics fleet account is inactive.'];
                }
                if ($lockedOrder->rider_id && (int)$lockedOrder->rider_id !== (int)$rider->id) {
                    return ['success' => false, 'message' => 'Unauthorized: This order is assigned to another delivery courier.'];
                }
            }

            // Merchant Hardening: Validate merchant active status
            if ($lockedOrder->vendor && $lockedOrder->vendor->marketplace_status !== 'active') {
                return ['success' => false, 'message' => 'Merchant marketplace account is suspended. Delivery settlement halted.'];
            }

            if (!hash_equals((string)$lockedOrder->verification_code, trim($otp))) {
                $lockedOrder->increment('delivery_failed_attempts');
                if ($lockedOrder->delivery_failed_attempts >= 5) {
                    $lockedOrder->delivery_locked_until = Carbon::now()->addMinutes(15);
                    $lockedOrder->save();
                    return [
                        'success' => false,
                        'message' => 'Delivery verification temporarily locked for 15 minutes due to 5 consecutive incorrect attempts.',
                    ];
                }
                return ['success' => false, 'message' => 'Invalid customer delivery verification OTP. Please check your phone SMS or screen.'];
            }

            $lockedOrder->delivery_failed_attempts = 0;
            $lockedOrder->delivery_locked_until = null;

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
        if ($staffUser->status !== 'active') {
            return ['success' => false, 'message' => 'Unauthorized: User account is inactive.'];
        }

        if ($staffUser->isVendorEmployee()) {
            $employee = $staffUser->vendorEmployeeProfile;
            if (!$employee || !$employee->is_active || !$employee->canAccessBranch($reservation->shop_id)) {
                return ['success' => false, 'message' => 'Unauthorized: Branch isolation prevents access to this shop.'];
            }
        } elseif ($staffUser->isVendor()) {
            $vendor = $staffUser->vendorProfile;
            if (!$vendor || (int)$vendor->id !== (int)$reservation->vendor_id) {
                return ['success' => false, 'message' => 'Unauthorized: Merchant ownership mismatch.'];
            }
            if ($vendor->marketplace_status !== 'active') {
                return ['success' => false, 'message' => 'Merchant marketplace account is suspended.'];
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
        } elseif ($staffUser->isVendor()) {
            $vendor = $staffUser->vendorProfile;
            if (!$vendor || (int)$vendor->id !== (int)$order->vendor_id) {
                return ['success' => false, 'message' => 'Unauthorized: Merchant ownership barrier prevents access to this order.'];
            }
        }

        // Strict 6-Digit Format Validation
        if (!preg_match('/^[0-9]{6}$/', trim($otp))) {
            return ['success' => false, 'message' => 'Handover verification code must be exactly 6 digits.'];
        }

        return DB::transaction(function () use ($order, $otp, $staffUser) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if ($lockedOrder->fulfillment_type !== 'in_shop_pickup') {
                return ['success' => false, 'message' => 'This is not an in-shop pickup order.'];
            }

            if ($lockedOrder->payment_status !== 'paid') {
                return ['success' => false, 'message' => 'Goods cannot be handed over before verified payment.'];
            }

            // Timed Lockout Protection: Check if handover verification is locked
            if ($lockedOrder->isPickupLocked()) {
                $minutesRemaining = max(1, Carbon::now()->diffInMinutes($lockedOrder->pickup_locked_until));
                return [
                    'success' => false,
                    'message' => "Handover verification locked due to excessive failed attempts. Try again in {$minutesRemaining} minute(s).",
                ];
            }

            if (!hash_equals((string)$lockedOrder->verification_code, trim($otp))) {
                $lockedOrder->increment('pickup_failed_attempts');
                if ($lockedOrder->pickup_failed_attempts >= 5) {
                    $lockedOrder->pickup_locked_until = Carbon::now()->addMinutes(15);
                    $lockedOrder->save();
                    return [
                        'success' => false,
                        'message' => 'Handover verification temporarily locked for 15 minutes due to 5 consecutive incorrect attempts.',
                    ];
                }
                return ['success' => false, 'message' => 'Invalid pickup handover OTP.'];
            }

            $lockedOrder->pickup_failed_attempts = 0;
            $lockedOrder->pickup_locked_until = null;

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

            // Administrator Authorization & Status Check
            if ($adminUser->status !== 'active') {
                return ['success' => false, 'message' => 'Unauthorized: Administrator account is inactive.'];
            }

            if (!$adminUser->isSuperAdmin() && !$adminUser->isAdminEmployee()) {
                return ['success' => false, 'message' => 'Unauthorized: User lacks administrative financial disbursement privileges.'];
            }

            // Documented Bank Transfer Proof Validation
            if (empty(trim($proofPath))) {
                return ['success' => false, 'message' => 'Settlement disbursement requires documented bank transfer proof.'];
            }

            if ($lockedOrder->vendor_settlement_status !== 'eligible') {
                return [
                    'success' => false,
                    'message' => "Order is not eligible for disbursement (current state: {$lockedOrder->vendor_settlement_status}).",
                ];
            }

            // Merchant Integrity & 48-Hour Bank Cooldown Defense
            $vendor = $lockedOrder->vendor;
            if (!$vendor) {
                return ['success' => false, 'message' => 'Merchant profile not found for this order.'];
            }

            if ($vendor->marketplace_status !== 'active') {
                return [
                    'success' => false,
                    'message' => "Disbursement blocked: Merchant marketplace account is {$vendor->marketplace_status}.",
                ];
            }

            if ($vendor->isWithinBankCooldown()) {
                return [
                    'success' => false,
                    'message' => 'Disbursement blocked: Merchant bank account was recently updated and is under a 48-hour security cooldown.',
                ];
            }

            $lockedOrder->vendor_settlement_status = 'settled';
            $lockedOrder->settled_at = Carbon::now();
            $lockedOrder->settled_by_admin_id = $adminUser->id;
            $lockedOrder->settlement_proof_path = $proofPath;
            $lockedOrder->save();

            // Immutable Financial Audit Log
            AdminAuditLog::create([
                'user_id' => $adminUser->id,
                'action' => 'vendor_settlement_disbursed',
                'target_type' => Order::class,
                'target_id' => $lockedOrder->id,
                'old_values' => [
                    'vendor_settlement_status' => 'eligible',
                ],
                'new_values' => [
                    'vendor_settlement_status' => 'settled',
                    'vendor_net_90' => $lockedOrder->vendor_net_90,
                    'vendor_id' => $vendor->id,
                    'merchant_name' => $vendor->business_name,
                    'proof_path' => $proofPath,
                    'settled_at' => $lockedOrder->settled_at->toIso8601String(),
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'message' => "Settlement of ₦{$lockedOrder->vendor_net_90} disbursed successfully.",
                'order' => $lockedOrder,
            ];
        });
    }
}
