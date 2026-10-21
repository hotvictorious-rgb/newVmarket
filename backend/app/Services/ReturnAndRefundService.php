<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\CustomerCashbackLedger;
use App\Models\CashbackRecoveryLedger;
use App\Models\CustomerWalletCredit;
use App\Models\FinancialJournalEntry;
use App\Models\LogisticsCollectionPoint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnCase;
use App\Models\ReturnEvidence;
use App\Models\ReturnInspection;
use App\Models\ReturnItem;
use App\Models\RetailerSupplierPayoutAllocation;
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
        array $evidencePaths = [],
        ?User $adminOverrideActor = null,
        ?string $adminOverrideReason = null
    ): array {
        return DB::transaction(function () use ($customer, $order, $itemsData, $reason, $customerDescription, $collectionPointId, $evidencePaths, $adminOverrideActor, $adminOverrideReason) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (!$lockedOrder) {
                return ['success' => false, 'message' => 'Order not found under lock.'];
            }

            // 1. Customer Ownership Check (Zero IDOR)
            if ((int)$lockedOrder->customer_id !== (int)$customer->id) {
                return ['success' => false, 'message' => 'Unauthorized: This order does not belong to your account.'];
            }

            // 2. Order Delivered Status Guard
            // Strict Delivered Status Guard (Finding 1)
            if ($lockedOrder->order_status !== 'delivered') {
                return ['success' => false, 'message' => "Order cannot be returned (current status: {$lockedOrder->order_status}). Only delivered orders are eligible for return."];
            }

            // 3. Configurable Refund Window Guard (SystemSetting)
            $refundHours = max(1, (int) SystemSetting::get('refund_window_hours', 24));
            $windowExpiresAt = $lockedOrder->refund_window_expires_at;

            if (!$windowExpiresAt && $lockedOrder->received_at) {
                $windowExpiresAt = $lockedOrder->received_at->copy()->addHours($refundHours);
            } elseif (!$windowExpiresAt && $lockedOrder->delivered_at) {
                $windowExpiresAt = $lockedOrder->delivered_at->copy()->addHours($refundHours);
            } elseif (!$windowExpiresAt && $lockedOrder->updated_at) {
                $windowExpiresAt = $lockedOrder->updated_at->copy()->addHours($refundHours);
            }

            $isAuthorizedLateOverride = $adminOverrideActor
                && $adminOverrideActor->status === 'active'
                && ($adminOverrideActor->isSuperAdmin() || $adminOverrideActor->user_type === 'admin_employee')
                && is_string($adminOverrideReason)
                && mb_strlen(trim($adminOverrideReason)) >= 10;

            if ($windowExpiresAt && Carbon::now()->gt($windowExpiresAt) && !$isAuthorizedLateOverride) {
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

            // 4a. Aggregate and Deduplicate Items Payload (Finding 2)
            $aggregatedItems = [];
            foreach ($itemsData as $itemInput) {
                $orderItemId = (int) ($itemInput['order_item_id'] ?? 0);
                $quantity = max(1, (int) ($itemInput['quantity'] ?? 1));
                $condition = (string) ($itemInput['item_condition'] ?? 'opened_tested');

                if ($orderItemId <= 0) {
                    return ['success' => false, 'message' => 'Invalid order_item_id specified in return items.'];
                }

                if (isset($aggregatedItems[$orderItemId])) {
                    $aggregatedItems[$orderItemId]['quantity'] += $quantity;
                } else {
                    $aggregatedItems[$orderItemId] = [
                        'order_item_id' => $orderItemId,
                        'quantity' => $quantity,
                        'item_condition' => $condition,
                    ];
                }
            }

            $totalRefundableAmount = '0.00';
            $validatedItems = [];

            foreach ($aggregatedItems as $orderItemId => $agg) {
                $quantity = $agg['quantity'];
                $condition = $agg['item_condition'];

                $orderItem = OrderItem::where('id', $orderItemId)
                    ->where('order_id', $lockedOrder->id)
                    ->first();

                if (!$orderItem) {
                    return ['success' => false, 'message' => "Order item #{$orderItemId} does not belong to this order."];
                }

                if (RetailerSupplierPayoutAllocation::where('order_item_id', $orderItem->id)->where('status', 'allocated')->exists()) {
                    return ['success' => false, 'code' => 'SUPPLIER_PAYOUT_RESERVED', 'message' => 'A supplier payout reservation is active for this item.'];
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

                // Allocate proportional discount if order used cashback discount (Finding 8)
                $allocatedDiscount = '0.00';
                if (!empty($lockedOrder->cashback_redeemed) && bccomp((string)$lockedOrder->merchandise_subtotal, '0.00', 2) > 0) {
                    $lineShare = bcdiv($itemSubtotal, (string)$lockedOrder->merchandise_subtotal, 6);
                    $allocatedDiscount = bcmul((string)$lockedOrder->cashback_redeemed, $lineShare, 2);
                }
                $refundableAmount = bcsub($itemSubtotal, $allocatedDiscount, 2);
                if (bccomp($refundableAmount, '0.00', 2) < 0) {
                    $refundableAmount = '0.00';
                }

                $totalRefundableAmount = bcadd($totalRefundableAmount, $refundableAmount, 2);

                $validatedItems[] = [
                    'order_item' => $orderItem,
                    'quantity' => $quantity,
                    'unit_price' => $orderItem->unit_price,
                    'line_total' => $itemSubtotal,
                    'allocated_discount' => $allocatedDiscount,
                    'refundable_amount' => $refundableAmount,
                    'item_condition' => $condition,
                ];
            }

            // 5. Create Sovereign Return Case
            $returnCase = ReturnCase::create([
                'case_number' => ReturnCase::generateCaseNumber(),
                'customer_id' => $customer->id,
                'order_id' => $lockedOrder->id,
                'parent_order_id' => $lockedOrder->parent_order_id,
                'vendor_id' => $lockedOrder->retailer_model_order ? null : $lockedOrder->vendor_id,
                'collection_point_id' => $collectionPointId,
                'return_reason' => $reason,
                'customer_description' => $customerDescription,
                'status' => 'requested',
                'source' => ($windowExpiresAt && Carbon::now()->gt($windowExpiresAt) && $isAuthorizedLateOverride) ? 'admin_late_override' : 'post_delivery_claim',
                'admin_notes' => ($windowExpiresAt && Carbon::now()->gt($windowExpiresAt) && $isAuthorizedLateOverride) ? trim($adminOverrideReason) : null,
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
                $rawPath = is_array($ev) ? ($ev['path'] ?? '') : (string)$ev;
                if (empty($rawPath) || str_contains($rawPath, '..') || str_starts_with($rawPath, '/') || str_starts_with($rawPath, '\\')) {
                    continue; // Skip directory traversal / local paths
                }

                // Enforce allowed upload directory prefix
                $allowedPrefixes = ['evidence/', 'returns/', 'photos/', 'storage/'];
                $isAllowedPrefix = false;
                foreach ($allowedPrefixes as $prefix) {
                    if (str_starts_with($rawPath, $prefix)) {
                        $isAllowedPrefix = true;
                        break;
                    }
                }
                if (!$isAllowedPrefix) {
                    continue;
                }

                ReturnEvidence::create([
                    'return_case_id' => $returnCase->id,
                    'uploaded_by_id' => $customer->id,
                    'uploaded_by_role' => 'customer',
                    'file_path' => $rawPath,
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

            if ($windowExpiresAt && Carbon::now()->gt($windowExpiresAt) && $isAuthorizedLateOverride) {
                AdminAuditLog::create([
                    'user_id' => $adminOverrideActor->id,
                    'action' => 'return_case_opened_after_window_by_admin',
                    'target_type' => ReturnCase::class,
                    'target_id' => $returnCase->id,
                    'old_values' => [
                        'window_expires_at' => $windowExpiresAt->toDateTimeString(),
                        'standard_window_hours' => $refundHours,
                    ],
                    'new_values' => [
                        'case_number' => $returnCase->case_number,
                        'order_id' => $lockedOrder->id,
                        'override_reason' => trim($adminOverrideReason),
                        'status' => 'requested',
                    ],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent(),
                ]);
            }

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

            // Whitelist allowed initial statuses for inspection (Finding 4)
            $allowedInspectionStatuses = ['requested', 'pending', 'in_transit', 'in_transit_back_to_vendor'];
            if (!in_array($lockedCase->status, $allowedInspectionStatuses)) {
                return [
                    'success' => false,
                    'message' => "Return case #{$lockedCase->case_number} cannot be inspected because it is already in '{$lockedCase->status}' status.",
                ];
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
    public function adminApproveReturn(
        User $adminUser,
        ReturnCase $case,
        ?string $adminNotes = null,
        ?string $faultRuling = null
    ): array {
        if ($adminUser->status !== 'active' || (!$adminUser->isSuperAdmin() && $adminUser->user_type !== 'admin_employee')) {
            return ['success' => false, 'message' => 'Unauthorized: Only administrative staff can review return cases.'];
        }

        return DB::transaction(function () use ($adminUser, $case, $adminNotes, $faultRuling) {
            $lockedCase = ReturnCase::where('id', $case->id)->lockForUpdate()->first();

            if (!in_array($lockedCase->status, ['requested', 'under_review', 'received_at_hub', 'inspected', 'returned_to_vendor', 'resolved'])) {
                return ['success' => false, 'message' => "Cannot approve return case in '{$lockedCase->status}' status."];
            }

            if ($faultRuling) {
                $lockedCase->admin_ruling = $faultRuling;
                if ($faultRuling === 'customer_remorse') {
                    $lockedCase->cost_allocation = 'customer_100';
                } elseif ($faultRuling === 'vendor_fault') {
                    $lockedCase->cost_allocation = 'vendor_100';
                } elseif ($faultRuling === 'logistics_fault') {
                    $lockedCase->cost_allocation = 'logistics_fleet_100';
                }
            }

            // This action explicitly approves a cash refund, even if the
            // customer originally requested replacement/repair. Persist the chosen
            // financial resolution instead of inheriting an ambiguous request type.
            $lockedCase->resolution_type = 'refund';
            $lockedCase->status = 'refund_pending';
            $lockedCase->reviewed_by_admin_id = $adminUser->id;
            $lockedCase->admin_notes = $adminNotes;
            $lockedCase->save();

            // Automatically revoke reviews for all items in the approved return case
            foreach ($lockedCase->items()->get() as $rItem) {
                app(\App\Services\ReviewService::class)->revokeReviewsForOrder(
                    $lockedCase->order_id,
                    $rItem->product_id,
                    'Item return approved for refund'
                );
            }

            ReturnItem::where('return_case_id', $lockedCase->id)
                ->where('status', '!=', 'inspected_rejected')
                ->update(['status' => 'approved']);

            AdminAuditLog::create([
                'user_id' => $adminUser->id,
                'action' => 'return_case_approved_for_refund',
                'target_type' => ReturnCase::class,
                'target_id' => $lockedCase->id,
                'old_values' => [
                    'status' => $case->status,
                    'resolution_type' => $case->resolution_type,
                ],
                'new_values' => [
                    'status' => 'refund_pending',
                    'resolution_type' => 'refund',
                    'reviewed_by_admin_id' => $adminUser->id,
                ],
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
     * Reject a return case only from a reviewable, non-financially-terminal state.
     * Lock and audit the transition so rejection cannot race an approval/refund.
     */
    public function adminRejectReturn(User $adminUser, ReturnCase $case, string $reason): array
    {
        if ($adminUser->status !== 'active' || (!$adminUser->isSuperAdmin() && $adminUser->user_type !== 'admin_employee')) {
            return ['success' => false, 'message' => 'Unauthorized: Only administrative staff can reject return cases.'];
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            return ['success' => false, 'message' => 'A rejection reason between 3 and 500 characters is required.'];
        }

        return DB::transaction(function () use ($adminUser, $case, $reason) {
            $lockedCase = ReturnCase::where('id', $case->id)->lockForUpdate()->first();
            if (!$lockedCase) {
                return ['success' => false, 'message' => 'Return case not found.'];
            }

            $allowedSourceStates = ['requested', 'under_review', 'received_at_hub', 'inspected', 'returned_to_vendor'];
            if (!in_array($lockedCase->status, $allowedSourceStates, true)) {
                return [
                    'success' => false,
                    'message' => "Cannot reject return case in '{$lockedCase->status}' status. Only a case still under review can be rejected.",
                ];
            }

            $oldStatus = $lockedCase->status;
            $lockedCase->status = 'rejected';
            $lockedCase->resolution_type = 'rejected';
            $lockedCase->reviewed_by_admin_id = $adminUser->id;
            $lockedCase->admin_notes = $reason;
            $lockedCase->refund_claim_token = null;
            $lockedCase->refund_claimed_at = null;
            $lockedCase->save();

            ReturnItem::where('return_case_id', $lockedCase->id)
                ->whereNotIn('status', ['refunded', 'refund_pending'])
                ->update(['status' => 'rejected']);

            AdminAuditLog::create([
                'user_id' => $adminUser->id,
                'action' => 'return_case_rejected',
                'target_type' => ReturnCase::class,
                'target_id' => $lockedCase->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'rejected', 'admin_notes' => $reason],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent(),
            ]);

            return ['success' => true, 'message' => "Return Case #{$lockedCase->case_number} has been rejected.", 'case' => $lockedCase];
        });
    }

    /**
     * Authoritative Super Admin Manual Refund Disbursement.
     * Enforces strict double-entry balancing, reversals of 90/5/5 merchandise cuts,
     * cashback reward cancellation, and Paystack refunds to the verified original transaction.
     */
    public function adminDisburseRefund(
        User $adminUser,
        ReturnCase $case,
        ?string $bankReference = null,
        ?string $proofPath = null,
        bool $refundDeliveryFee = false
    ): array {
        if ($adminUser->status !== 'active' || !$adminUser->isSuperAdmin()) {
            return ['success' => false, 'message' => 'Unauthorized: Only Super Admin can disburse treasury refunds.'];
        }

        // Launch policy: customer refunds must return through the verified original
        // Paystack transaction. Manual bank transfers and wallet credits are disabled
        // at the service boundary, even if a caller supplies a reference or proof file.
        if ($bankReference !== null && trim($bankReference) !== '') {
            return [
                'success' => false,
                'status' => 'disabled',
                'message' => 'Manual bank and wallet refunds are disabled at launch. Use the authorized Paystack refund workflow for the original payment.',
            ];
        }

        // 1. Initial State Validation & Pre-disbursement Lock
        $caseId = $case->id;
        $claimToken = 'CLAIM-' . $caseId . '-' . \Illuminate\Support\Str::random(16);

        $preValidation = DB::transaction(function () use ($caseId, $adminUser, $refundDeliveryFee, $claimToken) {
            $lockedCase = ReturnCase::where('id', $caseId)->lockForUpdate()->firstOrFail();
            $order = Order::where('id', $lockedCase->order_id)->lockForUpdate()->firstOrFail();

            // Strict Financial Approval State Machine: requires prior explicit approval
            if ($lockedCase->status !== 'refund_pending'
                || $lockedCase->resolution_type !== 'refund'
                || !$lockedCase->reviewed_by_admin_id) {
                return [
                    'valid' => false,
                    'message' => "Cannot disburse refund unless the case is explicitly approved with resolution_type=refund and status=refund_pending by an administrator.",
                ];
            }

            // Concurrency Race Guard: check for active unexpired claim from another worker/admin
            if (!empty($lockedCase->refund_claim_token) && $lockedCase->refund_claimed_at && $lockedCase->refund_claimed_at->gt(Carbon::now()->subMinutes(5))) {
                return [
                    'valid' => false,
                    'message' => "Refund operation is currently being processed by another worker or administrator. Please wait for settlement confirmation.",
                ];
            }

            // Once a Paystack refund has been recorded, its component scope must
            // remain immutable until reconciliation; changing this flag could make
            // settlement accounting allocate a component the gateway did not refund.
            $requestedDeliveryFeeAuthorization = $refundDeliveryFee
                && bccomp((string) $order->delivery_fee, '0.00', 2) > 0;
            $refundOperationExists = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
                ->where('metadata->return_case_id', $lockedCase->id)
                ->exists();

            if ((!empty($lockedCase->paystack_refund_id) || $refundOperationExists)
                && (bool) $lockedCase->refund_delivery_fee !== $requestedDeliveryFeeAuthorization) {
                return [
                    'valid' => false,
                    'message' => 'Refund component scope is immutable once a financial refund operation exists. Reconcile the existing operation or use an audited finance resolution before changing component treatment.',
                ];
            }

            // Calculate and persist the separate component authorization before
            // creating the FinancialOperation. The delivery fee is not marked as
            // refunded until verified gateway settlement is finalized.
            $merchandiseRefund = (string) $lockedCase->total_refundable_amount;
            $deliveryFeeAuthorized = $refundDeliveryFee
                && bccomp((string) $order->delivery_fee, '0.00', 2) > 0;
            $deliveryFeeRefund = $deliveryFeeAuthorized ? (string) $order->delivery_fee : '0.00';

            $totalCashRefund = bcadd($merchandiseRefund, $deliveryFeeRefund, 2);
            if ($order->payment_status !== 'paid'
                || trim((string) $order->payment_reference) === '') {
                return [
                    'valid' => false,
                    'message' => 'Refund is blocked because the order has no verified paid status and original payment reference. Finance reconciliation is required; no refund was posted.',
                ];
            }
            if (bccomp($totalCashRefund, '0.00', 2) <= 0) {
                return [
                    'valid' => false,
                    'message' => 'Refund amount must be greater than zero. No refund operation or settlement journal was created.',
                ];
            }

            // Lock every wallet-credit lot for this return case. A spend and a
            // cash-refund initiation must serialize so the customer cannot spend an
            // instant credit while a Paystack refund is pending.
            $walletCredits = CustomerWalletCredit::where('return_case_id', $lockedCase->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($walletCredits->contains(fn (CustomerWalletCredit $credit) => in_array($credit->status, ['redeemed', 'spent'], true))) {
                return [
                    'valid' => false,
                    'message' => 'Cannot disburse a cash refund because a wallet credit for this return case has already been redeemed or spent.',
                ];
            }

            $walletCreditStatuses = [];
            foreach ($walletCredits as $walletCredit) {
                if (in_array($walletCredit->status, ['available', 'pending_approval'], true)) {
                    $walletCreditStatuses[(int) $walletCredit->id] = $walletCredit->status;
                    $walletCredit->refund_reserved_from_status = $walletCredit->status;
                    $walletCredit->status = 'quarantined';
                    $walletCredit->notes = trim(($walletCredit->notes ? $walletCredit->notes . ' | ' : '')
                        . 'Cash refund reserved; credit unavailable pending authoritative Paystack outcome.');
                    $walletCredit->save();
                }
            }

            // Atomically acquire claim token, quarantine spendable credit and persist
            // the approved component under the same database transaction.
            $lockedCase->refund_claim_token = $claimToken;
            $lockedCase->refund_claimed_at = Carbon::now();
            $lockedCase->refund_delivery_fee = $deliveryFeeAuthorized;
            $lockedCase->save();

            return [
                'valid' => true,
                'order_id' => $order->id,
                'order_payment_reference' => $order->payment_reference,
                'case_number' => $lockedCase->case_number,
                'existing_paystack_refund_id' => $lockedCase->paystack_refund_id,
                'total_cash_refund' => $totalCashRefund,
                'merchandise_refund' => $merchandiseRefund,
                'delivery_fee_refund' => $deliveryFeeRefund,
                'refund_delivery_fee_authorized' => $deliveryFeeAuthorized,
                'wallet_credit_statuses' => $walletCreditStatuses,
            ];
        });

        if (!$preValidation['valid']) {
            return ['success' => false, 'message' => $preValidation['message']];
        }

        $totalCashRefund = $preValidation['total_cash_refund'];
        $paystackRefundId = $preValidation['existing_paystack_refund_id'];
        $walletCreditStatuses = $preValidation['wallet_credit_statuses'] ?? [];
        $dispatchAttempted = false;

        // A supplied bank reference means the administrator is recording an already
        // executed manual bank transfer. Do not also initiate a Paystack refund.
        // If a Paystack refund was already created for this case, do not let a manual
        // reference bypass its pending state and settle a second refund.
        if (!empty($bankReference) && !empty($paystackRefundId)) {
            ReturnCase::where('id', $caseId)->update([
                'refund_claim_token' => null,
                'refund_claimed_at' => null,
            ]);

            return [
                'success' => false,
                'status' => 'reconciliation_required',
                'message' => 'A Paystack refund is already recorded for this return case. Reconcile its gateway status before recording a separate bank or wallet refund.',
            ];
        }

        $gatewayJournalAlreadyPosted = false;

        // Reuse a live operation for reconciliation, never issue a second gateway
        // refund while its outcome is open. A new idempotency key is permitted only
        // after every prior attempt for this case is definitively failed.
        $priorOperations = \App\Models\FinancialOperation::where('operation_type', 'customer_refund')
            ->where('metadata->return_case_id', $caseId)
            ->orderBy('id')
            ->get();
        if ($priorOperations->contains(fn ($operation) => in_array($operation->status, ['confirmed', 'reversed'], true))) {
            ReturnCase::where('id', $caseId)->where('refund_claim_token', $claimToken)->update([
                'refund_claim_token' => null,
                'refund_claimed_at' => null,
            ]);
            return [
                'success' => false,
                'status' => 'reconciliation_required',
                'message' => 'A prior refund attempt is confirmed or reversed. Finance must reconcile its journal and gateway evidence before another refund can be initiated.',
            ];
        }
        $openOperation = $priorOperations->first(fn ($operation) => !in_array($operation->status, ['failed', 'confirmed', 'reversed'], true));
        if ($openOperation) {
            ReturnCase::where('id', $caseId)->where('refund_claim_token', $claimToken)->update([
                'refund_claim_token' => null,
                'refund_claimed_at' => null,
            ]);
            return [
                'success' => true,
                'status' => 'initiated',
                'message' => 'An earlier refund operation is still open. No second Paystack request was sent; reconcile the existing operation.',
                'case' => ReturnCase::find($caseId),
                'total_refunded' => $totalCashRefund,
            ];
        }

        // A persisted refund reference with no matching operation is an orphan and
        // cannot be safely retried. If all linked operations are definitively failed,
        // clear only the stale reference so a new attempt can be created.
        if (!empty($paystackRefundId)) {
            if ($priorOperations->isEmpty() || $priorOperations->contains(fn ($operation) => $operation->status !== 'failed')) {
                ReturnCase::where('id', $caseId)->where('refund_claim_token', $claimToken)->update([
                    'refund_claim_token' => null,
                    'refund_claimed_at' => null,
                ]);
                return [
                    'success' => true,
                    'status' => 'initiated',
                    'message' => 'A Paystack refund reference remains without a definitively failed linked operation. It is held for reconciliation; no second request was sent.',
                    'case' => ReturnCase::find($caseId),
                    'total_refunded' => $totalCashRefund,
                ];
            }
            ReturnCase::where('id', $caseId)->where('refund_claim_token', $claimToken)->update([
                'paystack_refund_id' => null,
            ]);
            $paystackRefundId = null;
        }

        $retryIdempotencyKey = $priorOperations->isNotEmpty()
            ? 'REFUND-CASE-' . $caseId . '-ATTEMPT-' . ($priorOperations->count() + 1)
            : null;

        // 2. Only dispatch to Paystack when no manual bank reference was supplied.
        if (empty($bankReference) && empty($paystackRefundId) && !empty($preValidation['order_payment_reference'])) {
            $finOpService = new \App\Services\FinancialOperationService($this->paystackService);
            $order = Order::findOrFail($preValidation['order_id']);
            $case = ReturnCase::findOrFail($caseId);

            try {
                $op = $finOpService->createRefundOperation(
                    $order,
                    $totalCashRefund,
                    "Manual refund for Return Case #{$preValidation['case_number']}",
                    $case,
                    $retryIdempotencyKey,
                    [
                        'type' => 'admin_approved_return',
                        'approved_by_user_id' => (int) $case->reviewed_by_admin_id,
                        'refund_delivery_fee' => (bool) $preValidation['refund_delivery_fee_authorized'],
                    ]
                );

                $dispatchAttempted = true;
                $dispatchRes = $finOpService->dispatchRefund($op);

                if (empty($dispatchRes['success'])) {
                    $dispatchStatus = (string) ($dispatchRes['status'] ?? '');
                    // Restore wallet credits only for an authoritative terminal
                    // gateway failure. An initiated/uncertain outcome keeps them
                    // quarantined until reconciliation.
                    if ($dispatchStatus === 'failed') {
                        $this->restoreWalletCreditsAfterDefinitiveFailure($walletCreditStatuses);
                    }
                    ReturnCase::where('id', $caseId)->where('refund_claim_token', $claimToken)->update([
                        'refund_claim_token' => null,
                        'refund_claimed_at' => null,
                    ]);

                    if ($dispatchStatus === 'initiated') {
                        return [
                            'success' => true,
                            'status' => 'initiated',
                            'message' => 'Paystack refund outcome is uncertain. No second refund request was sent; wallet credits remain quarantined and finance reconciliation is required before retry.',
                            'case' => ReturnCase::find($caseId),
                            'total_refunded' => $totalCashRefund,
                        ];
                    }

                    return [
                        'success' => false,
                        'status' => $dispatchStatus ?: 'failed',
                        'message' => 'Paystack gateway refund failed: ' . ($dispatchRes['message'] ?? 'Payment provider rejected.'),
                    ];
                }

                $paystackRefundId = $dispatchRes['refund_reference'] ?? null;
                ReturnCase::where('id', $caseId)->update(['paystack_refund_id' => $paystackRefundId]);

                if (($dispatchRes['status'] ?? '') === 'confirmed' || !empty($bankReference)) {
                    // FinancialOperationService is the sole journal writer for a confirmed gateway refund.
                    if (empty($bankReference) && ($dispatchRes['status'] ?? '') === 'confirmed') {
                        $gatewayJournalAlreadyPosted = true;
                    }
                } else {
                    // Initiated / Pending asynchronous bank confirmation
                    ReturnCase::where('id', $caseId)->update([
                        'refund_claim_token' => null,
                        'refund_claimed_at' => null,
                    ]);

                    return [
                        'success' => true,
                        'status' => 'initiated',
                        'message' => "Refund of ₦" . number_format((float)$totalCashRefund, 2) . " queued with Paystack ({$paystackRefundId}). Awaiting gateway settlement confirmation.",
                        'case' => $case->fresh(),
                        'total_refunded' => $totalCashRefund,
                    ];
                }
            } catch (\Throwable $e) {
                if (!$dispatchAttempted) {
                    $this->restoreWalletCreditsAfterDefinitiveFailure($walletCreditStatuses);
                }
                ReturnCase::where('id', $caseId)->where('refund_claim_token', $claimToken)->update([
                    'refund_claim_token' => null,
                    'refund_claimed_at' => null,
                ]);
                return [
                    'success' => false,
                    'message' => 'Refund operation error: ' . $e->getMessage(),
                ];
            }
        }

        // Launch policy disables manual bank and wallet refunds. Therefore this
        // settlement branch is reachable only after FinancialOperationService has
        // confirmed the Paystack refund and posted its gateway journal. Never fall
        // through to synthetic cash-out/refund entries when dispatch was skipped,
        // pending, or otherwise unconfirmed.
        if (!$gatewayJournalAlreadyPosted) {
            ReturnCase::where('id', $caseId)->where('refund_claim_token', $claimToken)->update([
                'refund_claim_token' => null,
                'refund_claimed_at' => null,
            ]);

            return [
                'success' => false,
                'status' => 'reconciliation_required',
                'message' => 'Refund settlement is blocked because confirmed Paystack settlement evidence is missing. No refund settlement journal was posted.',
            ];
        }

        // 3. Atomic Financial Journal Entries & State Settlement
        return DB::transaction(function () use (
            $adminUser,
            $caseId,
            $bankReference,
            $proofPath,
            $refundDeliveryFee,
            $paystackRefundId,
            $totalCashRefund,
            $gatewayJournalAlreadyPosted
        ) {
            $lockedCase = ReturnCase::where('id', $caseId)->lockForUpdate()->firstOrFail();
            $order = Order::where('id', $lockedCase->order_id)->lockForUpdate()->firstOrFail();
            $hasAuthorisedVendorFault = $lockedCase->admin_ruling === 'uphold_customer_claim'
                && $lockedCase->cost_allocation === 'vendor_100'
                && !empty($lockedCase->reviewed_by_admin_id)
                && !empty($lockedCase->admin_ruled_at);

            $walletCredit = CustomerWalletCredit::where('return_case_id', $lockedCase->id)
                ->whereIn('status', ['available', 'pending_approval'])
                ->first();

            $merchandiseRefund = $lockedCase->total_refundable_amount;
            $deliveryFeeRefund = ($refundDeliveryFee && bccomp((string)$order->delivery_fee, '0.00', 2) > 0)
                ? (string)$order->delivery_fee
                : '0.00';

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
            $refToStore = $bankReference ?: ($paystackRefundId ?: $disburseRef);
            $isWalletDisbursement = ($bankReference === 'wallet');

            if ($isWalletDisbursement) {
                if ($walletCredit) {
                    $walletCredit->status = 'available';
                    $walletCredit->notes = ($walletCredit->notes ? $walletCredit->notes . ' | ' : '') . 'Approved and activated by admin.';
                    $walletCredit->save();
                } else {
                    CustomerWalletCredit::create([
                        'customer_id' => $order->customer_id,
                        'order_id' => $order->id,
                        'return_case_id' => $lockedCase->id,
                        'amount' => $totalCashRefund,
                        'credit_type' => 'doorstep_refund_credit',
                        'status' => 'available',
                        'notes' => "Admin approved refund wallet credit for Return Case #{$lockedCase->case_number}",
                    ]);
                }

                if (!$gatewayJournalAlreadyPosted) {
                    FinancialJournalEntry::create([
                    'order_id' => $order->id,
                    'transaction_reference' => $refToStore,
                    'event_id' => 'EVT-REFUND-WALLET-' . $disburseRef,
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => $order->customer_id,
                    'account_name' => 'customer_wallet_liability',
                    'entry_type' => 'credit',
                    'amount' => $totalCashRefund,
                    'currency' => 'NGN',
                    'description' => "Wallet credit refund disbursement to customer for Return Case #{$lockedCase->case_number}",
                ]);
                }

                if ($order->customer) {
                    \App\Services\NotificationDispatchService::dispatchCustomerWalletCredited(
                        $order->customer,
                        $totalCashRefund,
                        "Refund approved for Return #{$lockedCase->case_number}"
                    );
                }
            } else {
                // Reverse available or pending instant wallet credit to prevent duplicate cash payout
                if ($walletCredit && in_array($walletCredit->status, ['available', 'pending_approval'])) {
                    $walletCredit->status = 'reversed_for_cash_refund';
                    $walletCredit->notes = ($walletCredit->notes ? $walletCredit->notes . ' | ' : '') . 'Reversed upon manual cash/bank refund disbursement.';
                    $walletCredit->save();
                }

                // 1. Post Double-Entry Journal Entries
                // Credit: Cash in Escrow (Exact Cash Outflow to Customer)
                if (!$gatewayJournalAlreadyPosted) {
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
                }
            }

            // Commit active financial reservation for this order
            \App\Models\FinancialReservation::where('source_type', Order::class)
                ->where('source_id', $order->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'committed',
                    'committed_at' => Carbon::now(),
                ]);

            if ($gatewayJournalAlreadyPosted) {
                // The verified FinancialOperationService finalizer already posted the
                // balanced gateway refund entries; do not duplicate them here.
            } elseif ($order->retailer_model_order) {
                app(\App\Services\RetailerRefundAccountingService::class)->reverse(
                    $order,
                    $lockedCase,
                    (string) $merchandiseRefund,
                    (string) $deliveryFeeRefund,
                    (string) $refToStore,
                    null,
                    $hasAuthorisedVendorFault
                );
            } else {
            // Reverse the vendor's merchandise share. A settled vendor becomes a receivable
            // only when a persisted admin ruling explicitly confirms vendor fault; otherwise the
            // platform absorbs the share as a tracked loss and does not create vendor debt.
            if ($order->vendor_settlement_status === 'settled' && $hasAuthorisedVendorFault) {
                $recovery = VendorRecoveryLedger::firstOrCreate(
                    ['idempotency_key' => "return-case-{$lockedCase->id}-merchandise-recovery"],
                    [
                        'vendor_id' => $order->vendor_id,
                        'return_case_id' => $lockedCase->id,
                        'order_id' => $order->id,
                        'amount' => $vendorNetDebit,
                        'status' => 'pending_recovery',
                        'notes' => "Authorised vendor-fault recovery for merchandise refund of Return #{$lockedCase->case_number}",
                    ]
                );

                if ($recovery->wasRecentlyCreated) {
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
                        'description' => "Authorised merchant recovery for post-settlement merchandise refund #{$lockedCase->case_number}",
                    ]);
                }
            } elseif ($order->vendor_settlement_status === 'settled') {
                FinancialJournalEntry::create([
                    'order_id' => $order->id,
                    'transaction_reference' => $refToStore,
                    'event_id' => 'EVT-REFUND-PLATFORM-LOSS-' . $disburseRef,
                    'beneficiary_type' => User::class,
                    'beneficiary_id' => null,
                    'account_name' => 'platform_refund_loss_pending_mapping',
                    'entry_type' => 'debit',
                    'amount' => $vendorNetDebit,
                    'currency' => 'NGN',
                    'description' => "Platform-funded refund absorbed vendor share because no authorised vendor-fault ruling supports a receivable; accountant mapping required for Return #{$lockedCase->case_number}",
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
                    'description' => "Merchant 90% net payable reversed for Return #{$lockedCase->case_number}",
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

                // Forfeit proportional pending maturation cashback reward in customer ledger
                $pendingCashbacks = CustomerCashbackLedger::where('order_id', $order->id)
                    ->where('status', 'pending_maturation')
                    ->lockForUpdate()
                    ->get();

                $remainingToForfeit = $customerCashbackDebit;
                foreach ($pendingCashbacks as $cb) {
                    if (bccomp($remainingToForfeit, '0.00', 2) <= 0) {
                        break;
                    }
                    if (bccomp($cb->amount, $remainingToForfeit, 2) <= 0) {
                        $remainingToForfeit = bcsub($remainingToForfeit, $cb->amount, 2);
                        $cb->update(['status' => 'forfeited']);
                    } else {
                        $retainedAmount = bcsub($cb->amount, $remainingToForfeit, 2);
                        $cb->update(['amount' => $retainedAmount]);
                        $remainingToForfeit = '0.00';
                    }
                }
            }

            // If delivery fee was refunded, reverse delivery fee allocation and platform delivery cut
            if (bccomp($deliveryFeeRefund, '0.00', 2) > 0) {
                $deliveryFeeCut = bcmul($deliveryFeeRefund, '0.9000', 2);
                $platDeliveryDebit = bcmul($deliveryFeeRefund, '0.1000', 2);

                if ($order->logistics_company_id && empty($order->rider?->vendor_id)) {
                    // 3rd-party logistics fleet
                    FinancialJournalEntry::create([
                        'order_id' => $order->id,
                        'transaction_reference' => $refToStore,
                        'event_id' => 'EVT-REFUND-FLEET-' . $disburseRef,
                        'beneficiary_type' => \App\Models\LogisticsCompanyProfile::class,
                        'beneficiary_id' => $order->logistics_company_id,
                        'account_name' => 'logistics_payable',
                        'entry_type' => 'debit',
                        'amount' => $deliveryFeeCut,
                        'currency' => 'NGN',
                        'description' => "Logistics fee reversed for Return #{$lockedCase->case_number}",
                    ]);
                } else {
                    // Delivery-fee recovery is 90% of the refunded fee. Do not create a
                    // vendor receivable after settlement unless the persisted ruling confirms fault.
                    if ($order->vendor_settlement_status === 'settled' && $hasAuthorisedVendorFault) {
                        $recovery = VendorRecoveryLedger::firstOrCreate(
                            ['idempotency_key' => "return-case-{$lockedCase->id}-delivery-fee-recovery"],
                            [
                                'vendor_id' => $order->vendor_id,
                                'return_case_id' => $lockedCase->id,
                                'order_id' => $order->id,
                                'amount' => $deliveryFeeCut,
                                'status' => 'pending_recovery',
                                'notes' => "Authorised vendor-fault recovery for 90% delivery-fee refund of Return #{$lockedCase->case_number}",
                            ]
                        );

                        if ($recovery->wasRecentlyCreated) {
                            FinancialJournalEntry::create([
                                'order_id' => $order->id,
                                'transaction_reference' => $refToStore,
                                'event_id' => 'EVT-REFUND-VND-DELIVERY-RECOVERY-' . $disburseRef,
                                'beneficiary_type' => VendorProfile::class,
                                'beneficiary_id' => $order->vendor_id,
                                'account_name' => 'vendor_recovery_receivable',
                                'entry_type' => 'debit',
                                'amount' => $deliveryFeeCut,
                                'currency' => 'NGN',
                                'description' => "Authorised merchant recovery for 90% delivery fee refund on Return #{$lockedCase->case_number}",
                            ]);
                        }
                    } elseif ($order->vendor_settlement_status === 'settled') {
                        FinancialJournalEntry::create([
                            'order_id' => $order->id,
                            'transaction_reference' => $refToStore,
                            'event_id' => 'EVT-REFUND-PLATFORM-DELIVERY-LOSS-' . $disburseRef,
                            'beneficiary_type' => User::class,
                            'beneficiary_id' => null,
                            'account_name' => 'platform_refund_loss_pending_mapping',
                            'entry_type' => 'debit',
                            'amount' => $deliveryFeeCut,
                            'currency' => 'NGN',
                            'description' => "Platform-funded delivery refund absorbed vendor share without authorised fault ruling; accountant mapping required for Return #{$lockedCase->case_number}",
                        ]);
                    } else {
                        FinancialJournalEntry::create([
                            'order_id' => $order->id,
                            'transaction_reference' => $refToStore,
                            'event_id' => 'EVT-REFUND-VND-DELIVERY-' . $disburseRef,
                            'beneficiary_type' => VendorProfile::class,
                            'beneficiary_id' => $order->vendor_id,
                            'account_name' => 'vendor_payable',
                            'entry_type' => 'debit',
                            'amount' => $deliveryFeeCut,
                            'currency' => 'NGN',
                            'description' => "Merchant delivery fee allocation reversed for Return #{$lockedCase->case_number}",
                        ]);
                    }
                }

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




            }

            // 3. Update Return Case State
            $lockedCase->status = 'refunded';
            $lockedCase->refunded_amount = $totalCashRefund;
            $lockedCase->refund_delivery_fee = $refundDeliveryFee;
            $lockedCase->delivery_fee_refunded = $deliveryFeeRefund;
            $lockedCase->paystack_refund_id = $paystackRefundId;
            $lockedCase->refund_claim_token = null;
            $lockedCase->refund_claimed_at = null;
            $lockedCase->bank_reference = $refToStore;
            $lockedCase->refund_proof_path = $proofPath;
            $lockedCase->refunded_at = Carbon::now();
            $lockedCase->save();

            ReturnItem::where('return_case_id', $lockedCase->id)->update(['status' => 'refunded']);

            // Automatically revoke customer reviews for all items in this refunded case
            foreach ($lockedCase->items()->get() as $rItem) {
                app(\App\Services\ReviewService::class)->revokeReviewsForOrder(
                    $lockedCase->order_id,
                    $rItem->product_id,
                    'Item return refunded'
                );
            }

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
    /**
     * Restore wallet-credit lots only when no gateway refund could still be in
     * flight. Ambiguous outcomes intentionally keep credits quarantined.
     */
    private function restoreWalletCreditsAfterDefinitiveFailure(array $walletCreditStatuses): void
    {
        if (!$walletCreditStatuses) {
            return;
        }

        DB::transaction(function () use ($walletCreditStatuses): void {
            foreach ($walletCreditStatuses as $creditId => $previousStatus) {
                if (!in_array($previousStatus, ['available', 'pending_approval'], true)) {
                    continue;
                }
                $credit = CustomerWalletCredit::whereKey((int) $creditId)
                    ->lockForUpdate()
                    ->first();
                if (!$credit || $credit->status !== 'quarantined') {
                    continue;
                }

                $restoreStatus = in_array($credit->refund_reserved_from_status, ['available', 'pending_approval'], true)
                    ? $credit->refund_reserved_from_status
                    : $previousStatus;
                $credit->status = $restoreStatus;
                $credit->refund_reserved_from_status = null;
                $credit->notes = trim(($credit->notes ? $credit->notes . ' | ' : '')
                    . 'Wallet credit restored after authoritative refund failure/no dispatch.');
                $credit->save();
            }
        }, 3);
    }

}
