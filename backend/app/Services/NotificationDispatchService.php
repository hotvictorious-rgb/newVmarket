<?php

namespace App\Services;

use App\Jobs\SendFcmNotificationJob;
use App\Jobs\SendTermiiNotificationJob;
use App\Models\LogisticsPayoutRequest;
use App\Models\NotificationDeliveryLog;
use App\Models\Order;
use App\Models\SystemNotification;
use App\Models\SystemSetting;
use App\Models\VendorPayoutRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificationDispatchService
{
    protected const CACHE_KEY = 'vmarket_simulated_notifications';

    /**
     * Check if a specific delivery channel is enabled for a given business event in Admin Portal
     */
    public static function isChannelActive(string $channel, string $eventType): bool
    {
        // 1. Check Global Master Channel Switch
        $masterSettingKey = "notification_{$channel}_enabled";
        if (!SystemSetting::get($masterSettingKey, true)) {
            return false;
        }

        // 2. Check Event-by-Event Configuration Matrix
        $matrix = SystemSetting::get('notification_event_matrix');
        if (!is_array($matrix) || !isset($matrix[$eventType])) {
            return true; // Default to true if unconfigured
        }

        return !empty($matrix[$eventType][$channel]);
    }

    /**
     * Create an In-App Notification (Navbar Bell & Drawer)
     */
    public static function createInApp(int $userId, string $title, string $body, string $type, array $data = [], ?string $actionUrl = null): ?SystemNotification
    {
        try {
            $notification = SystemNotification::create([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'data' => $data,
                'action_url' => $actionUrl,
            ]);

            NotificationDeliveryLog::log('in_app', (string)$userId, $type, $title, 'delivered');
            return $notification;
        } catch (\Throwable $e) {
            Log::error("Failed to create in-app notification for User #{$userId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Dispatch an Asynchronous FCM Push Notification
     */
    public static function dispatchPush(int $userId, string $title, string $body, array $data = [], string $eventType = 'general'): void
    {
        try {
            SendFcmNotificationJob::dispatch($userId, $title, $body, $data, $eventType);
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch push notification job for User #{$userId}: " . $e->getMessage());
        }
    }

    /**
     * Event 1: Order Placed
     */
    public static function dispatchOrderPlaced(Order $order): array
    {
        $dispatches = [];
        $eventType = 'order_placed';

        // 1. Customer Notification
        if ($order->customer) {
            $customerPhone = $order->customer->phone ?? '+2348000000000';
            $customerMsg = "Victorious Market: Order {$order->order_number} confirmed! Total ₦" . number_format((float)$order->grand_total, 2) . ". Track live: " . route('order.status', $order->order_number);

            if (self::isChannelActive('sms', $eventType)) {
                $dispatches[] = self::record('sms', $customerPhone, 'Customer', $customerMsg);
            }
            if (self::isChannelActive('in_app', $eventType)) {
                self::createInApp($order->customer->id, "Order Confirmed!", "Your order #{$order->order_number} is being prepared.", $eventType, ['order_id' => $order->id], route('order.status', $order->order_number));
            }
            if (self::isChannelActive('fcm', $eventType)) {
                self::dispatchPush($order->customer->id, "Order Confirmed! #{$order->order_number}", "Total: ₦" . number_format((float)$order->grand_total, 2) . ". Your order is being prepared.", ['type' => 'order_placed', 'order_id' => $order->id], $eventType);
            }
        }

        // 2. Vendor Alert
        if ($order->vendor && $order->vendor->user) {
            $vendorUserId = $order->vendor->user_id;
            $vendorPhone = $order->vendor->business_phone ?? '+2348000000001';
            $vendorMsg = "Victorious Market Plaza Alert: New Order {$order->order_number} received! Please inspect and prepare package.";

            if (self::isChannelActive('whatsapp', $eventType)) {
                $dispatches[] = self::record('whatsapp', $vendorPhone, 'Vendor', $vendorMsg);
            }
            if (self::isChannelActive('in_app', $eventType)) {
                self::createInApp($vendorUserId, "New Order Received!", "Order #{$order->order_number} requires packaging and handover authorization.", $eventType, ['order_id' => $order->id]);
            }
            if (self::isChannelActive('fcm', $eventType)) {
                self::dispatchPush($vendorUserId, "New Order Received! #{$order->order_number}", "A customer has placed an order. Please inspect inventory.", ['type' => 'order_placed', 'order_id' => $order->id], $eventType);
            }
        }

        return $dispatches;
    }

    /**
     * Event 2: Vendor Handover Ready (Courier Dispatch Alert)
     */
    public static function dispatchVendorHandoverReady(Order $order): array
    {
        $dispatches = [];
        $eventType = 'vendor_handover_ready';

        // 1. Vendor Counter Alert with Secret 6-digit PIN
        if ($order->vendor && $order->vendor->user) {
            $vendorUserId = $order->vendor->user_id;
            $vendorPhone = $order->vendor->business_phone ?? '+2348000000001';
            $vendorMsg = "Victorious Market Counter Authorized: Handover OTP for Order {$order->order_number} is [{$order->pickup_verification_code}]. Verify assigned courier identity before releasing package.";

            if (self::isChannelActive('whatsapp', $eventType)) {
                $dispatches[] = self::record('whatsapp', $vendorPhone, 'Vendor Counter', $vendorMsg);
            }
            if (self::isChannelActive('in_app', $eventType)) {
                self::createInApp($vendorUserId, "Handover PIN Issued", "Pickup OTP for Order #{$order->order_number} is: {$order->pickup_verification_code}", $eventType, ['order_id' => $order->id, 'pickup_otp' => $order->pickup_verification_code]);
            }
        }

        // 2. Assigned Courier Push & In-App Alert (Critical Rider Notification)
        if ($order->rider && $order->rider->user) {
            $riderUserId = $order->rider->user_id;
            $riderPhone = $order->rider->user->phone ?? '+2348000000003';
            $shopName = $order->shop->name ?? 'Merchant Shop';
            $riderMsg = "Victorious Market: Order {$order->order_number} is packed and ready for pickup at {$shopName}. Present yourself to counter staff to collect package.";

            if (self::isChannelActive('sms', $eventType)) {
                $dispatches[] = self::record('sms', $riderPhone, 'Assigned Courier', $riderMsg);
            }
            if (self::isChannelActive('in_app', $eventType)) {
                self::createInApp($riderUserId, "Package Ready for Pickup!", "Order #{$order->order_number} is packed and waiting at {$shopName}.", $eventType, ['order_id' => $order->id]);
            }
            if (self::isChannelActive('fcm', $eventType)) {
                self::dispatchPush($riderUserId, "Package Ready for Pickup!", "Order #{$order->order_number} is ready at {$shopName}. Tap to view pickup manifest.", [
                    'type' => 'pickup_ready',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'shop_name' => $shopName,
                ], $eventType);
            }
        }

        return $dispatches;
    }

    /**
     * Event 3: Rider Picked Up (Out For Delivery)
     */
    public static function dispatchRiderPickedUp(Order $order): array
    {
        $dispatches = [];
        $eventType = 'out_for_delivery';

        $riderName = $order->rider->user->name ?? 'Dispatch Courier';
        $shopName = $order->shop->name ?? 'Merchant';

        // 1. Customer Notification: Package in transit
        if ($order->customer) {
            $customerPhone = $order->customer->phone ?? '+2348000000000';
            $customerMsg = "Victorious Market: Your package for Order {$order->order_number} has been picked up from {$shopName} by courier {$riderName} and is now en route to you! Track: " . route('order.status', $order->order_number);

            if (self::isChannelActive('sms', $eventType)) {
                $dispatches[] = self::record('sms', $customerPhone, 'Customer', $customerMsg);
            }
            if (self::isChannelActive('in_app', $eventType)) {
                self::createInApp($order->customer->id, "Package Out For Delivery!", "Courier {$riderName} has collected your parcel and is en route.", $eventType, ['order_id' => $order->id], route('order.status', $order->order_number));
            }
            if (self::isChannelActive('fcm', $eventType)) {
                self::dispatchPush($order->customer->id, "Out For Delivery!", "Courier {$riderName} is on the way with Order #{$order->order_number}.", [
                    'type' => 'out_for_delivery',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ], $eventType);
            }
        }

        // 2. Vendor Notification: Handover confirmed
        if ($order->vendor && $order->vendor->user) {
            $vendorUserId = $order->vendor->user_id;
            $vendorPhone = $order->vendor->business_phone ?? '+2348000000001';
            $vendorMsg = "Victorious Market: Order {$order->order_number} custody transferred to courier {$riderName}. Status is now Out for Delivery.";

            if (self::isChannelActive('whatsapp', $eventType)) {
                $dispatches[] = self::record('whatsapp', $vendorPhone, 'Vendor', $vendorMsg);
            }
            if (self::isChannelActive('in_app', $eventType)) {
                self::createInApp($vendorUserId, "Handover Confirmed", "Custody of Order #{$order->order_number} transferred to courier {$riderName}.", $eventType, ['order_id' => $order->id]);
            }
        }

        return $dispatches;
    }

    /**
     * Event 4: Rider Arrived at Doorstep
     */
    public static function dispatchRiderArrived(Order $order): array
    {
        $dispatches = [];
        $eventType = 'doorstep_arrived';

        if ($order->customer) {
            $customerPhone = $order->customer->phone ?? '+2348000000000';
            $msg = "🚨 Victorious Market Courier has arrived at your doorstep! Your secret Delivery Verification PIN is [{$order->verification_code}]. Verify package seal before releasing PIN.";

            if (self::isChannelActive('sms', $eventType)) {
                $dispatches[] = self::record('sms', $customerPhone, 'Customer', $msg);
            }
            if (self::isChannelActive('whatsapp', $eventType)) {
                $dispatches[] = self::record('whatsapp', $customerPhone, 'Customer', $msg);
            }
            if (self::isChannelActive('in_app', $eventType)) {
                self::createInApp($order->customer->id, "Courier at Your Doorstep!", "Your courier has arrived. Your Security PIN is: {$order->verification_code}", $eventType, ['order_id' => $order->id, 'delivery_otp' => $order->verification_code]);
            }
            if (self::isChannelActive('fcm', $eventType)) {
                self::dispatchPush($order->customer->id, "Courier at Your Doorstep!", "Your courier has arrived with Order #{$order->order_number}. PIN: {$order->verification_code}", [
                    'type' => 'doorstep_arrived',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'otp' => $order->verification_code,
                ], $eventType);
            }
        }

        return $dispatches;
    }

    /**
     * Event 5: Order Delivered & Handshake Sealed
     */
    public static function dispatchOrderDelivered(Order $order): array
    {
        $dispatches = [];
        $eventType = 'delivery_completed';

        // 1. Logistics Fleet Notification
        if ($order->rider && $order->rider->logisticsCompany && $order->rider->logisticsCompany->user) {
            $logisticsUserId = $order->rider->logisticsCompany->user_id;
            $logisticsNet = $order->logistics_fee_retained ?? bcmul((string)$order->delivery_fee, '0.90', 2);
            $fleetMsg = "Victorious Market B2B Settlement: Order {$order->order_number} delivered. ₦" . number_format((float)$logisticsNet, 2) . " credited to your Corporate Wallet.";

            if (self::isChannelActive('in_app', $eventType)) {
                self::createInApp($logisticsUserId, "Delivery Completed", $fleetMsg, $eventType, ['order_id' => $order->id]);
            }
            if (self::isChannelActive('fcm', $eventType)) {
                self::dispatchPush($logisticsUserId, "Delivery Completed!", "Order #{$order->order_number} settled. +₦" . number_format((float)$logisticsNet, 2), ['type' => 'delivery_completed', 'order_id' => $order->id], $eventType);
            }
        }

        // 2. Customer Cashback Reward Notification
        if ($order->customer) {
            $customerPhone = $order->customer->phone ?? '+2348000000000';
            $cashbackMsg = "Victorious Market: Order {$order->order_number} delivery confirmed! 5% Cashback (₦" . number_format((float)$order->customer_cashback_5, 2) . ") has been added to your rewards wallet.";

            if (self::isChannelActive('sms', $eventType)) {
                $dispatches[] = self::record('sms', $customerPhone, 'Customer', $cashbackMsg);
            }
            if (self::isChannelActive('in_app', $eventType)) {
                self::createInApp($order->customer->id, "Cashback Credited! 🎉", "You earned ₦" . number_format((float)$order->customer_cashback_5, 2) . " cashback on Order #{$order->order_number}.", $eventType, ['order_id' => $order->id]);
            }
            if (self::isChannelActive('fcm', $eventType)) {
                self::dispatchPush($order->customer->id, "Delivery Confirmed + Cashback! 🎉", "You earned ₦" . number_format((float)$order->customer_cashback_5, 2) . " cashback on Order #{$order->order_number}.", [
                    'type' => 'delivery_completed',
                    'order_id' => $order->id,
                    'cashback' => (string)$order->customer_cashback_5,
                ], $eventType);
            }
        }

        // 3. Vendor Escrow Settlement Notification
        if ($order->vendor && $order->vendor->user) {
            $vendorUserId = $order->vendor->user_id;
            $vendorPhone = $order->vendor->business_phone ?? '+2348000000001';
            $vendorMsg = "Victorious Market: Order {$order->order_number} delivered to customer. 90% payout of ₦" . number_format((float)$order->vendor_net_90, 2) . " entered 24h sovereign buffer.";

            if (self::isChannelActive('whatsapp', $eventType)) {
                $dispatches[] = self::record('whatsapp', $vendorPhone, 'Vendor', $vendorMsg);
            }
            if (self::isChannelActive('in_app', $eventType)) {
                self::createInApp($vendorUserId, "Order Delivered Successfully", "Order #{$order->order_number} delivered. ₦" . number_format((float)$order->vendor_net_90, 2) . " is in inspection escrow.", $eventType, ['order_id' => $order->id]);
            }
        }

        return $dispatches;
    }

    /**
     * Event 6: Payout Disbursed (Vendor & Fleet)
     */
    public static function dispatchPayoutDisbursed(LogisticsPayoutRequest|VendorPayoutRequest $payout): array
    {
        $eventType = 'payout_disbursed';
        $dispatches = [];

        if ($payout instanceof LogisticsPayoutRequest) {
            $companyPhone = $payout->company->contact_phone ?? '+2348000000002';
            $msg = "Victorious Market Treasury: Corporate payout of ₦" . number_format((float)$payout->amount, 2) . " successfully disbursed to {$payout->bank_name} ({$payout->account_number}).";
            if (self::isChannelActive('whatsapp', $eventType)) {
                $dispatches[] = self::record('whatsapp', $companyPhone, 'Logistics HQ', $msg);
            }
            if ($payout->company && $payout->company->user) {
                $userId = $payout->company->user_id;
                if (self::isChannelActive('in_app', $eventType)) {
                    self::createInApp($userId, "Payout Disbursed", $msg, $eventType, ['payout_id' => $payout->id]);
                }
                if (self::isChannelActive('fcm', $eventType)) {
                    self::dispatchPush($userId, "Payout Disbursed! ₦" . number_format((float)$payout->amount, 2), "Bank transfer to {$payout->bank_name} confirmed.", ['type' => 'payout_disbursed', 'payout_id' => $payout->id], $eventType);
                }
            }
        } else {
            $vendorPhone = $payout->vendor->business_phone ?? '+2348000000001';
            $msg = "Victorious Market Treasury: Merchant payout of ₦" . number_format((float)$payout->amount, 2) . " successfully disbursed to {$payout->bank_name} ({$payout->account_number}).";
            if (self::isChannelActive('whatsapp', $eventType)) {
                $dispatches[] = self::record('whatsapp', $vendorPhone, 'Vendor', $msg);
            }
            if ($payout->vendor && $payout->vendor->user) {
                $userId = $payout->vendor->user_id;
                if (self::isChannelActive('in_app', $eventType)) {
                    self::createInApp($userId, "Earnings Disbursed", $msg, $eventType, ['payout_id' => $payout->id]);
                }
                if (self::isChannelActive('fcm', $eventType)) {
                    self::dispatchPush($userId, "Earnings Disbursed! ₦" . number_format((float)$payout->amount, 2), "Bank transfer to {$payout->bank_name} confirmed.", ['type' => 'payout_disbursed', 'payout_id' => $payout->id], $eventType);
                }
            }
        }

        return $dispatches;
    }

    /**
     * Record and execute SMS/WhatsApp gateway dispatch
     */
    protected static function record(string $channel, string $recipient, string $targetRole, string $message): array
    {
        $logPayload = [
            'channel' => $channel,
            'recipient' => $recipient,
            'target_role' => $targetRole,
            'message' => $message,
            'dispatched_at' => Carbon::now()->toIso8601String(),
        ];

        // Production Async Dispatch via Termii Job
        if (config('services.termii.key') || env('TERMII_API_KEY')) {
            try {
                SendTermiiNotificationJob::dispatch($recipient, $channel, $message);
                $logPayload['status'] = 'queued_live';
                NotificationDeliveryLog::log($channel, $recipient, 'sms_broadcast', null, 'delivered', ['mode' => 'termii_job']);
            } catch (\Throwable $e) {
                Log::error("Failed to queue async Termii notification: " . $e->getMessage());
                $logPayload['status'] = 'queue_failed';
                NotificationDeliveryLog::log($channel, $recipient, 'sms_broadcast', null, 'failed', null, $e->getMessage());
            }
        } else {
            $logPayload['status'] = 'simulated';
            NotificationDeliveryLog::log($channel, $recipient, 'sms_broadcast', null, 'delivered', ['mode' => 'simulation']);
        }

        $cached = Cache::get(self::CACHE_KEY, []);
        array_unshift($cached, $logPayload);
        Cache::put(self::CACHE_KEY, array_slice($cached, 0, 50), 3600);

        Log::info("Notification [{$channel}] to {$targetRole} ({$recipient}): {$message}");
        return $logPayload;
    }

    public static function getRecentSimulations(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }
}
