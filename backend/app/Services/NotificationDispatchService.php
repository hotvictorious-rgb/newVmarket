<?php

namespace App\Services;

use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificationDispatchService
{
    protected const CACHE_KEY = 'vmarket_simulated_notifications';

    /**
     * Dispatch multi-channel notification for Order Creation
     * NOTE: Security Invariant — pickup OTP is NEVER disclosed at order placement.
     * It is only revealed after physical merchant verification and handover authorization.
     */
    public static function dispatchOrderPlaced(Order $order): array
    {
        $dispatches = [];

        // 1. Customer SMS
        $customerPhone = $order->customer->phone ?? '+2348000000000';
        $customerMsg = "Victorious Market: Order {$order->order_number} confirmed! Total ₦" . number_format((float)$order->grand_total, 2) . ". Track live: " . route('order.status', $order->order_number);
        $dispatches[] = self::record('sms', $customerPhone, 'Customer', $customerMsg);

        // 2. Vendor Alert (Zero early disclosure of pickup OTP)
        $vendorPhone = $order->vendor->business_phone ?? '+2348000000001';
        $vendorMsg = "Victorious Market Plaza Alert: New Order {$order->order_number} received! Please inspect and prepare package. Pickup OTP will be issued upon handover authorization.";
        $dispatches[] = self::record('whatsapp', $vendorPhone, 'Vendor', $vendorMsg);

        return $dispatches;
    }

    /**
     * Dispatch notification when Merchant / Counter authorizes handover and reveals OTP
     */
    public static function dispatchVendorHandoverReady(Order $order): array
    {
        $dispatches = [];

        // 1. Vendor Counter Alert with Secret 6-digit PIN
        $vendorPhone = $order->vendor->business_phone ?? '+2348000000001';
        $vendorMsg = "Victorious Market Counter Authorized: Handover OTP for Order {$order->order_number} is [{$order->pickup_verification_code}]. Verify assigned courier identity before releasing package.";
        $dispatches[] = self::record('whatsapp', $vendorPhone, 'Vendor Counter', $vendorMsg);

        // 2. Assigned Courier Alert: Package is sealed and ready for collection
        if ($order->rider && $order->rider->user) {
            $riderPhone = $order->rider->user->phone ?? '+2348000000003';
            $shopName = $order->shop->name ?? 'Merchant Shop';
            $riderMsg = "Victorious Market: Order {$order->order_number} is packed and ready for pickup at {$shopName}. Present yourself to counter staff to collect package.";
            $dispatches[] = self::record('sms', $riderPhone, 'Assigned Courier', $riderMsg);
        }

        return $dispatches;
    }

    /**
     * Dispatch multi-channel notification for Rider Counter Pickup Handshake
     */
    public static function dispatchRiderPickedUp(Order $order): array
    {
        $dispatches = [];

        // 1. Customer Notification: Package in transit
        $customerPhone = $order->customer->phone ?? '+2348000000000';
        $riderName = $order->rider->user->name ?? 'Dispatch Courier';
        $shopName = $order->shop->name ?? 'Merchant';
        $customerMsg = "Victorious Market: Your package for Order {$order->order_number} has been picked up from {$shopName} by courier {$riderName} and is now en route to you! Track: " . route('order.status', $order->order_number);
        $dispatches[] = self::record('sms', $customerPhone, 'Customer', $customerMsg);

        // 2. Vendor Notification: Handover confirmed
        $vendorPhone = $order->vendor->business_phone ?? '+2348000000001';
        $vendorMsg = "Victorious Market: Order {$order->order_number} custody transferred to courier {$riderName}. Status is now Out for Delivery.";
        $dispatches[] = self::record('whatsapp', $vendorPhone, 'Vendor', $vendorMsg);

        return $dispatches;
    }

    /**
     * Dispatch multi-channel notification for Rider Doorstep Arrival
     */
    public static function dispatchRiderArrived(Order $order): array
    {
        $dispatches = [];
        $customerPhone = $order->customer->phone ?? '+2348000000000';

        // High priority SMS & WhatsApp with 6-digit delivery OTP
        $msg = "🚨 Victorious Market Courier has arrived at your doorstep! Your secret Delivery Verification PIN is [{$order->verification_code}]. Verify package seal before releasing PIN.";
        $dispatches[] = self::record('sms', $customerPhone, 'Customer', $msg);
        $dispatches[] = self::record('whatsapp', $customerPhone, 'Customer', $msg);

        return $dispatches;
    }

    /**
     * Dispatch multi-channel notification for Delivery Handshake Completion
     */
    public static function dispatchOrderDelivered(Order $order): array
    {
        $dispatches = [];

        // 1. Logistics Company Corporate Settlement Notification
        $logisticsNet = $order->logistics_fee_retained ?? bcmul((string)$order->delivery_fee, '0.90', 2);
        $companyEmail = $order->rider->logisticsCompany->contact_email ?? 'logistics@victoriousmarket.com.ng';
        $fleetMsg = "Victorious Market B2B Settlement: Order {$order->order_number} delivered. ₦" . number_format((float)$logisticsNet, 2) . " (90% delivery cut) credited to your Corporate Wallet.";
        $dispatches[] = self::record('push', $companyEmail, 'Logistics HQ', $fleetMsg);

        // 2. Customer Cashback Reward Notification
        $customerPhone = $order->customer->phone ?? '+2348000000000';
        $cashbackMsg = "Victorious Market: Order {$order->order_number} delivery confirmed! 5% Cashback (₦" . number_format((float)$order->customer_cashback_5, 2) . ") has been added to your rewards wallet.";
        $dispatches[] = self::record('sms', $customerPhone, 'Customer', $cashbackMsg);

        // 3. Vendor Escrow Settlement Notification
        $vendorPhone = $order->vendor->business_phone ?? '+2348000000001';
        $vendorMsg = "Victorious Market: Order {$order->order_number} delivered to customer. 90% payout of ₦" . number_format((float)$order->vendor_net_90, 2) . " entered 24h sovereign buffer.";
        $dispatches[] = self::record('whatsapp', $vendorPhone, 'Vendor', $vendorMsg);

        return $dispatches;
    }

    /**
     * Dispatch notification for Corporate Bank Withdrawal Payout
     */
    public static function dispatchPayoutDisbursed(LogisticsPayoutRequest $payout): array
    {
        $companyPhone = $payout->company->contact_phone ?? '+2348000000002';
        $msg = "Victorious Market Treasury: Corporate payout of ₦" . number_format((float)$payout->amount, 2) . " successfully disbursed to {$payout->bank_name} ({$payout->account_number}).";
        
        return [
            self::record('whatsapp', $companyPhone, 'Logistics HQ', $msg)
        ];
    }

    /**
     * Records, logs and attempts production dispatch with fallback to simulation
     * Supports Termii/Twilio SMS & WhatsApp gateways when configured in environment.
     */
    protected static function record(string $channel, string $recipient, string $recipientType, string $message): array
    {
        $channelUpper = strtoupper($channel);
        $deliveryStatus = 'simulated';
        $providerRef = null;

        // Production Provider Dispatch: Check Termii (Nigeria SMS/WhatsApp gateway)
        $termiiApiKey = config('services.termii.key', env('TERMII_API_KEY'));
        if (!empty($termiiApiKey) && in_array(strtolower($channel), ['sms', 'whatsapp'])) {
            try {
                if (app()->runningUnitTests()) {
                    $deliveryStatus = 'simulated';
                    $providerRef = 'test_termii_' . uniqid();
                } else {
                    \App\Jobs\SendTermiiNotificationJob::dispatch($recipient, $channel, $message);
                    $deliveryStatus = 'queued';
                    $providerRef = 'termii_job_' . uniqid();
                }
            } catch (\Throwable $e) {
                $deliveryStatus = 'queue_dispatch_failed';
                Log::warning("Termii background dispatch error: " . $e->getMessage());
            }
        }

        $payload = [
            'channel' => $channelUpper,
            'recipient' => $recipient,
            'recipient_type' => $recipientType,
            'message' => $message,
            'delivery_status' => $deliveryStatus,
            'provider_ref' => $providerRef,
            'dispatched_at' => Carbon::now()->toIso8601String(),
        ];

        Log::info("Notification dispatched [{$channelUpper} - {$deliveryStatus}]: {$message}", $payload);

        // Retain last 30 notifications in cache for live UI audit trail
        try {
            $recent = Cache::get(self::CACHE_KEY, []);
            array_unshift($recent, $payload);
            Cache::put(self::CACHE_KEY, array_slice($recent, 0, 30), 86400);
        } catch (\Throwable $e) {
            // Ignore cache exceptions in test environments
        }

        return $payload;
    }

    /**
     * Get recent notifications
     */
    public static function getRecentDispatches(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }
}
