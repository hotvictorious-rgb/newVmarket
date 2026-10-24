<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\FinancialJournalEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSupplierAssignment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SupplierReassignmentRecoveryService
{
    /**
     * Recover selected items from a rejected supplier on a paid retailer order.
     *
     * Capture-time supplier_* fields and original journal entries are immutable.
     * Recovery changes fulfillment_* assignment projections and appends balanced,
     * idempotent supplier-payable compensations. Customer-facing price, delivery,
     * and cashback fields are deliberately never modified here.
     */
    public function reassignRejectedItems(
        int $orderId,
        int $rejectedAssignmentId,
        int $newVendorId,
        int $newShopId,
        array $orderItemIds,
        ?User $admin,
        string $reason,
        string $idempotencyKey
    ): array {
        $isAutomatic = $admin === null;
        if ($isAutomatic) {
            // Null actor is reserved for the internal bounded failover entry point.
            // It is never accepted as an administrator override.
            if (!str_starts_with($idempotencyKey, 'auto-failover-')
                || !str_starts_with(trim($reason), 'Automatic supplier failover:')) {
                throw new InvalidArgumentException('System recovery requires the explicit automatic-failover identity and reason.');
            }
        } elseif ($admin->user_type !== 'super_admin' || $admin->status !== 'active') {
            throw new InvalidArgumentException('Only an active super administrator may resolve a paid supplier rejection.');
        }

        $auditAction = $isAutomatic ? 'automatic_supplier_failover_applied' : 'admin_recover_rejected_supplier_items';
        $reason = trim($reason);
        $idempotencyKey = trim($idempotencyKey);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw new InvalidArgumentException('A resolution reason of 1–2,000 characters is required.');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{8,80}$/', $idempotencyKey)) {
            throw new InvalidArgumentException('Idempotency key must be 8–80 letters, numbers, dots, underscores, colons or hyphens.');
        }

        $itemIds = array_values(array_unique(array_map('intval', $orderItemIds)));
        sort($itemIds);
        if (!$itemIds || count($itemIds) !== count($orderItemIds)) {
            throw new InvalidArgumentException('Select one or more distinct order items to reassign.');
        }

        $requestHash = hash('sha256', json_encode([
            'order_id' => $orderId,
            'rejected_assignment_id' => $rejectedAssignmentId,
            'new_vendor_id' => $newVendorId,
            'new_shop_id' => $newShopId,
            'order_item_ids' => $itemIds,
            'reason' => $reason,
        ], JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use (
            $orderId, $rejectedAssignmentId, $newVendorId, $newShopId,
            $itemIds, $admin, $reason, $idempotencyKey, $requestHash, $isAutomatic, $auditAction
        ) {
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();

            // The order lock serializes retries for the same order before the
            // idempotency audit lookup and all journal/assignment mutations.
            $prior = AdminAuditLog::where('target_type', Order::class)
                ->where('target_id', $order->id)
                ->where('action', $auditAction)
                ->latest('id')
                ->get()
                ->first(fn (AdminAuditLog $log) => ($log->new_values['idempotency_key'] ?? null) === $idempotencyKey);

            if ($prior) {
                if (($prior->new_values['request_hash'] ?? null) !== $requestHash) {
                    throw new InvalidArgumentException('This idempotency key was already used for a different supplier recovery instruction.');
                }

                return [
                    'success' => true,
                    'replayed' => true,
                    'order_id' => $order->id,
                    'replacement_assignment_id' => $prior->new_values['replacement_assignment_id'] ?? null,
                    'sourcing_status' => $prior->new_values['sourcing_status'] ?? $order->sourcing_status,
                    'message' => 'This supplier recovery instruction was already applied; no additional journal entries were created.',
                ];
            }

            $allowedSourcingStatuses = ['supplier_rejected', 'timed_out', 'stockout_revalidation_hold', 'counter_breach', 'awaiting_supplier_confirmation'];
            if (!$order->retailer_model_order || $order->payment_status !== 'paid'
                || $order->order_status !== 'confirmed' || !in_array($order->sourcing_status, $allowedSourcingStatuses, true)) {
                throw new InvalidArgumentException('Recovery is allowed only for paid retailer orders on an authorized sourcing hold.');
            }

            $rejectedAssignment = OrderSupplierAssignment::whereKey($rejectedAssignmentId)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($rejectedAssignment->status, $allowedSourcingStatuses, true)) {
                throw new InvalidArgumentException('The selected assignment is not on an authorized sourcing hold.');
            }
            if ((int) $rejectedAssignment->supplier_vendor_id === $newVendorId) {
                throw new InvalidArgumentException('Choose a different supplier account from the rejected assignment.');
            }

            $newVendor = VendorProfile::withTrashed()->with('user')->find($newVendorId);
            $newShop = Shop::withTrashed()->with(['lga.state', 'vendor.user'])->find($newShopId);
            if (!$newVendor || $newVendor->trashed() || $newVendor->marketplace_status !== 'active'
                || !$newVendor->user || $newVendor->user->status !== 'active'
                || !$newVendor->isKycVerified()) {
                throw new InvalidArgumentException('Replacement supplier account must be active and KYC-verified.');
            }
            if (!$newShop || $newShop->trashed() || !$newShop->is_active
                || (int) $newShop->vendor_id !== (int) $newVendor->id) {
                throw new InvalidArgumentException('Replacement shop must be active and belong to the replacement supplier.');
            }
            if (Shop::where('vendor_id', $newVendor->id)->whereNull('deleted_at')->where('is_active', true)->count() !== 1) {
                throw new InvalidArgumentException('A private supplier must have exactly one active shop/pickup point.');
            }
            if (strtolower(trim((string) ($newShop->lga->name ?? ''))) !== 'uyo'
                || strtolower(trim((string) ($newShop->lga->state->name ?? ''))) !== 'akwa ibom') {
                throw new InvalidArgumentException('Replacement supplier shops must be based in Uyo, Akwa Ibom State.');
            }

            $items = OrderItem::where('order_id', $order->id)
                ->whereIn('id', $itemIds)
                ->where('fulfillment_supplier_vendor_id', $rejectedAssignment->supplier_vendor_id)
                ->where('fulfillment_supplier_shop_id', $rejectedAssignment->supplier_shop_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->count() !== count($itemIds)) {
                throw new InvalidArgumentException('Every selected item must still belong to the rejected supplier assignment.');
            }

            $offers = [];
            $offerCheckAt = Carbon::now();
            foreach ($items as $item) {
                $offer = SupplierOffer::where('product_id', $item->product_id)
                    ->where('vendor_id', $newVendor->id)
                    ->where('shop_id', $newShop->id)
                    ->where('is_available', true)
                    ->where('is_admin_disabled', false)
                    ->where('wholesale_cost', '>', 0)
                    ->whereRaw('supplier_offers.wholesale_cost < (select products.unit_price from products where products.id = supplier_offers.product_id)')
                    ->where(function ($query) use ($offerCheckAt) {
                        $query->whereNull('unavailability_penalty_expires_at')
                            ->orWhere('unavailability_penalty_expires_at', '<=', $offerCheckAt);
                    })
                    ->where(function ($catalogLink) {
                        $catalogLink->whereNull('supplier_catalog_product_id')
                            ->orWhereHas('supplierCatalogProduct', function ($catalog) {
                                $catalog->whereNull('supplier_catalog_products.deleted_at')
                                    ->where('supplier_catalog_products.status', 'active')
                                    ->where('supplier_catalog_products.is_in_stock', true)
                                    ->whereColumn('supplier_catalog_products.canonical_product_id', 'supplier_offers.product_id')
                                    ->whereColumn('supplier_catalog_products.supplier_vendor_id', 'supplier_offers.vendor_id')
                                    ->whereColumn('supplier_catalog_products.supplier_shop_id', 'supplier_offers.shop_id')
                                    ->whereColumn('supplier_catalog_products.source_price', 'supplier_offers.wholesale_cost');
                            });
                    })
                    ->whereNotNull('availability_confirmed_at')
                    ->where('availability_confirmed_at', '<=', $offerCheckAt)
                    ->whereNotNull('availability_confirmed_by')
                    ->whereNotNull('availability_expires_at')
                    ->where('availability_expires_at', '>', $offerCheckAt)
                    ->lockForUpdate()
                    ->first();

                if (!$offer) {
                    throw new InvalidArgumentException("Replacement supplier has no fresh confirmed offer for {$item->product_name}.");
                }
                $offers[$item->id] = $offer;
            }

            // Before changing any assignment, prove that current operational costs
            // reconcile to the net supplier_payable ledger for both affected suppliers.
            $this->assertSupplierPayableReconciles($order, (int) $rejectedAssignment->supplier_vendor_id);
            $this->assertSupplierPayableReconciles($order, (int) $newVendor->id);

            $requestSuffix = substr($requestHash, 0, 16);
            $transactionReference = 'SUPREC-' . $order->id . '-' . $requestSuffix;
            $oldValues = [];
            $newValues = [];
            foreach ($items as $item) {
                $offer = $offers[$item->id];
                $oldVendorId = (int) $item->fulfillment_supplier_vendor_id;
                $oldShopId = (int) $item->fulfillment_supplier_shop_id;
                $oldUnitCost = (string) $item->fulfillment_supplier_cost_snapshot;
                $newUnitCost = BcMathService::add((string) $offer->wholesale_cost, '0.00');
                if (BcMathService::comp($oldUnitCost, '0.00') <= 0) {
                    throw new InvalidArgumentException("Item #{$item->id} has no valid operational supplier cost snapshot.");
                }

                $oldLineCost = BcMathService::mul($oldUnitCost, (string) $item->quantity);
                $newLineCost = BcMathService::mul($newUnitCost, (string) $item->quantity);
                $oldValues[] = [
                    'order_item_id' => $item->id,
                    'capture_supplier_vendor_id' => $item->supplier_vendor_id,
                    'capture_supplier_shop_id' => $item->supplier_shop_id,
                    'capture_supplier_cost_snapshot' => $item->supplier_cost_snapshot,
                    'old_fulfillment_supplier_vendor_id' => $oldVendorId,
                    'old_fulfillment_supplier_shop_id' => $oldShopId,
                    'old_fulfillment_supplier_cost_snapshot' => $oldUnitCost,
                    'old_fulfillment_line_cost' => $oldLineCost,
                ];
                $newValues[] = [
                    'order_item_id' => $item->id,
                    'replacement_offer_id' => $offer->id,
                    'replacement_supplier_vendor_id' => $newVendor->id,
                    'replacement_supplier_shop_id' => $newShop->id,
                    'replacement_unit_cost' => $newUnitCost,
                    'replacement_line_cost' => $newLineCost,
                ];

                $itemSuffix = $idempotencyKey . '-ITEM-' . $item->id;
                $this->postSupplierJournalEntry(
                    $order, (int) $item->id, $transactionReference, $itemSuffix . '-OLD',
                    $oldVendorId, 'debit', $oldLineCost,
                    "Reverse operational supplier payable for item #{$item->id} after approved recovery."
                );
                $this->postSupplierJournalEntry(
                    $order, (int) $item->id, $transactionReference, $itemSuffix . '-NEW',
                    (int) $newVendor->id, 'credit', $newLineCost,
                    "Replacement supplier payable for item #{$item->id} after approved recovery."
                );

                $variance = BcMathService::sub($newLineCost, $oldLineCost);
                if (BcMathService::comp($variance, '0.00') > 0) {
                    $this->postVarianceEntry($order, $transactionReference, $itemSuffix . '-VAR', 'debit', $variance, $item->id);
                } elseif (BcMathService::comp($variance, '0.00') < 0) {
                    $this->postVarianceEntry($order, $transactionReference, $itemSuffix . '-VAR', 'credit', BcMathService::mul($variance, '-1.00'), $item->id);
                }

                // Only operational fulfillment fields change. Capture-time supplier_*
                // fields and the original capture-time journal are preserved.
                $item->fulfillment_supplier_offer_id = $offer->id;
                $item->fulfillment_supplier_vendor_id = $newVendor->id;
                $item->fulfillment_supplier_shop_id = $newShop->id;
                $item->fulfillment_supplier_name_snapshot = $newVendor->business_name ?? $newVendor->user->name;
                $item->fulfillment_supplier_shop_name_snapshot = $newShop->name;
                $item->fulfillment_supplier_cost_snapshot = $newUnitCost;
                $item->fulfillment_supplier_snapshot_at = Carbon::now();
                $item->save();
            }

            $replacementAssignment = OrderSupplierAssignment::where('order_id', $order->id)
                ->where('supplier_vendor_id', $newVendor->id)
                ->where('supplier_shop_id', $newShop->id)
                ->lockForUpdate()
                ->first();

            if (!$replacementAssignment) {
                $replacementAssignment = OrderSupplierAssignment::create([
                    'order_id' => $order->id,
                    'supplier_vendor_id' => $newVendor->id,
                    'supplier_shop_id' => $newShop->id,
                    'status' => 'awaiting_supplier_confirmation',
                    'assigned_at' => Carbon::now(),
                    'admin_override_by' => $admin?->id,
                    'admin_override_reason' => $isAutomatic ? null : $reason,
                ]);
            } elseif ($replacementAssignment->status === 'admin_reassigned') {
                $replacementAssignment->status = 'awaiting_supplier_confirmation';
                $replacementAssignment->assigned_at = Carbon::now();
                $replacementAssignment->supplier_confirmed_at = null;
                $replacementAssignment->supplier_rejected_at = null;
                $replacementAssignment->supplier_response_notes = null;
                $replacementAssignment->confirmed_order_items = null;
                $replacementAssignment->confirmation_snapshot_hash = null;
                $replacementAssignment->supplier_confirmed_by_user_id = null;
                $replacementAssignment->supplier_pickup_code = null;
                $replacementAssignment->handover_ready_at = null;
                $replacementAssignment->collected_at = null;
                $replacementAssignment->admin_override_by = $admin?->id;
                $replacementAssignment->admin_override_reason = $isAutomatic ? null : $reason;
                $replacementAssignment->save();
            } elseif ($isAutomatic && in_array($replacementAssignment->status, ['supplier_confirmed', 'ready_for_collection'], true)
                && $replacementAssignment->collected_at === null) {
                // New lines joining a previously confirmed supplier group invalidate
                // the old group-level confirmation; require the supplier to confirm
                // the complete revised set of line quantities again.
                $replacementAssignment->status = 'awaiting_supplier_confirmation';
                $replacementAssignment->assigned_at = Carbon::now();
                $replacementAssignment->supplier_confirmed_at = null;
                $replacementAssignment->supplier_rejected_at = null;
                $replacementAssignment->supplier_response_notes = null;
                $replacementAssignment->confirmed_order_items = null;
                $replacementAssignment->confirmation_snapshot_hash = null;
                $replacementAssignment->supplier_confirmed_by_user_id = null;
                $replacementAssignment->supplier_pickup_code = null;
                $replacementAssignment->handover_ready_at = null;
                $replacementAssignment->admin_override_by = null;
                $replacementAssignment->admin_override_reason = null;
                $replacementAssignment->save();
            } elseif ($replacementAssignment->status !== 'awaiting_supplier_confirmation') {
                throw new InvalidArgumentException('Replacement supplier already has an assignment in a state that cannot safely absorb these items.');
            } else {
                $replacementAssignment->admin_override_by = $admin?->id;
                $replacementAssignment->admin_override_reason = $isAutomatic ? null : $reason;
                $replacementAssignment->save();
            }

            // Mark rejected/timed-out assignments with no remaining items as historical, not
            // active blockers. Keep the rows and rejection audit history intact.
            $rejectedAssignments = OrderSupplierAssignment::where('order_id', $order->id)
                ->whereIn('status', ['supplier_rejected', 'timed_out', 'stockout_revalidation_hold', 'counter_breach'])
                ->lockForUpdate()
                ->get();
            $hasUnresolvedRejectedItems = false;
            foreach ($rejectedAssignments as $rejected) {
                $stillAssigned = OrderItem::where('order_id', $order->id)
                    ->where('fulfillment_supplier_vendor_id', $rejected->supplier_vendor_id)
                    ->where('fulfillment_supplier_shop_id', $rejected->supplier_shop_id)
                    ->exists();
                if ($stillAssigned) {
                    $hasUnresolvedRejectedItems = true;
                } else {
                    $rejected->status = 'admin_reassigned';
                    $rejected->supplier_pickup_code = null;
                    $rejected->handover_ready_at = null;
                    $rejected->save();
                }
            }

            $operationalTotal = '0.00';
            foreach (OrderItem::where('order_id', $order->id)->orderBy('id')->get() as $item) {
                if ($item->fulfillment_supplier_cost_snapshot === null
                    || empty($item->fulfillment_supplier_vendor_id)
                    || empty($item->fulfillment_supplier_shop_id)) {
                    throw new InvalidArgumentException("Order item #{$item->id} is missing operational supplier evidence.");
                }
                $operationalTotal = BcMathService::add(
                    $operationalTotal,
                    BcMathService::mul((string) $item->fulfillment_supplier_cost_snapshot, (string) $item->quantity)
                );
            }

            $order->fulfillment_supplier_cost_total_snapshot = $operationalTotal;
            if ($hasUnresolvedRejectedItems) {
                $order->sourcing_status = 'supplier_rejected';
            } else {
                $hasConfirmedAssignment = OrderSupplierAssignment::where('order_id', $order->id)
                    ->whereIn('status', ['supplier_confirmed', 'ready_for_collection', 'collected'])
                    ->whereExists(function ($query) use ($order) {
                        $query->select(DB::raw(1))
                            ->from('order_items')
                            ->whereColumn('order_items.order_id', 'order_supplier_assignments.order_id')
                            ->whereColumn('order_items.fulfillment_supplier_vendor_id', 'order_supplier_assignments.supplier_vendor_id')
                            ->whereColumn('order_items.fulfillment_supplier_shop_id', 'order_supplier_assignments.supplier_shop_id')
                            ->where('order_items.order_id', $order->id);
                    })
                    ->exists();
                $order->sourcing_status = $hasConfirmedAssignment
                    ? 'partially_supplier_confirmed'
                    : 'awaiting_supplier_confirmation';
            }
            $order->order_status = 'confirmed';
            $order->vendor_handover_ready_at = null;
            $order->save();

            AdminAuditLog::create([
                'user_id' => $admin?->id,
                'action' => $auditAction,
                'target_type' => Order::class,
                'target_id' => $order->id,
                'old_values' => [
                    'assignment_id' => $rejectedAssignment->id,
                    'items' => $oldValues,
                    'sourcing_status' => 'supplier_rejected',
                ],
                'new_values' => [
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'replacement_assignment_id' => $replacementAssignment->id,
                    'items' => $newValues,
                    'supplier_vendor_id' => $newVendor->id,
                    'supplier_shop_id' => $newShop->id,
                    'order_item_ids' => $itemIds,
                    'reason' => $reason,
                    'sourcing_status' => $order->sourcing_status,
                    'operational_supplier_cost_total' => $operationalTotal,
                    'capture_supplier_cost_total_unchanged' => $order->supplier_cost_total_snapshot,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System/Recovery',
            ]);

            return [
                'success' => true,
                'replayed' => false,
                'order_id' => $order->id,
                'replacement_assignment_id' => $replacementAssignment->id,
                'sourcing_status' => $order->sourcing_status,
                'message' => $hasUnresolvedRejectedItems
                    ? 'Selected items were reassigned. The order remains on hold until every rejected supplier item is resolved.'
                    : 'Supplier recovery was recorded. The replacement supplier must confirm availability before collection can resume.',
            ];
        }, 3);
    }

    /**
     * Automatically attempt the next eligible supplier plan after a supplier
     * rejection or confirmation timeout. The existing recovery path performs
     * row locks, immutable-capture preservation and balanced compensating journals.
     * If no complete plan remains, keep the paid order held for Super Admin review.
     */
    public function attemptAutomaticFailover(int $orderId, int $failedAssignmentId): array
    {
        return DB::transaction(function () use ($orderId, $failedAssignmentId): array {
            $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
            $failed = OrderSupplierAssignment::whereKey($failedAssignmentId)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if (!$failed || !$order->retailer_model_order || $order->payment_status !== 'paid'
                || $order->order_status !== 'confirmed'
                || !in_array($failed->status, ['supplier_rejected', 'timed_out', 'stockout_revalidation_hold', 'counter_breach'], true)) {
                return ['success' => false, 'reason' => 'The paid order or failed assignment is no longer eligible for automatic failover.'];
            }

            $items = OrderItem::where('order_id', $order->id)
                ->where('fulfillment_supplier_vendor_id', $failed->supplier_vendor_id)
                ->where('fulfillment_supplier_shop_id', $failed->supplier_shop_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                return ['success' => true, 'replayed' => true, 'reassigned_groups' => 0, 'reason' => 'No order lines remain on the failed assignment.'];
            }

            $planItems = [];
            foreach ($items as $item) {
                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                if (!$product) {
                    return $this->recordAutomaticFailoverExhausted($order, $failed, 'A paid order line no longer has a canonical product record.');
                }
                $planItems[] = ['product' => $product, 'quantity' => (int) $item->quantity];
            }

            $plan = app(SourcingAllocationService::class)->resolveOptimalWholeOrderPlan(
                $planItems,
                (string) $order->fulfillment_type
            );

            if (!$plan) {
                return $this->recordAutomaticFailoverExhausted(
                    $order,
                    $failed,
                    'No complete eligible Uyo supplier plan remains for the failed assignment; admin decision required.'
                );
            }

            $groups = [];
            foreach (array_values($plan['items'] ?? []) as $index => $line) {
                $item = $items[$index] ?? null;
                $offer = $line['resolved_offer'] ?? [];
                if (!$item || empty($offer['supplier_offer_id']) || empty($offer['vendor_id']) || empty($offer['shop_id'])
                    || ((int) $offer['vendor_id'] === (int) $failed->supplier_vendor_id
                        && (int) $offer['shop_id'] === (int) $failed->supplier_shop_id)) {
                    return $this->recordAutomaticFailoverExhausted(
                        $order,
                        $failed,
                        'The next candidate plan was invalid or included the failed supplier; admin decision required.'
                    );
                }

                $key = (int) $offer['vendor_id'] . ':' . (int) $offer['shop_id'];
                $groups[$key]['vendor_id'] = (int) $offer['vendor_id'];
                $groups[$key]['shop_id'] = (int) $offer['shop_id'];
                $groups[$key]['item_ids'][] = (int) $item->id;
            }

            if (!$groups) {
                return $this->recordAutomaticFailoverExhausted($order, $failed, 'No alternative supplier candidate was selected.');
            }

            $reassignedGroups = 0;
            foreach ($groups as $group) {
                sort($group['item_ids']);
                $groupHash = substr(hash('sha256', implode(',', $group['item_ids']) . ':' . $group['vendor_id'] . ':' . $group['shop_id']), 0, 10);
                $key = 'auto-failover-' . $failed->id . '-' . $group['vendor_id'] . '-' . $group['shop_id'] . '-' . $groupHash;
                $reason = 'Automatic supplier failover: previous supplier declined or timed out; candidate selected by the approved eligible sourcing plan.';

                try {
                    $this->reassignRejectedItems(
                        $order->id,
                        $failed->id,
                        $group['vendor_id'],
                        $group['shop_id'],
                        $group['item_ids'],
                        null,
                        $reason,
                        $key
                    );
                    $reassignedGroups++;
                } catch (\Throwable $exception) {
                    AdminAuditLog::create([
                        'user_id' => null,
                        'action' => 'automatic_supplier_failover_attempt_failed',
                        'target_type' => Order::class,
                        'target_id' => $order->id,
                        'old_values' => [
                            'failed_assignment_id' => $failed->id,
                            'failed_supplier_vendor_id' => $failed->supplier_vendor_id,
                            'failed_supplier_shop_id' => $failed->supplier_shop_id,
                        ],
                        'new_values' => [
                            'idempotency_key' => $key,
                            'candidate_vendor_id' => $group['vendor_id'],
                            'candidate_shop_id' => $group['shop_id'],
                            'order_item_ids' => $group['item_ids'],
                            'reason' => 'Automatic failover attempt failed closed; admin reconciliation is required.',
                            'exception_class' => get_class($exception),
                            'partial_recovery' => $reassignedGroups > 0,
                            'reassigned_groups' => $reassignedGroups,
                            'admin_action_required' => true,
                            'refund_dispatched' => false,
                        ],
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'System/AutomaticSupplierFailover',
                    ]);
                    return [
                        'success' => false,
                        'partial' => $reassignedGroups > 0,
                        'reassigned_groups' => $reassignedGroups,
                        'reason' => 'Automatic failover could not safely complete every candidate group. The order remains held for admin reconciliation.',
                    ];
                }
            }

            return [
                'success' => true,
                'replayed' => false,
                'reassigned_groups' => $reassignedGroups,
                'reason' => 'Eligible replacement supplier assignment(s) created; order-line quantities must be confirmed by the replacement supplier(s).',
            ];
        }, 3);
    }

    private function recordAutomaticFailoverExhausted(Order $order, OrderSupplierAssignment $failed, string $reason): array
    {
        $key = 'auto-failover-exhausted-' . $failed->id;
        $alreadyRecorded = AdminAuditLog::where('target_type', Order::class)
            ->where('target_id', $order->id)
            ->where('action', 'automatic_supplier_failover_exhausted')
            ->get()
            ->contains(fn (AdminAuditLog $log) => ($log->new_values['idempotency_key'] ?? null) === $key);

        if (!$alreadyRecorded) {
            AdminAuditLog::create([
                'user_id' => null,
                'action' => 'automatic_supplier_failover_exhausted',
                'target_type' => Order::class,
                'target_id' => $order->id,
                'old_values' => [
                    'failed_assignment_id' => $failed->id,
                    'failed_supplier_vendor_id' => $failed->supplier_vendor_id,
                    'failed_supplier_shop_id' => $failed->supplier_shop_id,
                    'sourcing_status' => $order->sourcing_status,
                ],
                'new_values' => [
                    'idempotency_key' => $key,
                    'reason' => $reason,
                    'admin_action_required' => true,
                    'refund_dispatched' => false,
                ],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'System/AutomaticSupplierFailover',
            ]);
        }

        return ['success' => false, 'exhausted' => true, 'reassigned_groups' => 0, 'reason' => $reason];
    }

    private function assertSupplierPayableReconciles(Order $order, int $vendorId): void
    {
        $expected = '0.00';
        foreach (OrderItem::where('order_id', $order->id)
            ->where('fulfillment_supplier_vendor_id', $vendorId)
            ->get(['quantity', 'fulfillment_supplier_cost_snapshot']) as $item) {
            if ($item->fulfillment_supplier_cost_snapshot === null) {
                throw new InvalidArgumentException('Operational supplier cost snapshot is missing; recovery is blocked for finance review.');
            }
            $expected = BcMathService::add(
                $expected,
                BcMathService::mul((string) $item->fulfillment_supplier_cost_snapshot, (string) $item->quantity)
            );
        }

        $net = '0.00';
        $entries = FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'supplier_payable')
            ->where('beneficiary_type', VendorProfile::class)
            ->where('beneficiary_id', $vendorId)
            ->get(['entry_type', 'amount']);
        foreach ($entries as $entry) {
            $net = $entry->entry_type === 'credit'
                ? BcMathService::add($net, (string) $entry->amount)
                : BcMathService::sub($net, (string) $entry->amount);
        }

        if (BcMathService::comp($expected, $net) !== 0) {
            throw new InvalidArgumentException("Supplier payable ledger for supplier #{$vendorId} does not reconcile to the operational item snapshots; finance review is required.");
        }
    }

    /**
     * Keep journal idempotency identifiers deterministic and within the database
     * column limit even when the admin-supplied request key is at its 80-char max.
     * Never persist raw caller keys in journal rows.
     */
    private function journalIdempotencyKey(int $orderId, string $keySuffix): string
    {
        // The journal key has a database-wide unique constraint, while recovery
        // idempotency keys are scoped to an order. Include order identity so the
        // same caller key on two different orders cannot collide globally.
        return 'SUPREC-' . hash('sha256', $orderId . ':' . $keySuffix);
    }

    private function postSupplierJournalEntry(
        Order $order,
        int $orderItemId,
        string $transactionReference,
        string $keySuffix,
        int $vendorId,
        string $entryType,
        string $amount,
        string $description
    ): void {
        $amount = BcMathService::add($amount, '0.00');
        if (BcMathService::comp($amount, '0.00') <= 0) {
            throw new InvalidArgumentException('Recovery journal entries must have positive amounts.');
        }

        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItemId,
            'transaction_reference' => $transactionReference,
            'event_id' => 'EVT-' . $transactionReference . '-' . substr(hash('sha256', $keySuffix), 0, 20),
            'idempotency_key' => $this->journalIdempotencyKey((int) $order->id, $keySuffix),
            'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $vendorId,
            'account_name' => 'supplier_payable',
            'entry_type' => $entryType,
            'amount' => $amount,
            'currency' => 'NGN',
            'description' => $description,
        ]);
    }

    private function postVarianceEntry(
        Order $order,
        string $transactionReference,
        string $keySuffix,
        string $entryType,
        string $amount,
        int $orderItemId
    ): void {
        $amount = BcMathService::add($amount, '0.00');
        if (BcMathService::comp($amount, '0.00') <= 0) {
            return;
        }

        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItemId,
            'transaction_reference' => $transactionReference,
            'event_id' => 'EVT-' . $transactionReference . '-' . substr(hash('sha256', $keySuffix), 0, 20),
            'idempotency_key' => $this->journalIdempotencyKey((int) $order->id, $keySuffix),
            'beneficiary_type' => User::class,
            'beneficiary_id' => null,
            'account_name' => 'retailer_sourcing_variance',
            'entry_type' => $entryType,
            'amount' => $amount,
            'currency' => 'NGN',
            'description' => "Balanced supplier-cost variance for recovered order item #{$orderItemId}.",
        ]);
    }
}
