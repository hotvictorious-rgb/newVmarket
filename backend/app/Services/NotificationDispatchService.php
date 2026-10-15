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
     */
    public static function dispatchOrderPlaced(Order $order): array
    {
        $dispatches = [];

        // 1. Customer SMS
        $customerPhone = $order->customer->phone ?? '+2348000000000';
        $customerMsg = "Victorious Market: Order {$order->order_number} confirmed! Total ₦" . number_format((float)$order->grand_total, 2) . ". Track live: " . route('order.status', $order->order_number);
        $dispatches[] = self::record('sms', $customerPhone, 'Customer', $customerMsg);

        // 2. Vendor WhatsApp Alert
        $vendorPhone = $order->vendor->business_phone ?? '+2348000000001';
        $vendorMsg = "Victorious Market Plaza Alert: New Order {$order->order_number} received! Pickup OTP: {$order->pickup_verification_code}. Please prepare package for courier handover.";
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
     * Records and logs dispatch event
     */
    protected static function record(string $channel, string $recipient, string $recipientType, string $message): array
    {
        $payload = [
            'channel' => strtoupper($channel),
            'recipient' => $recipient,
            'recipient_type' => $recipientType,
            'message' => $message,
            'dispatched_at' => Carbon::now()->toIso8601String(),
        ];

        Log::info("Notification dispatched [{$channel}]: {$message}", $payload);

        // Retain last 30 notifications in cache for live UI simulation
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
