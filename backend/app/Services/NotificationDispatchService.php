<?php

namespace App\Services;

use App\Jobs\SendFcmNotificationJob;
use App\Jobs\SendTermiiNotificationJob;
use App\Models\LogisticsPayoutRequest;
use App\Models\NotificationDeliveryLog;
use App\Models\Order;
use App\Models\ReturnCase;
use App\Models\User;
use App\Models\SystemNotification;
use App\Models\SystemSetting;
use App\Models\VendorEmployeeProfile;
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

        // 2. Vendor HQ & Branch Counter Staff Alert
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

            // Security Hardening: Alert active counter staff assigned STRICTLY to this branch shop
            if (!empty($order->shop_id)) {
                $counterUserIds = VendorEmployeeProfile::where('shop_id', $order->shop_id)
                    ->where('is_active', true)
                    ->pluck('user_id');

                foreach ($counterUserIds as $cUserId) {
                    if (self::isChannelActive('in_app', $eventType)) {
                        self::createInApp($cUserId, "New Branch Order Received!", "Order #{$order->order_number} assigned to your branch counter. Prepare for packing.", $eventType, ['order_id' => $order->id]);
                    }
                    if (self::isChannelActive('fcm', $eventType)) {
                        self::dispatchPush($cUserId, "New Branch Order! #{$order->order_number}", "Prepare parcel for counter inspection.", ['type' => 'order_placed', 'order_id' => $order->id], $eventType);
                    }
                }
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

            // Security Hardening: Supply Handover OTP to active branch counter staff strictly for this shop
            if (!empty($order->shop_id)) {
                $counterUserIds = VendorEmployeeProfile::where('shop_id', $order->shop_id)
                    ->where('is_active', true)
                    ->pluck('user_id');

                foreach ($counterUserIds as $cUserId) {
                    if (self::isChannelActive('in_app', $eventType)) {
                        self::createInApp($cUserId, "Handover PIN Issued", "Pickup OTP for Order #{$order->order_number} is: {$order->pickup_verification_code}", $eventType, ['order_id' => $order->id, 'pickup_otp' => $order->pickup_verification_code]);
                    }
                    if (self::isChannelActive('fcm', $eventType)) {
                        self::dispatchPush($cUserId, "Handover PIN: {$order->pickup_verification_code}", "Order #{$order->order_number}. Verify courier identity before releasing package.", ['type' => 'pickup_otp', 'order_id' => $order->id, 'pickup_otp' => $order->pickup_verification_code], $eventType);
                    }
                }
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
     * Render customizable message template with dynamic business placeholders
     */
    public static function renderTemplate(string $eventKey, string $targetRole, string $field, array $replacements, string $default): string
    {
        $templates = SystemSetting::get('notification_templates', []);
        $templateString = $templates[$eventKey][$targetRole][$field] ?? $default;

        foreach ($replacements as $k => $v) {
            $templateString = str_replace('{' . $k . '}', (string)$v, $templateString);
        }

        return $templateString;
    }

    /**
     * Event 7: Doorstep Return Initiated (Customer refused item at doorstep)
     */
    public static function dispatchDoorstepReturnInitiated(ReturnCase $returnCase): array
    {
        $dispatches = [];
        $eventType = 'doorstep_return_dispute';
        $order = $returnCase->order;
        $orderNumber = $order->order_number ?? $returnCase->case_number;

        // Alert Vendor & Branch Counter Staff
        if ($returnCase->vendor && $returnCase->vendor->user) {
            $vendorUserId = $returnCase->vendor->user_id;
            $vendorPhone = $returnCase->vendor->business_phone ?? '+2348000000001';

            $title = self::renderTemplate($eventType, 'vendor', 'title', ['order_number' => $orderNumber], "Doorstep Return Initiated");
            $body = self::renderTemplate($eventType, 'vendor', 'body', [
                'order_number' => $orderNumber,
                'category' => $returnCase->rejection_category,
                'amount' => number_format((float)$returnCase->total_refundable_amount, 2),
            ], "Order #{$orderNumber} items were rejected at doorstep ({$returnCase->rejection_category}). Package is returning via courier.");

            if (self::isChannelActive('in_app', $eventType)) {
                self::createInApp($vendorUserId, $title, $body, $eventType, ['return_case_id' => $returnCase->id]);
            }
            if (self::isChannelActive('fcm', $eventType)) {
                self::dispatchPush($vendorUserId, $title, $body, ['type' => 'doorstep_return', 'return_case_id' => $returnCase->id], $eventType);
            }
            if (self::isChannelActive('whatsapp', $eventType)) {
                $dispatches[] = self::record('whatsapp', $vendorPhone, 'Vendor', "Victorious Market: " . $body);
            }

            // Security Hardening: Alert active counter staff strictly for this branch shop
            if (!empty($order->shop_id)) {
                $counterUserIds = VendorEmployeeProfile::where('shop_id', $order->shop_id)
                    ->where('is_active', true)
                    ->pluck('user_id');

                foreach ($counterUserIds as $cUserId) {
                    if (self::isChannelActive('in_app', $eventType)) {
                        self::createInApp($cUserId, $title, $body, $eventType, ['return_case_id' => $returnCase->id]);
                    }
                    if (self::isChannelActive('fcm', $eventType)) {
                        self::dispatchPush($cUserId, $title, $body, ['type' => 'doorstep_return', 'return_case_id' => $returnCase->id], $eventType);
                    }
                }
            }
        }

        // Alert Super Admin & Operations/Moderation Staff
        $adminUsers = User::whereIn('user_type', ['super_admin', 'admin_employee'])
            ->where('status', 'active')
            ->get();
        foreach ($adminUsers as $admin) {
            self::createInApp($admin->id, "Doorstep Return Alert", "Order #{$orderNumber} experienced doorstep refusal ({$returnCase->rejection_category}).", $eventType, ['return_case_id' => $returnCase->id]);
        }

        return $dispatches;
    }

    /**
     * Event 8: Vendor Return Handshake OTP Issued (Courier arriving back at shop)
     */
    public static function dispatchVendorReturnOtpIssued(ReturnCase $returnCase): array
    {
        $dispatches = [];
        $eventType = 'vendor_return_otp';
        $order = $returnCase->order;
        $orderNumber = $order->order_number ?? $returnCase->case_number;
        $vendor = $returnCase->vendor;
        $shopId = $order->shop_id ?? null;

        $notifyAdmins = static function (string $reason) use ($returnCase, $orderNumber, $eventType): void {
            Log::critical('Vendor return OTP could not be securely routed.', [
                'return_case_id' => $returnCase->id,
                'order_id' => $returnCase->order_id,
                'reason' => $reason,
            ]);
            $admins = User::where('status', 'active')
                ->whereIn('user_type', ['super_admin', 'admin_employee'])
                ->get();
            foreach ($admins as $admin) {
                self::createInApp(
                    $admin->id,
                    'Return Destination Requires Review',
                    "Return case #{$returnCase->case_number} for Order #{$orderNumber} cannot receive a supplier return OTP until its destination is resolved: {$reason}",
                    $eventType,
                    ['return_case_id' => $returnCase->id, 'order_id' => $returnCase->order_id]
                );
            }
        };

        // Retailer-model orders have no public vendor_id on ReturnCase. Resolve the
        // private supplier from the rejected order items, and only issue one OTP if
        // exactly one active supplier/shop owns the return. Multi-supplier cases
        // stay held for explicit assignment; one OTP must never cover several shops.
        if (!$vendor && $order && $order->retailer_model_order) {
            $supplierIds = \App\Models\ReturnItem::where('return_case_id', $returnCase->id)
                ->with('orderItem')
                ->get()
                ->map(fn ($item) => (int) ($item->orderItem?->fulfillment_supplier_vendor_id ?? 0))
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            if ($supplierIds->isEmpty()) {
                $supplierIds = \App\Models\OrderItem::where('order_id', $order->id)
                    ->pluck('fulfillment_supplier_vendor_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();
            }

            if ($supplierIds->count() !== 1) {
                $notifyAdmins('Rejected items belong to zero or multiple suppliers.');
                return [];
            }

            $vendor = \App\Models\VendorProfile::with('user')->find($supplierIds->first());
            $shop = $vendor?->shops()->where('is_active', true)->first();
            if (!$vendor || $vendor->marketplace_status !== 'active'
                || !$vendor->user || $vendor->user->status !== 'active' || !$shop) {
                $notifyAdmins('The single assigned supplier, its user, or its active shop is unavailable.');
                return [];
            }
            $shopId = $shop->id;
        }

        if (!$vendor || !$vendor->user || $vendor->user->status !== 'active') {
            $notifyAdmins('No active supplier account is available for this return case.');
            return [];
        }

        $otp = trim((string) $returnCase->vendor_return_otp);
        if (!preg_match('/^\\d{6}$/D', $otp)) {
            $notifyAdmins('The return case has no valid six-digit OTP.');
            return [];
        }

        $vendorUserId = (int) $vendor->user_id;
        $vendorPhone = trim((string) ($vendor->business_phone ?: $vendor->user->phone));
        $inAppEnabled = self::isChannelActive('in_app', $eventType);
        $pushEnabled = self::isChannelActive('fcm', $eventType);
        $smsEnabled = $vendorPhone !== '' && self::isChannelActive('sms', $eventType);
        if (!$inAppEnabled && !$pushEnabled && !$smsEnabled) {
            $notifyAdmins('No active notification channel can deliver the supplier return OTP.');
            return [];
        }

        // This security-sensitive message intentionally bypasses editable templates:
        // a stale template could falsely say the courier has arrived and encourage
        // premature disclosure of the custody OTP.
        $title = 'Return OTP Issued — Keep Confidential';
        $body = "A return parcel for Order #{$orderNumber} is expected at your shop. Keep this OTP private and share it only with the assigned rider after the parcel physically arrives and the items match. Return OTP: {$otp}.";

        if ($inAppEnabled) {
            self::createInApp($vendorUserId, $title, $body, $eventType, ['return_case_id' => $returnCase->id, 'return_otp' => $otp]);
        }
        if ($pushEnabled) {
            self::dispatchPush($vendorUserId, $title, $body, ['type' => 'return_otp', 'otp' => $otp, 'return_case_id' => $returnCase->id], $eventType);
        }
        if ($smsEnabled) {
            $dispatches[] = self::record('sms', $vendorPhone, 'Supplier Counter', 'Victorious Market: ' . $body);
        } elseif ($vendorPhone === '') {
            Log::warning('Supplier return OTP has no verified phone destination; only enabled in-app/push channels can deliver it.', [
                'return_case_id' => $returnCase->id,
                'supplier_vendor_id' => $vendor->id,
            ]);
        }

        // Send the OTP only to active counter staff at the resolved shop. Never
        // fall back to a placeholder phone number, and never return this OTP to the rider.
        if ($shopId) {
            $counterUserIds = VendorEmployeeProfile::where('shop_id', $shopId)
                ->where('is_active', true)
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->pluck('user_id');

            foreach ($counterUserIds as $counterUserId) {
                if (self::isChannelActive('in_app', $eventType)) {
                    self::createInApp($counterUserId, $title, $body, $eventType, ['return_case_id' => $returnCase->id, 'return_otp' => $otp]);
                }
                if (self::isChannelActive('fcm', $eventType)) {
                    self::dispatchPush($counterUserId, $title, $body, ['type' => 'return_otp', 'otp' => $otp, 'return_case_id' => $returnCase->id], $eventType);
                }
            }
        }

        return $dispatches;
    }

    /**
     * Event 9: Vendor Accepted Return (Return Handshake Sealed)
     */
    public static function dispatchReturnAccepted(ReturnCase $returnCase): array
    {
        $dispatches = [];
        $eventType = 'return_accepted';
        $order = $returnCase->order;
        $orderNumber = $order->order_number ?? $returnCase->case_number;

        if ($returnCase->customer) {
            $customerId = $returnCase->customer_id;
            $customerPhone = $returnCase->customer->phone ?? '+2348000000000';
            $amountFormatted = number_format((float)$returnCase->total_refundable_amount, 2);

            $title = self::renderTemplate($eventType, 'customer', 'title', ['order_number' => $orderNumber], "Return Accepted & Refund Processed");
            $body = self::renderTemplate($eventType, 'customer', 'body', [
                'order_number' => $orderNumber,
                'amount' => $amountFormatted,
            ], "Merchant accepted returned items for Order #{$orderNumber}. Your refund request for ₦{$amountFormatted} is under review. If approved, the refund will be sent through the original Paystack payment channel; no wallet credit has been issued.");

            if (self::isChannelActive('in_app', $eventType)) {
                self::createInApp($customerId, $title, $body, $eventType, ['return_case_id' => $returnCase->id]);
            }
            if (self::isChannelActive('fcm', $eventType)) {
                self::dispatchPush($customerId, $title, $body, ['type' => 'return_accepted', 'return_case_id' => $returnCase->id], $eventType);
            }
            if (self::isChannelActive('sms', $eventType)) {
                $dispatches[] = self::record('sms', $customerPhone, 'Customer', "Victorious Market: " . $body);
            }
        }

        return $dispatches;
    }

    /**
     * Event 10: Vendor Counter Dispute Escalated
     */
    public static function dispatchVendorCounterDispute(ReturnCase $returnCase, string $reason): array
    {
        $dispatches = [];
        $eventType = 'dispute_escalated';

        // Alert Customer
        if ($returnCase->customer) {
            $title = "Return Escalated for Review";
            $body = "Merchant submitted a dispute on Return #{$returnCase->case_number} ({$reason}). Victorious Sovereign Arbitration will review within 24h.";

            self::createInApp($returnCase->customer_id, $title, $body, $eventType, ['return_case_id' => $returnCase->id]);
            self::dispatchPush($returnCase->customer_id, $title, $body, ['type' => 'dispute_escalated', 'return_case_id' => $returnCase->id], $eventType);
        }

        // Alert Super Admin & Operations/Moderation Staff
        $adminUsers = User::whereIn('user_type', ['super_admin', 'admin_employee'])
            ->where('status', 'active')
            ->get();
        foreach ($adminUsers as $admin) {
            self::createInApp($admin->id, "Urgent: Dispute Filed", "Return #{$returnCase->case_number} was disputed by vendor: {$reason}", $eventType, ['return_case_id' => $returnCase->id]);
        }

        return $dispatches;
    }

    /**
     * Event 11: Super Admin Arbitration Ruling
     */
    public static function dispatchDisputeArbitrationRuling(ReturnCase $returnCase, string $ruling, ?string $notes = null): array
    {
        $dispatches = [];
        $eventType = 'dispute_arbitration';
        $rulingText = strtoupper(str_replace('_', ' ', $ruling));

        // Alert Customer
        if ($returnCase->customer) {
            $title = "Arbitration Ruling: {$rulingText}";
            $body = "Victorious Market arbitration panel has resolved Case #{$returnCase->case_number} as: {$rulingText}. " . ($notes ? "Notes: {$notes}" : '');

            self::createInApp($returnCase->customer_id, $title, $body, $eventType, ['return_case_id' => $returnCase->id]);
            self::dispatchPush($returnCase->customer_id, $title, $body, ['type' => 'arbitration_ruling', 'return_case_id' => $returnCase->id], $eventType);
        }

        // Alert Vendor
        if ($returnCase->vendor && $returnCase->vendor->user) {
            $title = "Arbitration Ruling: {$rulingText}";
            $body = "Super Admin has ruled on Return Case #{$returnCase->case_number}: {$rulingText}. " . ($notes ? "Notes: {$notes}" : '');

            self::createInApp($returnCase->vendor->user_id, $title, $body, $eventType, ['return_case_id' => $returnCase->id]);
            self::dispatchPush($returnCase->vendor->user_id, $title, $body, ['type' => 'arbitration_ruling', 'return_case_id' => $returnCase->id], $eventType);
        }

        return $dispatches;
    }

    /**
     * Event 12: Payout Withdrawal Requested
     */
    public static function dispatchPayoutRequested(LogisticsPayoutRequest|VendorPayoutRequest $payout): array
    {
        $dispatches = [];
        $eventType = 'payout_requested';
        $amountFormatted = number_format((float)$payout->amount, 2);

        // Alert Requester
        if ($payout instanceof LogisticsPayoutRequest && $payout->company && $payout->company->user) {
            $userId = $payout->company->user_id;
            $title = "Payout Request Submitted";
            $body = "Your corporate payout withdrawal for ₦{$amountFormatted} has been logged and is awaiting Super Admin approval.";

            self::createInApp($userId, $title, $body, $eventType, ['payout_id' => $payout->id]);
            self::dispatchPush($userId, $title, $body, ['type' => 'payout_requested', 'payout_id' => $payout->id], $eventType);
        } elseif ($payout instanceof VendorPayoutRequest && $payout->vendor && $payout->vendor->user) {
            $userId = $payout->vendor->user_id;
            $title = "Payout Request Submitted";
            $body = "Your merchant earnings payout for ₦{$amountFormatted} has been submitted to treasury for review.";

            self::createInApp($userId, $title, $body, $eventType, ['payout_id' => $payout->id]);
            self::dispatchPush($userId, $title, $body, ['type' => 'payout_requested', 'payout_id' => $payout->id], $eventType);
        }

        // Alert Super Admin Tower
        $adminUsers = User::where('user_type', 'super_admin')->get();
        foreach ($adminUsers as $admin) {
            self::createInApp($admin->id, "New Payout Request: ₦{$amountFormatted}", "A new payout withdrawal request #{$payout->id} is pending review.", $eventType, ['payout_id' => $payout->id]);
        }

        return $dispatches;
    }

    /**
     * Event 13: Payout Request Rejected
     */
    public static function dispatchPayoutRejected(LogisticsPayoutRequest|VendorPayoutRequest $payout, string $reason): array
    {
        $dispatches = [];
        $eventType = 'payout_rejected';
        $amountFormatted = number_format((float)$payout->amount, 2);

        if ($payout instanceof LogisticsPayoutRequest && $payout->company && $payout->company->user) {
            $userId = $payout->company->user_id;
            $title = "Payout Request Rejected";
            $body = "Your payout request #{$payout->id} (₦{$amountFormatted}) was rejected. Reason: {$reason}. Funds have been restored to your wallet.";

            self::createInApp($userId, $title, $body, $eventType, ['payout_id' => $payout->id]);
            self::dispatchPush($userId, $title, $body, ['type' => 'payout_rejected', 'payout_id' => $payout->id], $eventType);
        } elseif ($payout instanceof VendorPayoutRequest && $payout->vendor && $payout->vendor->user) {
            $userId = $payout->vendor->user_id;
            $title = "Payout Request Rejected";
            $body = "Your payout request #{$payout->id} (₦{$amountFormatted}) was rejected. Reason: {$reason}. Orders restored to eligible status.";

            self::createInApp($userId, $title, $body, $eventType, ['payout_id' => $payout->id]);
            self::dispatchPush($userId, $title, $body, ['type' => 'payout_rejected', 'payout_id' => $payout->id], $eventType);
        }

        return $dispatches;
    }

    /**
     * Event 14: Customer Wallet Credited (Instant refunds & cashback)
     */
    public static function dispatchCustomerWalletCredited(User $customer, string $amount, string $reason): array
    {
        $dispatches = [];
        $eventType = 'wallet_credit';
        $amountFormatted = number_format((float)$amount, 2);

        $title = "Victorious Wallet Credited! +₦{$amountFormatted}";
        $body = "₦{$amountFormatted} has been credited to your sovereign customer wallet. ({$reason})";

        if (self::isChannelActive('in_app', $eventType)) {
            self::createInApp($customer->id, $title, $body, $eventType);
        }
        if (self::isChannelActive('fcm', $eventType)) {
            self::dispatchPush($customer->id, $title, $body, ['type' => 'wallet_credit', 'amount' => $amount], $eventType);
        }
        if (self::isChannelActive('sms', $eventType) && !empty($customer->phone)) {
            $dispatches[] = self::record('sms', $customer->phone, 'Customer', "Victorious Market: " . $body);
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
        if (SystemSetting::get('termii_api_key') || config('services.termii.key') || env('TERMII_API_KEY')) {
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
