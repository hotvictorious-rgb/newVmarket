<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\CustomerCashbackLedger;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialReservation;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnCase;
use App\Models\ReturnItem;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\NotificationDispatchService;
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

            if ($lockedOrder->fulfillment_type !== 'doorstep_delivery') {
                return [
                    'success' => false,
                    'message' => 'Doorstep delivery handover is only available for doorstep deliveries. In-shop pickup orders are fulfilled at the merchant counter.',
                ];
            }

            // A customer OTP proves handover only; it does not establish payment.
            // This alternate manifest path must enforce the same payment prerequisite
            // as the standard delivery service before any item, refund or cashback writes.
            if ($lockedOrder->payment_status !== 'paid') {
                return ['success' => false, 'message' => 'Unpaid orders cannot undergo doorstep handover.'];
            }

            // Guard against duplicate / replayed delivery execution:
            if (in_array($lockedOrder->order_status, ['delivered', 'returned'], true)) {
                $expectedOtp = trim((string)$lockedOrder->verification_code);
                $providedOtp = trim((string)$deliveryOtp);
                if (!empty($expectedOtp) && hash_equals($expectedOtp, $providedOtp)) {
                    return [
                        'success' => true,
                        'replayed' => true,
                        'message' => 'Doorstep delivery was already confirmed and recorded.',
                        'order' => $lockedOrder,
                    ];
                }
            }

            // This service is the terminal doorstep handover transition, not a shortcut
            // from vendor preparation or pickup-ready states. Rider custody must already
            // have moved the order into out_for_delivery.
            if ($lockedOrder->order_status !== 'out_for_delivery') {
                return ['success' => false, 'message' => 'Order must be out for delivery before doorstep handover.'];
            }

            // Guard against invalid or cancelled order states under lock
            if (in_array($lockedOrder->order_status, ['cancelled', 'pending_payment', 'refunded'])) {
                return [
                    'success' => false,
                    'message' => "Order cannot undergo doorstep handover (current status: {$lockedOrder->order_status}).",
                ];
            }

            // 1. Verify the central logistics-company relationship and exact order assignment.
            // Vendor/shop associations may remain as private sourcing metadata, but they
            // must not authorize a rider or dispatch transition.
            $haltReason = null;
            if (!$rider->isEligibleForCentralOrder($lockedOrder)) {
                $haltReason = 'Unauthorized: Handover requires the assigned active rider to be linked exclusively to the order active logistics company.';
            }

            if (!$haltReason && $lockedOrder->logisticsCompany) {
                if ($lockedOrder->logisticsCompany->deleted_at !== null
                    || $lockedOrder->logisticsCompany->status !== 'active'
                    || ($lockedOrder->logisticsCompany->user && $lockedOrder->logisticsCompany->user->status !== 'active')) {
                    $haltReason = 'Assigned logistics company is inactive or suspended. Delivery handover halted.';
                }
            } elseif (!$haltReason) {
                $haltReason = 'Order has no approved logistics-company assignment. Delivery handover halted.';
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

            // Photo proof is an owner-approved fulfilment invariant, not merely an HTTP
            // validation rule. Direct service callers must not bypass the evidence requirement.
            if (!is_string($doorstepPhotoPath) || trim($doorstepPhotoPath) === '') {
                return ['success' => false, 'message' => 'Doorstep photo proof is required before handover can be completed.'];
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

            // 4. Validate Manifest Completeness & Prevent Duplicate Lines (Findings 6 & 7)
            $manifestItemIds = [];
            foreach ($manifestItems as $line) {
                $id = (int) ($line['order_item_id'] ?? 0);
                if ($id <= 0) {
                    return [
                        'success' => false,
                        'message' => 'Invalid order_item_id specified in delivery manifest.',
                    ];
                }
                if (in_array($id, $manifestItemIds, true)) {
                    return [
                        'success' => false,
                        'message' => "Duplicate order_item_id #{$id} detected in delivery manifest.",
                    ];
                }
                $manifestItemIds[] = $id;
            }

            // Ensure complete manifest coverage: all order items must be accounted for
            $allOrderItemIds = OrderItem::where('order_id', $lockedOrder->id)->pluck('id')->all();
            $missingItemIds = array_diff($allOrderItemIds, $manifestItemIds);
            if (!empty($missingItemIds)) {
                return [
                    'success' => false,
                    'message' => "Delivery manifest is incomplete: Order item(s) #" . implode(', #', $missingItemIds) . " are missing from the handover manifest.",
                ];
            }
            $refundPolicy = app(RefundPolicyService::class);
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

                $alloc = $rejectedQty > 0
                    ? $refundPolicy->calculateItemAllocation($lockedOrder, $orderItem, $rejectedQty)
                    : [
                        'unit_price' => (string)$orderItem->unit_price,
                        'quantity' => 0,
                        'line_total' => '0.00',
                        'allocated_discount' => '0.00',
                        'refundable_amount' => '0.00',
                        'refundable_kobo' => 0,
                    ];

                $totalAcceptedMerchandise = bcadd($totalAcceptedMerchandise, $acceptedSubtotal, 2);
                $totalRejectedMerchandise = bcadd($totalRejectedMerchandise, $alloc['refundable_amount'], 2);

                $validatedLines[] = [
                    'order_item' => $orderItem,
                    'accepted_qty' => $acceptedQty,
                    'rejected_qty' => $rejectedQty,
                    'category' => $category,
                    'notes' => $notes,
                    'defect_photo_path' => $itemInput['defect_photo_path'] ?? null,
                    'accepted_subtotal' => $acceptedSubtotal,
                    'rejected_subtotal' => $rejectedSubtotal,
                    'allocated_discount' => $alloc['allocated_discount'],
                    'refundable_amount' => $alloc['refundable_amount'],
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

            // 6. Handle Rejection Flow (Return Case + Refund Reservation)
            $returnCase = null;
            $refundRequestedAmount = '0.00';

            if ($hasRejectedItems) {
                // Retailer orders may contain rejected items from multiple private
                // suppliers. A single vendor OTP cannot prove custody for several
                // shops, so hold those cases for explicit supplier assignment.
                $rejectedSupplierIds = collect($validatedLines)
                    ->filter(fn ($line) => (int) ($line['rejected_qty'] ?? 0) > 0)
                    ->map(fn ($line) => (int) ($line['order_item']->fulfillment_supplier_vendor_id ?? 0))
                    ->filter(fn ($supplierId) => $supplierId > 0)
                    ->unique()
                    ->values();
                $requiresSupplierAssignment = $lockedOrder->retailer_model_order
                    && $rejectedSupplierIds->count() !== 1;

                // Generate a six-digit vendor return handshake OTP. It is delivered
                // privately to the verified supplier counter, never to the rider.
                $vendorReturnOtp = (string) random_int(100000, 999999);

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
                    'status' => $requiresSupplierAssignment ? 'pending_supplier_assignment' : 'in_transit_back_to_vendor',
                    'resolution_type' => 'refund',
                    'total_refundable_amount' => $totalRejectedMerchandise,
                    'refund_delivery_fee' => false,
                    'delivery_fee_refunded' => '0.00',
                    'refunded_amount' => '0.00',
                ]);

                // Attach Return Items with authoritatively calculated refundable amounts
                foreach ($validatedLines as $line) {
                    if ($line['rejected_qty'] > 0) {
                        ReturnItem::create([
                            'return_case_id' => $returnCase->id,
                            'order_item_id' => $line['order_item']->id,
                            'product_id' => $line['order_item']->product_id,
                            'quantity' => $line['rejected_qty'],
                            'unit_price' => $line['order_item']->unit_price,
                            'line_total' => $line['rejected_subtotal'],
                            'allocated_discount' => $line['allocated_discount'],
                            'refundable_amount' => $line['refundable_amount'],
                            'item_condition' => $line['category'] === 'vendor_issue' ? 'damaged_defective' : 'unopened_sealed',
                            'status' => 'requested',
                        ]);
                    }
                }

                // Calculate Cost Allocation & Refund Liability
                if ($predominantRejectionCategory === 'customer_change_of_mind') {
                    // Customer change of mind: Delivery fee was rendered and paid upfront; customer receives product refund only (NOT deducted twice)
                    $refundRequestedAmount = $totalRejectedMerchandise;
                    $returnCase->cost_allocation = 'customer_100';
                } elseif ($predominantRejectionCategory === 'transit_damage') {
                    // Under vendor-managed delivery, the owning vendor is responsible for
                    // its rider's transit handling. Any vendor recovery is separately reviewed;
                    // the platform creates no fleet/rider payable.
                    $refundRequestedAmount = $totalRejectedMerchandise;
                    $returnCase->cost_allocation = 'vendor_100';
                } else {
                    // Vendor issue: Vendor penalized, Customer gets full refund
                    $refundRequestedAmount = $totalRejectedMerchandise;
                    $returnCase->cost_allocation = 'vendor_100';
                }
                $returnCase->save();

                // No wallet credit is issued for a doorstep rejection. This rider event
                // records the return request and reserves the rejected merchandise amount;
                // an authorized reviewer must approve a refund through the original Paystack
                // transaction after vendor custody and evidence checks.
                // Reserve funds on financial ledger to guarantee availability and prevent double refund
                FinancialReservation::create([
                    'financial_operation_id' => null,
                    'source_type' => Order::class,
                    'source_id' => $lockedOrder->id,
                    'reserved_amount_kobo' => (int) bcmul($refundRequestedAmount, '100', 0),
                    'purpose' => 'customer_refund_hold',
                    'status' => 'active',
                ]);

                // Trigger sovereign multi-channel notifications
                NotificationDispatchService::dispatchDoorstepReturnInitiated($returnCase);
                NotificationDispatchService::dispatchVendorReturnOtpIssued($returnCase);
            }

            if (!$lockedOrder->retailer_model_order) {
            // 7. Post Balanced Double-Entry Journal Entries
            $eventRef = 'EVT-HANDOVER-' . $lockedOrder->order_number . '-' . $lockedOrder->id;

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
                    'matures_at' => $now->copy()->addHours($refundHours + 24),
                    'expires_at' => $now->copy()->addHours($refundHours + 24)->addDays(365),
                    'notes' => "5% reward for accepted items on Order #{$lockedOrder->order_number}",
                ]);
            }

            // A fully rejected doorstep handover must not release delivery-fee revenue/payable.
            // Keep the delivery fee in escrow for the separately authorized admin remedy/refund.
            $deliveryFeeForSettlement = BcMathService::comp($totalAcceptedMerchandise, '0.00') > 0
                ? $deliveryFee
                : '0.00';

            // B. Delivery Fee Payout (only after at least one item is accepted)
            if (BcMathService::comp($deliveryFeeForSettlement, '0.00') > 0) {
                if ($lockedOrder->logistics_company_id && empty($rider->vendor_id)) {
                    // Legacy 3rd party logistics fleet
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
                } else {
                    // Vendor-managed delivery: Merchant receives 90% delivery allocation
                    FinancialJournalEntry::create([
                        'order_id' => $lockedOrder->id,
                        'transaction_reference' => $lockedOrder->payment_reference ?? $lockedOrder->order_number,
                        'event_id' => $eventRef . '-VEND-DELIVERY',
                        'beneficiary_type' => VendorProfile::class,
                        'beneficiary_id' => $lockedOrder->vendor_id,
                        'account_name' => 'vendor_payable',
                        'entry_type' => 'credit',
                        'amount' => $fleetDeliveryCut,
                        'currency' => 'NGN',
                        'description' => "Merchant 90% delivery fee for Order #{$lockedOrder->order_number}",
                    ]);
                }

                // Credit Platform 10% Delivery Commission
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
                $deliveryFeeForSettlement,
                2
            );

            // Do not create zero-value journal entries when every item was rejected.
            if (BcMathService::comp($totalOrderCredits, '0.00') > 0) {
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
            }


            } else {
                // Retailer payment settlement already booked item-cost supplier payables,
                // the active 5% cashback liability and the 90% company payable snapshot.
                // Do not post legacy 90/5/5 vendor or delivery journals a second time.
                $cashbackLedger = CustomerCashbackLedger::where('order_id', $lockedOrder->id)
                    ->lockForUpdate()
                    ->first();

                if (BcMathService::comp((string) $lockedOrder->customer_cashback_5, '0.00') > 0 && !$cashbackLedger) {
                    throw new \RuntimeException('Retailer cashback liability exists without its cashback ledger lot.');
                }

                if ($cashbackLedger && BcMathService::comp($totalAcceptedMerchandise, '0.00') > 0) {
                    // Maturity starts from verified customer handover, not payment capture.
                    // The scheduled maturity command sets the 365-day expiry when the lot
                    // actually becomes available after all blocking cases have cleared.
                    $cashbackLedger->status = 'pending_maturation';
                    $cashbackLedger->matures_at = $now->copy()->addHours($refundHours + 24);
                    $cashbackLedger->expires_at = null;
                    $cashbackLedger->save();
                }
            }

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

            if ($lockedOrder->retailer_model_order && $orderStatus === 'delivered') {
                app(\App\Services\LogisticsCompanyPayableService::class)->releaseAfterVerifiedDelivery($lockedOrder);
            }

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
                    'refund_requested_amount' => $refundRequestedAmount,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'message' => $hasRejectedItems
                    ? ($returnCase && $returnCase->status === 'pending_supplier_assignment'
                        ? 'Delivery handover recorded. Rejected items belong to multiple or unresolved suppliers; admin assignment is required before return custody can be verified. No return OTP was disclosed to the rider.'
                        : 'Delivery handover recorded. Rejected items are pending supplier return and refund review; the return OTP is sent privately to the assigned supplier counter. No wallet credit has been issued.')
                    : 'Order delivery successfully completed and verified.',
                'accepted_amount' => $totalAcceptedMerchandise,
                'rejected_amount' => $totalRejectedMerchandise,
                'return_case' => $returnCase,
                'refund_requested_amount' => $refundRequestedAmount,
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

            if ((int) $lockedCase->returned_by_rider_id !== (int) $rider->user_id) {
                return ['success' => false, 'message' => 'This return case is not assigned to the authenticated rider.'];
            }
            if ($lockedCase->status === 'pending_supplier_assignment') {
                return ['success' => false, 'message' => 'This return contains items from multiple or unresolved suppliers. Admin assignment is required before vendor handover verification.'];
            }
            if ($lockedCase->status !== 'in_transit_back_to_vendor') {
                return ['success' => false, 'message' => "Return case is not in transit to vendor (current status: {$lockedCase->status})."];
            }

            if ($lockedCase->order?->retailer_model_order) {
                $supplierIds = ReturnItem::where('return_case_id', $lockedCase->id)
                    ->with('orderItem')
                    ->get()
                    ->map(fn ($item) => (int) ($item->orderItem?->fulfillment_supplier_vendor_id ?? 0))
                    ->filter(fn ($supplierId) => $supplierId > 0)
                    ->unique()
                    ->values();
                if ($supplierIds->isEmpty()) {
                    $supplierIds = OrderItem::where('order_id', $lockedCase->order_id)
                        ->pluck('fulfillment_supplier_vendor_id')
                        ->map(fn ($supplierId) => (int) $supplierId)
                        ->filter(fn ($supplierId) => $supplierId > 0)
                        ->unique()
                        ->values();
                }
                if ($supplierIds->count() !== 1) {
                    return ['success' => false, 'message' => 'A single supplier OTP cannot confirm custody for a multi-supplier return. Admin assignment is required.'];
                }
                $supplier = VendorProfile::with('user')->find($supplierIds->first());
                $activeShop = $supplier?->shops()->where('is_active', true)->first();
                if (!$supplier || $supplier->marketplace_status !== 'active'
                    || !$supplier->user || $supplier->user->status !== 'active' || !$activeShop) {
                    return ['success' => false, 'message' => 'The assigned supplier or shop is inactive. Admin assignment is required before custody can be confirmed.'];
                }
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

            // Trigger sovereign return accepted notification
            NotificationDispatchService::dispatchReturnAccepted($lockedCase);

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

            // Trigger dispute escalation notification
            NotificationDispatchService::dispatchVendorCounterDispute($lockedCase, $disputeReason);

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
        if ($adminUser->status !== 'active' || (!$adminUser->isSuperAdmin() && !$adminUser->isAdminEmployee())) {
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
                // Customer claim upheld: vendor fault is established, but no receivable is posted
                // until the platform actually funds a refund. ReturnAndRefundService posts the
                // authorised, component-specific recovery exactly once at refund settlement.
                $lockedCase->cost_allocation = 'vendor_100';
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
