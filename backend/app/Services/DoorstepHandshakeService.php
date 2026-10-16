<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\CustomerCashbackLedger;
use App\Models\CustomerWalletCredit;
use App\Models\FinancialJournalEntry;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnCase;
use App\Models\ReturnItem;
use App\Models\RiderProfile;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorRecoveryLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DoorstepHandshakeService
{
    /**
     * Authoritative Doorstep Partial Acceptance & 3-Option Rejection Engine.
     * Enforces item-level verification, manifest-bound OTP security,
     * instant customer wallet credit, and balanced double-entry accounting.
     */
    public function processDoorstepHandover(
        Order $order,
        RiderProfile $rider,
        array $manifestItems,
        string $deliveryOtp,
        ?string $doorstepPhotoPath = null
    ): array {
        return DB::transaction(function () use ($order, $rider, $manifestItems, $deliveryOtp, $doorstepPhotoPath) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder) {
                return ['success' => false, 'message' => 'Order not found under lock.'];
            }

            // 1. Verify Rider Assignment & Active Courier Profile
            $haltReason = null;
            if (!$rider->is_active || ($rider->user && $rider->user->status !== 'active')) {
                $haltReason = 'Unauthorized: Dispatch courier profile is suspended or inactive. Delivery halted.';
            } elseif (!$rider->logistics_company_id || ($rider->logisticsCompany && $rider->logisticsCompany->status !== 'active')) {
                $haltReason = 'Unauthorized: Parent logistics fleet account is inactive or suspended. Delivery halted.';
            } elseif ($lockedOrder->rider_id && (int)$lockedOrder->rider_id !== (int)$rider->id) {
                $haltReason = 'Unauthorized: This order is assigned to another delivery courier.';
            }

            // 1B. Verify Logistics Company Direct & Merchant Vendor & Shop Active Status
            if (!$haltReason && $lockedOrder->logisticsCompany && $lockedOrder->logisticsCompany->status !== 'active') {
                $haltReason = 'Assigned logistics fleet company is suspended or inactive. Delivery halted.';
            }

            if (!$haltReason && $lockedOrder->vendor) {
                if ($lockedOrder->vendor->marketplace_status !== 'active' || ($lockedOrder->vendor->user && $lockedOrder->vendor->user->status !== 'active')) {
                    $haltReason = 'Merchant marketplace account is suspended. Delivery settlement halted.';
                }
            }

            if (!$haltReason && $lockedOrder->shop && !$lockedOrder->shop->is_active) {
                $haltReason = 'Merchant shop branch is inactive or closed. Delivery settlement halted.';
            }

            if ($haltReason) {
                $lockedOrder->delivery_halted_reason = $haltReason;
                $lockedOrder->delivery_halted_at = Carbon::now();
                $lockedOrder->save();

                AdminAuditLog::create([
                    'user_id' => $rider->user_id ?? ($lockedOrder->customer_id ?? null),
                    'action' => 'doorstep_handshake_halted_entity_inactive',
                    'target_type' => Order::class,
                    'target_id' => $lockedOrder->id,
                    'old_values' => ['order_status' => $lockedOrder->order_status],
                    'new_values' => [
                        'order_number' => $lockedOrder->order_number,
                        'delivery_halted_reason' => $haltReason,
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent() ?? 'Doorstep Handshake Service',
                ]);

                return ['success' => false, 'message' => $haltReason];
            }

            // 2. Anti-Brute-Force & Lockout Check
            if ($lockedOrder->delivery_locked_until && Carbon::now()->lt($lockedOrder->delivery_locked_until)) {
                $waitSec = Carbon::now()->diffInSeconds($lockedOrder->delivery_locked_until);
                return [
                    'success' => false,
                    'message' => "Delivery verification locked due to excessive failed attempts. Please retry in {$waitSec} seconds.",
                ];
            }

            // 3. Verify Manifest Delivery OTP
            $expectedOtp = trim((string)$lockedOrder->verification_code);
            $providedOtp = trim((string)$deliveryOtp);

            if (empty($expectedOtp) || $providedOtp !== $expectedOtp) {
                $lockedOrder->delivery_failed_attempts = (int)$lockedOrder->delivery_failed_attempts + 1;
                if ($lockedOrder->delivery_failed_attempts >= 5) {
                    $lockedOrder->delivery_locked_until = Carbon::now()->addMinutes(15);
                }
                $lockedOrder->save();

                return [
                    'success' => false,
                    'message' => 'Invalid delivery verification OTP. Verification failed.',
                ];
            }

            // 4. Validate Manifest Quantities & Line Item Integrity
            $totalAcceptedMerchandise = '0.00';
            $totalRejectedMerchandise = '0.00';
            $hasRejectedItems = false;
            $predominantRejectionCategory = null;
            $validatedLines = [];

            foreach ($manifestItems as $itemInput) {
                $orderItemId = (int) ($itemInput['order_item_id'] ?? 0);
                $acceptedQty = max(0, (int) ($itemInput['accepted_quantity'] ?? 0));
                $rejectedQty = max(0, (int) ($itemInput['rejected_quantity'] ?? 0));
                $category = $itemInput['rejection_category'] ?? null;
                $notes = $itemInput['rejection_notes'] ?? null;

                $orderItem = OrderItem::where('id', $orderItemId)
                    ->where('order_id', $lockedOrder->id)
                    ->first();

                if (!$orderItem) {
                    return ['success' => false, 'message' => "Order item #{$orderItemId} does not belong to this order."];
                }

                if (($acceptedQty + $rejectedQty) !== (int)$orderItem->quantity) {
                    return [
                        'success' => false,
                        'message' => "Quantity mismatch for '{$orderItem->product_name}'. Accepted ({$acceptedQty}) + Rejected ({$rejectedQty}) must equal ordered quantity ({$orderItem->quantity}).",
                    ];
                }

                if ($rejectedQty > 0) {
                    $hasRejectedItems = true;
                    if (!in_array($category, ['vendor_issue', 'transit_damage', 'customer_change_of_mind'])) {
                        return [
                            'success' => false,
                            'message' => "Invalid rejection category for '{$orderItem->product_name}'. Must be: vendor_issue, transit_damage, or customer_change_of_mind.",
                        ];
                    }
                    if (!$predominantRejectionCategory) {
                        $predominantRejectionCategory = $category;
                    }
                }

                $acceptedSubtotal = bcmul((string)$orderItem->unit_price, (string)$acceptedQty, 2);
                $rejectedSubtotal = bcmul((string)$orderItem->unit_price, (string)$rejectedQty, 2);

                $totalAcceptedMerchandise = bcadd($totalAcceptedMerchandise, $acceptedSubtotal, 2);
                $totalRejectedMerchandise = bcadd($totalRejectedMerchandise, $rejectedSubtotal, 2);

                $validatedLines[] = [
                    'order_item' => $orderItem,
                    'accepted_qty' => $acceptedQty,
                    'rejected_qty' => $rejectedQty,
                    'category' => $category,
                    'notes' => $notes,
                    'defect_photo_path' => $itemInput['defect_photo_path'] ?? null,
                    'accepted_subtotal' => $acceptedSubtotal,
                    'rejected_subtotal' => $rejectedSubtotal,
                ];
            }

            // 5. Update Order Items Status
            foreach ($validatedLines as $line) {
                $line['order_item']->accepted_quantity = $line['accepted_qty'];
                $line['order_item']->rejected_quantity = $line['rejected_qty'];
                $line['order_item']->rejection_category = $line['category'];
                $line['order_item']->rejection_notes = $line['notes'];
                if (!empty($line['defect_photo_path'])) {
                    $line['order_item']->defect_photo_path = $line['defect_photo_path'];
                }
                $line['order_item']->item_delivery_status = $line['rejected_qty'] > 0
                    ? ($line['accepted_qty'] > 0 ? 'partially_accepted' : 'rejected_in_transit')
                    : 'accepted';
                $line['order_item']->save();
            }

            $now = Carbon::now();
            $refundHours = max(1, (int) \App\Models\SystemSetting::get('refund_window_hours', 24));
            $deliveryFee = (string)$lockedOrder->delivery_fee;
            $fleetDeliveryCut = bcmul($deliveryFee, '0.9000', 2);
            $platDeliveryCut = bcmul($deliveryFee, '0.1000', 2);

            // 6. Handle Rejection Flow (Spawn Return Case + Instant Customer Wallet Credit)
            $returnCase = null;
            $customerRefundWalletAmount = '0.00';

            if ($hasRejectedItems) {
                // Generate 6-Digit Vendor Return Handshake OTP
                $vendorReturnOtp = (string) mt_rand(100000, 999999);

                $returnCase = ReturnCase::create([
                    'case_number' => ReturnCase::generateCaseNumber(),
                    'customer_id' => $lockedOrder->customer_id,
                    'order_id' => $lockedOrder->id,
                    'parent_order_id' => $lockedOrder->parent_order_id,
                    'vendor_id' => $lockedOrder->vendor_id,
                    'collection_point_id' => $lockedOrder->collection_point_id,
                    'source' => 'doorstep_refusal',
                    'rejection_category' => $predominantRejectionCategory,
                    'return_reason' => $predominantRejectionCategory === 'vendor_issue' ? 'defective_product' : ($predominantRejectionCategory === 'transit_damage' ? 'damaged_in_transit' : 'change_of_mind'),
                    'customer_description' => "Rejected at doorstep: {$predominantRejectionCategory}",
                    'vendor_return_otp' => $vendorReturnOtp,
                    'doorstep_photo_path' => $doorstepPhotoPath,
                    'returned_by_rider_id' => $rider->user_id,
                    'status' => 'in_transit_back_to_vendor',
                    'resolution_type' => 'refund',
                    'total_refundable_amount' => $totalRejectedMerchandise,
                    'refund_delivery_fee' => false,
                    'delivery_fee_refunded' => '0.00',
                    'refunded_amount' => '0.00',
                ]);

                // Attach Return Items
                foreach ($validatedLines as $line) {
                    if ($line['rejected_qty'] > 0) {
                        ReturnItem::create([
                            'return_case_id' => $returnCase->id,
                            'order_item_id' => $line['order_item']->id,
                            'product_id' => $line['order_item']->product_id,
                            'quantity' => $line['rejected_qty'],
                            'unit_price' => $line['order_item']->unit_price,
                            'line_total' => $line['rejected_subtotal'],
                            'allocated_discount' => '0.00',
                            'refundable_amount' => $line['rejected_subtotal'],
                            'item_condition' => $line['category'] === 'vendor_issue' ? 'damaged_defective' : 'unopened_sealed',
                            'status' => 'requested',
                        ]);
                    }
                }

                // Calculate Customer Instant Wallet Credit
                if ($predominantRejectionCategory === 'customer_change_of_mind') {
                    // Customer changed mind on correctly delivered goods: Customer pays 100% of delivery fee
                    $netCustomerCredit = BcMathService::comp($totalRejectedMerchandise, $deliveryFee) > 0
                        ? BcMathService::sub($totalRejectedMerchandise, $deliveryFee)
                        : '0.00';
                    $customerRefundWalletAmount = $netCustomerCredit;
                    $returnCase->cost_allocation = 'customer_100';
                } elseif ($predominantRejectionCategory === 'transit_damage') {
                    // Transit damage: Vendor 90% payout protected; Courier company absorbs liability
                    $customerRefundWalletAmount = $totalRejectedMerchandise;
                    $returnCase->cost_allocation = 'logistics_fleet_100';
                } else {
                    // Vendor issue: Vendor penalized, Customer gets full refund
                    $customerRefundWalletAmount = $totalRejectedMerchandise;
                    $returnCase->cost_allocation = 'vendor_100';
                }
                $returnCase->save();

                // Credit Customer 100% Spendable Refund Wallet Immediately
                CustomerWalletCredit::create([
                    'customer_id' => $lockedOrder->customer_id,
                    'order_id' => $lockedOrder->id,
                    'return_case_id' => $returnCase->id,
                    'amount' => $customerRefundWalletAmount,
                    'credit_type' => 'doorstep_refund_credit',
                    'status' => 'available',
                    'notes' => "Instant refund wallet credit for doorstep rejection on Order #{$lockedOrder->order_number}",
                ]);
            }

            // 7. Post Balanced Double-Entry Journal Entries
            $eventRef = 'EVT-HANDOVER-' . $lockedOrder->order_number . '-' . time();

            // A. Accepted Merchandise 90/5/5 Split (Vendor Net, Customer Cashback, Platform Commission)
            if (BcMathService::comp($totalAcceptedMerchandise, '0.00') > 0) {
                $vendorNet = bcmul($totalAcceptedMerchandise, '0.9000', 2);
                $customerCashback = bcmul($totalAcceptedMerchandise, '0.0500', 2);
                $platformMerch = bcmul($totalAcceptedMerchandise, '0.0500', 2);

                // Credit Vendor Net Escrow
                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => $lockedOrder->payment_reference ?? $lockedOrder->order_number,
                    'event_id' => $eventRef . '-VEND',
                    'beneficiary_type' => VendorProfile::class,
                    'beneficiary_id' => $lockedOrder->vendor_id,
                    'account_name' => 'vendor_payable',
                    'entry_type' => 'credit',
                    'amount' => $vendorNet,
                    'currency' => 'NGN',
                    'description' => "Merchant 90% net escrow for accepted items on Order #{$lockedOrder->order_number}",
                ]);

                // Credit Customer 5% Cashback Reward
                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => $lockedOrder->payment_reference ?? $lockedOrder->order_number,
                    'event_id' => $eventRef . '-CB',
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => $lockedOrder->customer_id,
                    'account_name' => 'cashback_liability',
                    'entry_type' => 'credit',
                    'amount' => $customerCashback,
                    'currency' => 'NGN',
                    'description' => "Customer 5% cashback reward for accepted items on Order #{$lockedOrder->order_number}",
                ]);

                // Credit Platform 5% Commission
                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => $lockedOrder->payment_reference ?? $lockedOrder->order_number,
                    'event_id' => $eventRef . '-PLAT-M',
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => null,
                    'account_name' => 'platform_revenue',
                    'entry_type' => 'credit',
                    'amount' => $platformMerch,
                    'currency' => 'NGN',
                    'description' => "VMarket 5% platform cut for accepted items on Order #{$lockedOrder->order_number}",
                ]);

                // Create Customer Cashback Lot for Accepted Merchandise
                CustomerCashbackLedger::create([
                    'customer_id' => $lockedOrder->customer_id,
                    'order_id' => $lockedOrder->id,
                    'amount' => $customerCashback,
                    'status' => 'pending_maturation',
                    'matures_at' => $now->copy()->addHours($refundHours),
                    'expires_at' => $now->copy()->addMonths(6),
                    'notes' => "5% reward for accepted items on Order #{$lockedOrder->order_number}",
                ]);
            }

            // B. Delivery Fee Payout (Always Credited to Logistics Partner)
            if (BcMathService::comp($deliveryFee, '0.00') > 0) {
                // Credit Logistics Partner 90%
                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => $lockedOrder->payment_reference ?? $lockedOrder->order_number,
                    'event_id' => $eventRef . '-FLEET',
                    'beneficiary_type' => LogisticsCompanyProfile::class,
                    'beneficiary_id' => $lockedOrder->logistics_company_id,
                    'account_name' => 'logistics_payable',
                    'entry_type' => 'credit',
                    'amount' => $fleetDeliveryCut,
                    'currency' => 'NGN',
                    'description' => "Logistics fleet 90% delivery fee for Order #{$lockedOrder->order_number}",
                ]);

                // Credit Platform 10% Delivery Cut
                FinancialJournalEntry::create([
                    'order_id' => $lockedOrder->id,
                    'transaction_reference' => $lockedOrder->payment_reference ?? $lockedOrder->order_number,
                    'event_id' => $eventRef . '-PLAT-D',
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => null,
                    'account_name' => 'platform_revenue',
                    'entry_type' => 'credit',
                    'amount' => $platDeliveryCut,
                    'currency' => 'NGN',
                    'description' => "VMarket 10% platform delivery commission for Order #{$lockedOrder->order_number}",
                ]);
            }

            // C. Debit Escrow Holding Account to Balance Credits
            $totalOrderCredits = bcadd(
                $totalAcceptedMerchandise,
                $deliveryFee,
                2
            );

            FinancialJournalEntry::create([
                'order_id' => $lockedOrder->id,
                'transaction_reference' => $lockedOrder->payment_reference ?? $lockedOrder->order_number,
                'event_id' => $eventRef . '-ESCROW-DEBIT',
                'beneficiary_type' => User::class,
                'beneficiary_id' => null,
                'account_name' => 'customer_escrow_holding',
                'entry_type' => 'debit',
                'amount' => $totalOrderCredits,
                'currency' => 'NGN',
                'description' => "Escrow release for accepted items & shipping on Order #{$lockedOrder->order_number}",
            ]);

            // 8. Update Order Lifecycle
            $orderStatus = BcMathService::comp($totalAcceptedMerchandise, '0.00') > 0 ? 'delivered' : 'returned';
            $lockedOrder->order_status = $orderStatus;
            $lockedOrder->delivery_confirmed_by_customer = true;
            $lockedOrder->received_at = BcMathService::comp($totalAcceptedMerchandise, '0.00') > 0 ? $now : null;
            $lockedOrder->refund_window_expires_at = BcMathService::comp($totalAcceptedMerchandise, '0.00') > 0 ? $now->copy()->addHours($refundHours) : null;
            if ($doorstepPhotoPath) {
                $lockedOrder->proof_of_delivery_image = $doorstepPhotoPath;
            }
            $rejectionPhotos = [];
            foreach ($manifestItems as $mi) {
                if (!empty($mi['defect_photo_path'])) {
                    $rejectionPhotos[] = [
                        'order_item_id' => $mi['order_item_id'] ?? null,
                        'photo_path' => $mi['defect_photo_path'],
                        'rejection_category' => $mi['rejection_category'] ?? null,
                        'rejection_notes' => $mi['rejection_notes'] ?? null,
                    ];
                }
            }
            if (!empty($rejectionPhotos)) {
                $lockedOrder->doorstep_rejection_photos = $rejectionPhotos;
            }
            $lockedOrder->save();

            // 9. Admin Audit Log
            AdminAuditLog::create([
                'user_id' => $rider->user_id,
                'action' => 'doorstep_handshake_manifest_executed',
                'target_type' => Order::class,
                'target_id' => $lockedOrder->id,
                'old_values' => ['order_status' => 'out_for_delivery'],
                'new_values' => [
                    'order_status' => $orderStatus,
                    'accepted_merchandise' => $totalAcceptedMerchandise,
                    'rejected_merchandise' => $totalRejectedMerchandise,
                    'return_case_id' => $returnCase?->id,
                    'wallet_credit_credited' => $customerRefundWalletAmount,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'message' => $hasRejectedItems
                    ? "Delivery handover complete. ₦{$customerRefundWalletAmount} credited to customer wallet. Return OTP generated for vendor shop handover."
                    : "Order delivery successfully completed and verified.",
                'accepted_amount' => $totalAcceptedMerchandise,
                'rejected_amount' => $totalRejectedMerchandise,
                'return_case' => $returnCase,
                'wallet_credited' => $customerRefundWalletAmount,
            ];
        });
    }

    /**
     * Handshake 3: Rider returns rejected parcel to Vendor Shop.
     * Vendor inspects physical item and provides the 6-Digit Return OTP to Rider.
     */
    public function verifyVendorReturnOtp(ReturnCase $case, RiderProfile $rider, string $otp, ?string $returnPhotoPath = null): array
    {
        return DB::transaction(function () use ($case, $rider, $otp, $returnPhotoPath) {
            $lockedCase = ReturnCase::where('id', $case->id)->lockForUpdate()->first();

            if (!$lockedCase) {
                return ['success' => false, 'message' => 'Return case not found.'];
            }

            if ($lockedCase->status !== 'in_transit_back_to_vendor') {
                return ['success' => false, 'message' => "Return case is not in transit to vendor (current status: {$lockedCase->status})."];
            }

            if (trim((string)$lockedCase->vendor_return_otp) !== trim((string)$otp)) {
                return ['success' => false, 'message' => 'Invalid Vendor Return OTP. Vendor shop custody verification failed.'];
            }

            $lockedCase->status = 'returned_to_vendor';
            $lockedCase->returned_to_vendor_at = Carbon::now();
            if ($returnPhotoPath) {
                $lockedCase->vendor_return_photo_path = $returnPhotoPath;
            }
            $lockedCase->save();

            // Update item statuses to returned_to_vendor
            OrderItem::where('order_id', $lockedCase->order_id)
                ->where('rejected_quantity', '>', 0)
                ->update(['item_delivery_status' => 'returned_to_vendor']);

            AdminAuditLog::create([
                'user_id' => $rider->user_id,
                'action' => 'vendor_return_otp_handshake_verified',
                'target_type' => ReturnCase::class,
                'target_id' => $lockedCase->id,
                'old_values' => ['status' => 'in_transit_back_to_vendor'],
                'new_values' => [
                    'status' => 'returned_to_vendor',
                    'returned_to_vendor_at' => $lockedCase->returned_to_vendor_at,
                    'vendor_id' => $lockedCase->vendor_id,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'message' => 'Vendor return handshake successfully verified. Item is back in vendor custody.',
                'case' => $lockedCase,
            ];
        });
    }

    /**
     * Handshake 3 Dispute: Vendor inspects returned parcel at counter and flags discrepancy.
     * Escalates directly to Super Admin Arbitration Desk.
     */
    public function vendorDisputeReturn(
        ReturnCase $case,
        User $vendorUser,
        string $disputeReason,
        ?string $disputePhotoPath = null
    ): array {
        return DB::transaction(function () use ($case, $vendorUser, $disputeReason, $disputePhotoPath) {
            $lockedCase = ReturnCase::where('id', $case->id)->lockForUpdate()->first();

            if (!$lockedCase) {
                return ['success' => false, 'message' => 'Return case not found.'];
            }

            if (!in_array($lockedCase->status, ['in_transit_back_to_vendor', 'returned_to_vendor'])) {
                return ['success' => false, 'message' => "Cannot dispute return in status '{$lockedCase->status}'."];
            }

            $lockedCase->status = 'disputed_by_vendor';
            $lockedCase->vendor_disputed = true;
            $lockedCase->vendor_dispute_reason = $disputeReason;
            $lockedCase->vendor_dispute_photo_path = $disputePhotoPath;
            $lockedCase->vendor_disputed_at = Carbon::now();
            $lockedCase->save();

            // Update item statuses to disputed_by_vendor
            OrderItem::where('order_id', $lockedCase->order_id)
                ->where('rejected_quantity', '>', 0)
                ->update(['item_delivery_status' => 'disputed_by_vendor']);

            AdminAuditLog::create([
                'user_id' => $vendorUser->id,
                'action' => 'vendor_counter_return_disputed',
                'target_type' => ReturnCase::class,
                'target_id' => $lockedCase->id,
                'old_values' => ['vendor_disputed' => false],
                'new_values' => [
                    'status' => 'disputed_by_vendor',
                    'vendor_dispute_reason' => $disputeReason,
                    'vendor_dispute_photo_path' => $disputePhotoPath,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'message' => 'Return dispute logged successfully. Item accepted into quarantined shop custody and escalated to Super Admin Arbitration Desk.',
                'case' => $lockedCase,
            ];
        });
    }

    /**
     * Super Admin Arbitrates Disputed Return at `/admin/returns/{id}`.
     */
    public function adminArbitrateDispute(
        User $adminUser,
        ReturnCase $case,
        string $ruling,
        ?string $adminNotes = null
    ): array {
        if (!$adminUser->isSuperAdmin() && !$adminUser->isAdminEmployee()) {
            return ['success' => false, 'message' => 'Unauthorized: Only administrative staff can arbitrate return disputes.'];
        }

        return DB::transaction(function () use ($adminUser, $case, $ruling, $adminNotes) {
            $lockedCase = ReturnCase::where('id', $case->id)->lockForUpdate()->first();

            if (!$lockedCase || !$lockedCase->vendor_disputed) {
                return ['success' => false, 'message' => 'Return case is not currently flagged for dispute arbitration.'];
            }

            $validRulings = ['uphold_vendor_dispute', 'uphold_customer_claim', 'platform_transit_protection'];
            if (!in_array($ruling, $validRulings)) {
                return ['success' => false, 'message' => 'Invalid arbitration ruling.'];
            }

            $lockedCase->admin_ruling = $ruling;
            $lockedCase->admin_ruled_at = Carbon::now();
            $lockedCase->reviewed_by_admin_id = $adminUser->id;
            $lockedCase->admin_notes = $adminNotes;
            $lockedCase->status = 'resolved';

            // Financial Adjustment based on ruling
            if ($ruling === 'uphold_vendor_dispute') {
                // Vendor dispute upheld: Merchant is innocent. Customer pays 100% of delivery fee.
                $lockedCase->cost_allocation = 'customer_100';
            } elseif ($ruling === 'uphold_customer_claim') {
                // Customer claim upheld: Vendor fault confirmed.
                $lockedCase->cost_allocation = 'vendor_100';
                // Debit vendor recovery ledger for delivery fee penalty
                VendorRecoveryLedger::create([
                    'vendor_id' => $lockedCase->vendor_id,
                    'order_id' => $lockedCase->order_id,
                    'return_case_id' => $lockedCase->id,
                    'amount' => $lockedCase->order->delivery_fee ?? 1500.00,
                    'recovery_reason' => "Arbitration penalty: Vendor fault upheld for Return #{$lockedCase->case_number}",
                    'status' => 'pending_recovery',
                ]);
            } else {
                // Platform transit protection: VMarket absorbs the transit risk
                $lockedCase->cost_allocation = 'platform_absorbed';
            }
            $lockedCase->save();

            AdminAuditLog::create([
                'user_id' => $adminUser->id,
                'action' => 'admin_arbitrated_return_dispute',
                'target_type' => ReturnCase::class,
                'target_id' => $lockedCase->id,
                'old_values' => ['status' => 'disputed_by_vendor'],
                'new_values' => [
                    'status' => 'resolved',
                    'ruling' => $ruling,
                    'cost_allocation' => $lockedCase->cost_allocation,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'message' => "Dispute arbitrated successfully. Ruling: {$ruling}.",
                'case' => $lockedCase,
            ];
        });
    }
}
