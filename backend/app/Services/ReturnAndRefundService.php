<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\CustomerCashbackLedger;
use App\Models\FinancialJournalEntry;
use App\Models\LogisticsCollectionPoint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnCase;
use App\Models\ReturnEvidence;
use App\Models\ReturnInspection;
use App\Models\ReturnItem;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorRecoveryLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReturnAndRefundService
{
    protected PaystackService $paystackService;

    public function __construct(?PaystackService $paystackService = null)
    {
        $this->paystackService = $paystackService ?? app(PaystackService::class);
    }

    /**
     * Submit an Item-Level Return Request within the Configurable Refund Window.
     * Enforces customer ownership, delivered status, item quantity constraints,
     * and collection point assignment. NEVER automatically disburses funds.
     */
    public function createReturnCase(
        User $customer,
        Order $order,
        array $itemsData,
        string $reason,
        ?string $customerDescription = null,
        ?int $collectionPointId = null,
        array $evidencePaths = []
    ): array {
        return DB::transaction(function () use ($customer, $order, $itemsData, $reason, $customerDescription, $collectionPointId, $evidencePaths) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder) {
                return ['success' => false, 'message' => 'Order not found under lock.'];
            }

            // 1. Customer Ownership Check (Zero IDOR)
            if ((int)$lockedOrder->customer_id !== (int)$customer->id) {
                return ['success' => false, 'message' => 'Unauthorized: This order does not belong to your account.'];
            }

            // 2. Order Delivered Status Guard
            if (!in_array($lockedOrder->order_status, ['delivered', 'confirmed'])) {
                return ['success' => false, 'message' => "Order cannot be returned (current status: {$lockedOrder->order_status})."];
            }

            // 3. Configurable Refund Window Guard (SystemSetting)
            $refundHours = max(1, (int) SystemSetting::get('refund_window_hours', 24));
            $windowExpiresAt = $lockedOrder->refund_window_expires_at;

            if (!$windowExpiresAt && $lockedOrder->received_at) {
                $windowExpiresAt = $lockedOrder->received_at->copy()->addHours($refundHours);
            }

            if ($windowExpiresAt && Carbon::now()->gt($windowExpiresAt)) {
                return [
                    'success' => false,
                    'code' => 'RETURN_WINDOW_EXPIRED',
                    'message' => "The {$refundHours}-hour return and refund window for this order has expired.",
                ];
            }

            // 4. Collection Point Validation
            if ($collectionPointId) {
                $cp = LogisticsCollectionPoint::where('id', $collectionPointId)->where('is_active', true)->first();
                if (!$cp) {
                    return ['success' => false, 'message' => 'Selected return collection point is invalid or inactive.'];
                }
            }

            if (empty($itemsData)) {
                return ['success' => false, 'message' => 'At least one order item must be selected for return.'];
            }

            $totalRefundableAmount = '0.00';
            $validatedItems = [];

            foreach ($itemsData as $itemInput) {
                $orderItemId = (int) ($itemInput['order_item_id'] ?? 0);
                $quantity = max(1, (int) ($itemInput['quantity'] ?? 1));
                $condition = (string) ($itemInput['item_condition'] ?? 'opened_tested');

                $orderItem = OrderItem::where('id', $orderItemId)
                    ->where('order_id', $lockedOrder->id)
                    ->first();

                if (!$orderItem) {
                    return ['success' => false, 'message' => "Order item #{$orderItemId} does not belong to this order."];
                }

                // Check already returned or requested quantities
                $alreadyReturnedQty = (int) ReturnItem::where('order_item_id', $orderItem->id)
                    ->whereHas('returnCase', function ($q) {
                        $q->whereNotIn('status', ['rejected', 'closed']);
                    })
                    ->sum('quantity');

                $availableToReturn = $orderItem->quantity - $alreadyReturnedQty;

                if ($quantity > $availableToReturn) {
                    return [
                        'success' => false,
                        'message' => "Cannot return {$quantity} units of '{$orderItem->product_name}'. Only {$availableToReturn} eligible units remain.",
                    ];
                }

                $itemSubtotal = bcmul((string)$orderItem->unit_price, (string)$quantity, 2);
                $totalRefundableAmount = bcadd($totalRefundableAmount, $itemSubtotal, 2);

                $validatedItems[] = [
                    'order_item' => $orderItem,
                    'quantity' => $quantity,
                    'unit_price' => $orderItem->unit_price,
                    'line_total' => $itemSubtotal,
                    'allocated_discount' => '0.00',
                    'refundable_amount' => $itemSubtotal,
                    'item_condition' => $condition,
                ];
            }

            // 5. Create Sovereign Return Case
            $returnCase = ReturnCase::create([
                'case_number' => ReturnCase::generateCaseNumber(),
                'customer_id' => $customer->id,
                'order_id' => $lockedOrder->id,
                'parent_order_id' => $lockedOrder->parent_order_id,
                'vendor_id' => $lockedOrder->vendor_id,
                'collection_point_id' => $collectionPointId,
                'return_reason' => $reason,
                'customer_description' => $customerDescription,
                'status' => 'requested',
                'resolution_type' => 'refund',
                'total_refundable_amount' => $totalRefundableAmount,
                'refund_delivery_fee' => false,
                'delivery_fee_refunded' => '0.00',
                'refunded_amount' => '0.00',
            ]);

            // 6. Insert Return Items
            foreach ($validatedItems as $vItem) {
                ReturnItem::create([
                    'return_case_id' => $returnCase->id,
                    'order_item_id' => $vItem['order_item']->id,
                    'product_id' => $vItem['order_item']->product_id,
                    'quantity' => $vItem['quantity'],
                    'unit_price' => $vItem['unit_price'],
                    'line_total' => $vItem['line_total'],
                    'allocated_discount' => $vItem['allocated_discount'],
                    'refundable_amount' => $vItem['refundable_amount'],
                    'item_condition' => $vItem['item_condition'],
                    'status' => 'requested',
                ]);
            }

            // 7. Attach Evidence
            foreach ($evidencePaths as $ev) {
                ReturnEvidence::create([
                    'return_case_id' => $returnCase->id,
                    'uploaded_by_id' => $customer->id,
                    'uploaded_by_role' => 'customer',
                    'file_path' => is_array($ev) ? ($ev['path'] ?? '') : (string)$ev,
                    'evidence_type' => is_array($ev) ? ($ev['type'] ?? 'photo') : 'photo',
                    'caption' => is_array($ev) ? ($ev['caption'] ?? null) : null,
                ]);
            }

            AdminAuditLog::create([
                'user_id' => $customer->id,
                'action' => 'return_case_requested',
                'target_type' => ReturnCase::class,
                'target_id' => $returnCase->id,
                'old_values' => null,
                'new_values' => [
                    'case_number' => $returnCase->case_number,
                    'order_id' => $lockedOrder->id,
                    'total_refundable_amount' => $totalRefundableAmount,
                    'item_count' => count($validatedItems),
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'message' => 'Return request submitted successfully. Please drop off the parcel at the designated collection point.',
                'case' => $returnCase->load(['items.product', 'collectionPoint']),
            ];
        });
    }

    /**
     * Hub Staff Records Physical Inspection at Accredited Collection Point.
     */
    public function recordHubInspection(
        User $inspectorUser,
        ReturnCase $case,
        int $collectionPointId,
        string $conditionRating,
        ?string $serialOrImei = null,
        bool $packagingIntact = true,
        ?string $inspectionNotes = null,
        string $decision = 'accept_for_refund'
    ): array {
        return DB::transaction(function () use ($inspectorUser, $case, $collectionPointId, $conditionRating, $serialOrImei, $packagingIntact, $inspectionNotes, $decision) {
            $lockedCase = ReturnCase::where('id', $case->id)->lockForUpdate()->first();

            if (!$lockedCase) {
                return ['success' => false, 'message' => 'Return case not found.'];
            }

            $inspection = ReturnInspection::create([
                'return_case_id' => $lockedCase->id,
                'collection_point_id' => $collectionPointId,
                'inspected_by_user_id' => $inspectorUser->id,
                'condition_rating' => $conditionRating,
                'serial_number_or_imei' => $serialOrImei,
                'packaging_intact' => $packagingIntact,
                'inspection_notes' => $inspectionNotes,
                'inspection_decision' => $decision,
                'inspected_at' => Carbon::now(),
            ]);

            $lockedCase->collection_point_id = $collectionPointId;
            $lockedCase->status = 'inspected';
            $lockedCase->save();

            // Update individual return items
            $itemStatus = ($decision === 'accept_for_refund' || $decision === 'resalable_change_of_mind' || $decision === 'vendor_fault_confirmed')
                ? 'inspected_accepted'
                : 'inspected_rejected';

            ReturnItem::where('return_case_id', $lockedCase->id)->update(['status' => $itemStatus]);

            AdminAuditLog::create([
                'user_id' => $inspectorUser->id,
                'action' => 'return_case_inspected_at_hub',
                'target_type' => ReturnCase::class,
                'target_id' => $lockedCase->id,
                'old_values' => ['status' => 'requested'],
                'new_values' => [
                    'status' => 'inspected',
                    'decision' => $decision,
                    'condition_rating' => $conditionRating,
                    'collection_point_id' => $collectionPointId,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'message' => 'Return parcel inspected and logged successfully at collection point.',
                'inspection' => $inspection,
                'case' => $lockedCase->fresh(),
            ];
        });
    }

    /**
     * Super Admin Reviews and Approves Return for Refund.
     */
    public function adminApproveReturn(User $adminUser, ReturnCase $case, ?string $adminNotes = null): array
    {
        if (!$adminUser->isSuperAdmin() && $adminUser->user_type !== 'admin_employee') {
            return ['success' => false, 'message' => 'Unauthorized: Only administrative staff can review return cases.'];
        }

        return DB::transaction(function () use ($adminUser, $case, $adminNotes) {
            $lockedCase = ReturnCase::where('id', $case->id)->lockForUpdate()->first();

            if (!in_array($lockedCase->status, ['requested', 'under_review', 'received_at_hub', 'inspected'])) {
                return ['success' => false, 'message' => "Cannot approve return case in '{$lockedCase->status}' status."];
            }

            $lockedCase->status = 'refund_pending';
            $lockedCase->reviewed_by_admin_id = $adminUser->id;
            $lockedCase->admin_notes = $adminNotes;
            $lockedCase->save();

            ReturnItem::where('return_case_id', $lockedCase->id)
                ->where('status', '!=', 'inspected_rejected')
                ->update(['status' => 'approved']);

            AdminAuditLog::create([
                'user_id' => $adminUser->id,
                'action' => 'return_case_approved_for_refund',
                'target_type' => ReturnCase::class,
                'target_id' => $lockedCase->id,
                'old_values' => ['status' => $case->status],
                'new_values' => ['status' => 'refund_pending', 'reviewed_by_admin_id' => $adminUser->id],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'message' => 'Return case approved for refund. Awaiting manual refund disbursement.',
                'case' => $lockedCase,
            ];
        });
    }

    /**
     * Authoritative Super Admin Manual Refund Disbursement.
     * Enforces strict double-entry balancing, reversals of 90/5/5 merchandise cuts,
     * cashback reward cancellation, Paystack API integration, or documented bank transfer proof.
     */
    public function adminDisburseRefund(
        User $adminUser,
        ReturnCase $case,
        ?string $bankReference = null,
        ?string $proofPath = null,
        bool $refundDeliveryFee = false
    ): array {
        if (!$adminUser->isSuperAdmin()) {
            return ['success' => false, 'message' => 'Unauthorized: Only Super Admin can disburse treasury refunds.'];
        }

        return DB::transaction(function () use ($adminUser, $case, $bankReference, $proofPath, $refundDeliveryFee) {
            $lockedCase = ReturnCase::where('id', $case->id)->lockForUpdate()->first();
            $order = Order::where('id', $lockedCase->order_id)->lockForUpdate()->first();

            if (!in_array($lockedCase->status, ['refund_pending', 'inspected', 'approved_for_return'])) {
                return ['success' => false, 'message' => "Cannot disburse refund for case in '{$lockedCase->status}' status."];
            }

            // Calculate exact refund amount
            $merchandiseRefund = $lockedCase->total_refundable_amount;
            $deliveryFeeRefund = '0.00';

            if ($refundDeliveryFee && bccomp((string)$order->delivery_fee, '0.00', 2) > 0) {
                $deliveryFeeRefund = (string)$order->delivery_fee;
            }

            $totalCashRefund = bcadd($merchandiseRefund, $deliveryFeeRefund, 2);

            // Calculate 90/5/5 Merchandise Reversal Splits
            $vendorNetDebit = bcmul($merchandiseRefund, '0.9000', 2);
            $customerCashbackDebit = bcmul($merchandiseRefund, '0.0500', 2);
            $platformMarginDebit = bcmul($merchandiseRefund, '0.0500', 2);

            // Re-verify exact balance sum
            $calculatedMerchandiseTotal = bcadd(bcadd($vendorNetDebit, $customerCashbackDebit, 2), $platformMarginDebit, 2);
            if (bccomp($calculatedMerchandiseTotal, $merchandiseRefund, 2) !== 0) {
                $diff = bcsub($merchandiseRefund, $calculatedMerchandiseTotal, 2);
                $vendorNetDebit = bcadd($vendorNetDebit, $diff, 2);
            }

            $disburseRef = 'REFUND-' . $lockedCase->case_number . '-' . date('YmdHis');
            $refToStore = $bankReference ?: $disburseRef;

            // 1. Post Double-Entry Journal Entries
            // Credit: Cash in Escrow (Exact Cash Outflow to Customer)
            FinancialJournalEntry::create([
                'order_id' => $order->id,
                'transaction_reference' => $refToStore,
                'event_id' => 'EVT-REFUND-PAY-' . $disburseRef,
                'beneficiary_type' => User::class,
                'beneficiary_id' => $order->customer_id,
                'account_name' => 'cash_in_escrow',
                'entry_type' => 'credit',
                'amount' => $totalCashRefund,
                'currency' => 'NGN',
                'description' => "Manual refund disbursement to customer for Return Case #{$lockedCase->case_number}",
            ]);

            // Debit: Vendor Payable (Reduce held merchant earnings)
            // If vendor payout is already settled, log into vendor recovery ledger
            if ($order->vendor_settlement_status === 'settled') {
                VendorRecoveryLedger::create([
                    'vendor_id' => $order->vendor_id,
                    'return_case_id' => $lockedCase->id,
                    'order_id' => $order->id,
                    'amount' => $vendorNetDebit,
                    'status' => 'pending_recovery',
                    'notes' => "Post-settlement return claim: ₦{$vendorNetDebit} owed for Return #{$lockedCase->case_number}",
                ]);

                FinancialJournalEntry::create([
                    'order_id' => $order->id,
                    'transaction_reference' => $refToStore,
                    'event_id' => 'EVT-REFUND-VND-RECOVERY-' . $disburseRef,
                    'beneficiary_type' => VendorProfile::class,
                    'beneficiary_id' => $order->vendor_id,
                    'account_name' => 'vendor_recovery_receivable',
                    'entry_type' => 'debit',
                    'amount' => $vendorNetDebit,
                    'currency' => 'NGN',
                    'description' => "Merchant recovery claim for post-settlement return #{$lockedCase->case_number}",
                ]);
            } else {
                FinancialJournalEntry::create([
                    'order_id' => $order->id,
                    'transaction_reference' => $refToStore,
                    'event_id' => 'EVT-REFUND-VND-DEBIT-' . $disburseRef,
                    'beneficiary_type' => VendorProfile::class,
                    'beneficiary_id' => $order->vendor_id,
                    'account_name' => 'vendor_payable',
                    'entry_type' => 'debit',
                    'amount' => $vendorNetDebit,
                    'currency' => 'NGN',
                    'description' => "Merchant 90% Net Payable reversed for Return #{$lockedCase->case_number}",
                ]);
            }

            // Debit: Platform Revenue Reversal (5% Commission surrendered)
            if (bccomp($platformMarginDebit, '0.00', 2) > 0) {
                FinancialJournalEntry::create([
                    'order_id' => $order->id,
                    'transaction_reference' => $refToStore,
                    'event_id' => 'EVT-REFUND-PLATFORM-REV-' . $disburseRef,
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => null,
                    'account_name' => 'platform_revenue',
                    'entry_type' => 'debit',
                    'amount' => $platformMarginDebit,
                    'currency' => 'NGN',
                    'description' => "Platform 5% margin reversed for Return #{$lockedCase->case_number}",
                ]);
            }

            // Debit: Cashback Liability Reversal (5% Customer Reward cancelled)
            if (bccomp($customerCashbackDebit, '0.00', 2) > 0) {
                FinancialJournalEntry::create([
                    'order_id' => $order->id,
                    'transaction_reference' => $refToStore,
                    'event_id' => 'EVT-REFUND-CB-REV-' . $disburseRef,
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => $order->customer_id,
                    'account_name' => 'cashback_liability',
                    'entry_type' => 'debit',
                    'amount' => $customerCashbackDebit,
                    'currency' => 'NGN',
                    'description' => "Customer 5% cashback reward liability reversed for Return #{$lockedCase->case_number}",
                ]);

                // Forfeit pending maturation cashback reward in customer ledger
                CustomerCashbackLedger::where('order_id', $order->id)
                    ->where('status', 'pending_maturation')
                    ->update(['status' => 'forfeited']);
            }

            // If delivery fee was refunded, reverse logistics payable and platform delivery cut
            if (bccomp($deliveryFeeRefund, '0.00', 2) > 0) {
                $fleetDebit = bcmul($deliveryFeeRefund, '0.9000', 2);
                $platDeliveryDebit = bcmul($deliveryFeeRefund, '0.1000', 2);

                FinancialJournalEntry::create([
                    'order_id' => $order->id,
                    'transaction_reference' => $refToStore,
                    'event_id' => 'EVT-REFUND-FLEET-' . $disburseRef,
                    'beneficiary_type' => \App\Models\LogisticsCompanyProfile::class,
                    'beneficiary_id' => $order->logistics_company_id,
                    'account_name' => 'logistics_payable',
                    'entry_type' => 'debit',
                    'amount' => $fleetDebit,
                    'currency' => 'NGN',
                    'description' => "Logistics fee reversed for Return #{$lockedCase->case_number}",
                ]);

                FinancialJournalEntry::create([
                    'order_id' => $order->id,
                    'transaction_reference' => $refToStore,
                    'event_id' => 'EVT-REFUND-PLATFORM-DELIVERY-' . $disburseRef,
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => null,
                    'account_name' => 'platform_revenue',
                    'entry_type' => 'debit',
                    'amount' => $platDeliveryDebit,
                    'currency' => 'NGN',
                    'description' => "Platform 10% delivery fee reversed for Return #{$lockedCase->case_number}",
                ]);
            }

            // 2. Automated Paystack Gateway Refund Call if payment reference exists
            $paystackRefundId = null;
            if (!empty($order->payment_reference)) {
                $refundRes = $this->paystackService->initiateRefund(
                    $order->payment_reference,
                    $totalCashRefund,
                    "Manual refund for Return Case #{$lockedCase->case_number}"
                );
                if (!empty($refundRes['success'])) {
                    $paystackRefundId = $refundRes['refund_reference'] ?? null;
                }
            }

            // 3. Update Return Case State
            $lockedCase->status = 'refunded';
            $lockedCase->refunded_amount = $totalCashRefund;
            $lockedCase->refund_delivery_fee = $refundDeliveryFee;
            $lockedCase->delivery_fee_refunded = $deliveryFeeRefund;
            $lockedCase->paystack_refund_id = $paystackRefundId;
            $lockedCase->bank_reference = $refToStore;
            $lockedCase->refund_proof_path = $proofPath;
            $lockedCase->refunded_at = Carbon::now();
            $lockedCase->save();

            ReturnItem::where('return_case_id', $lockedCase->id)->update(['status' => 'refunded']);

            AdminAuditLog::create([
                'user_id' => $adminUser->id,
                'action' => 'return_refund_disbursed_manually',
                'target_type' => ReturnCase::class,
                'target_id' => $lockedCase->id,
                'old_values' => ['status' => 'refund_pending'],
                'new_values' => [
                    'status' => 'refunded',
                    'total_refunded' => $totalCashRefund,
                    'merchandise_refund' => $merchandiseRefund,
                    'delivery_fee_refunded' => $deliveryFeeRefund,
                    'bank_reference' => $refToStore,
                    'paystack_refund_id' => $paystackRefundId,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'success' => true,
                'message' => "Refund of ₦" . number_format((float)$totalCashRefund, 2) . " disbursed successfully for Case #{$lockedCase->case_number}.",
                'case' => $lockedCase,
                'total_refunded' => $totalCashRefund,
            ];
        });
    }
}
