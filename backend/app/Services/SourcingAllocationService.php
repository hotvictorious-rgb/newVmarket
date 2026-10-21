<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSupplierAssignment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BcMathService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SourcingAllocationService
{
    public function __construct(
        protected ?SupplierOfferSelectionService $offerSelectionService = null
    ) {
        $this->offerSelectionService = $offerSelectionService ?? app(SupplierOfferSelectionService::class);
    }

    /**
     * Resolves the cheapest eligible and available private supplier offer for a given product.
     *
     * Selection Policy (Retailer Central Sourcing Invariant):
     * 1. Attempt resolution via SupplierOfferSelectionService for unexpired admin-confirmed evidence.
     * 2. Query active supplier_offers for the product where is_available = true.
     * 3. Ensure supplier vendor profile and its user account are active (not suspended/deleted).
     * 4. Ensure supplier shop branch is active (not deleted).
     * 5. Sort deterministically:
     *    - Lowest wholesale_cost ascending (BCMath comparison).
     *    - If cost ties and preferred LGA is specified, prioritize offer in that LGA.
     *    - Final tie-break by offer ID ascending.
     * 6. No catalog-owner fallback: missing explicit offer evidence means unavailable.
     * 7. Returns null if no eligible active supplier exists.
     *
     * @param Product $product
     * @param int $quantity
     * @param int|null $preferredLgaId
     * @param string $fulfillmentType
     * @return array|null
     */
    public function resolveCheapestOffer(
        Product $product,
        int $quantity = 1,
        ?int $preferredLgaId = null,
        string $fulfillmentType = 'doorstep_delivery'
    ): ?array {
        if (!in_array($fulfillmentType, ['doorstep_delivery', 'in_shop_pickup'], true)) {
            throw new InvalidArgumentException('Unsupported fulfillment type.');
        }

        // The fallback offer query below exists to apply the Uyo-origin constraint
        // across all offers, not to bypass catalog eligibility. Never source an
        // unapproved, unlisted, out-of-stock, or stale product.
        if (!$product->is_approved
            || $product->listing_status !== 'listed'
            || !$product->is_in_stock
            || !$product->isFresh()) {
            return null;
        }

        // 1. If an unexpired confirmed offer exists via SupplierOfferSelectionService, prioritize it
        if ($this->offerSelectionService) {
            try {
                $confirmedOffer = $this->offerSelectionService->selectLowestCost($product, $fulfillmentType);
                if ($confirmedOffer
                    && $this->isEligibleUyoShop($confirmedOffer->shop)
                    && (int) $confirmedOffer->shop->vendor_id === (int) $confirmedOffer->vendor_id
                    && $confirmedOffer->vendor
                    && !$confirmedOffer->vendor->trashed()
                    && $confirmedOffer->vendor->marketplace_status === 'active'
                    && $confirmedOffer->vendor->isKycVerified()
                    && $confirmedOffer->vendor->user
                    && $confirmedOffer->vendor->user->status === 'active') {
                    return [
                        'supplier_offer_id' => $confirmedOffer->id,
                        'vendor_id' => (int)$confirmedOffer->vendor_id,
                        'shop_id' => (int)$confirmedOffer->shop_id,
                        'supplier_name' => $confirmedOffer->vendor->business_name ?? ($confirmedOffer->vendor->user->name ?? 'Supplier'),
                        'shop_name' => $confirmedOffer->shop->name ?? 'Shop Branch',
                        'wholesale_cost' => bcadd((string)$confirmedOffer->wholesale_cost, '0', 2),
                        'currency' => 'NGN',
                        'source_type' => 'supplier_offer',
                        'offer_model' => $confirmedOffer,
                    ];
                }
            } catch (\Throwable) {
                // Fall through to general active offer query
            }
        }

        // 2. Query explicit private supplier offers
        $now = Carbon::now();
        $offers = SupplierOffer::where('product_id', $product->id)
            ->where('is_available', true)
            ->whereNotNull('availability_confirmed_at')
            ->where('availability_confirmed_at', '<=', $now)
            ->whereNotNull('availability_confirmed_by')
            ->whereNotNull('availability_expires_at')
            ->where('availability_expires_at', '>', $now)
            ->with(['vendor.user', 'shop'])
            ->get();

        $eligibleOffers = $offers->filter(function (SupplierOffer $offer) {
            // Must have active vendor profile and active user
            if (!$offer->vendor || $offer->vendor->trashed() || $offer->vendor->marketplace_status !== 'active') {
                return false;
            }
            if (!$offer->vendor->user || $offer->vendor->user->status !== 'active'
                || !$offer->vendor->isKycVerified()) {
                return false;
            }
            // Must have active shop
            if (!$offer->shop || $offer->shop->trashed() || !$offer->shop->is_active
                || (int) $offer->shop->vendor_id !== (int) $offer->vendor_id
                || ($fulfillmentType === 'in_shop_pickup' && !$offer->shop->pickup_enabled)
                || !$this->isEligibleUyoShop($offer->shop)) {
                return false;
            }
            // Wholesale cost must be >= 0
            if (bccomp((string)$offer->wholesale_cost, '0.00', 2) <= 0) {
                return false;
            }
            return true;
        });

        if ($eligibleOffers->isNotEmpty()) {
            // Sort deterministically: cheapest wholesale_cost first
            $sorted = $eligibleOffers->sort(function (SupplierOffer $a, SupplierOffer $b) use ($preferredLgaId) {
                $costComp = bccomp((string)$a->wholesale_cost, (string)$b->wholesale_cost, 2);
                if ($costComp !== 0) {
                    return $costComp;
                }

                // If cost is identical and preferred LGA is given, match LGA
                if ($preferredLgaId !== null) {
                    $aMatch = ($a->shop && (int)$a->shop->lga_id === (int)$preferredLgaId) ? 1 : 0;
                    $bMatch = ($b->shop && (int)$b->shop->lga_id === (int)$preferredLgaId) ? 1 : 0;
                    if ($aMatch !== $bMatch) {
                        return $bMatch <=> $aMatch; // 1 before 0
                    }
                }

                // Deterministic tie-breaker
                return $a->id <=> $b->id;
            });

            $bestOffer = $sorted->first();

            return [
                'supplier_offer_id' => $bestOffer->id,
                'vendor_id' => (int)$bestOffer->vendor_id,
                'shop_id' => (int)$bestOffer->shop_id,
                'supplier_name' => $bestOffer->vendor->business_name ?? ($bestOffer->vendor->user->name ?? 'Supplier'),
                'shop_name' => $bestOffer->shop->name ?? 'Shop Branch',
                'wholesale_cost' => bcadd((string)$bestOffer->wholesale_cost, '0', 2),
                'currency' => 'NGN',
                'source_type' => 'supplier_offer',
                'offer_model' => $bestOffer,
            ];
        }

        // New retailer sourcing must have an explicit, attributable, unexpired
        // supplier offer. Catalog ownership/cost defaults are not stock evidence and
        // must never silently create a supplier payable for a private supplier.
        return null;
    }

    /**
     * Retailer sourcing is anchored to active supplier shops in Uyo, Akwa Ibom only.
     */
    private function isEligibleUyoShop(?Shop $shop): bool
    {
        if (!$shop || $shop->trashed() || !$shop->is_active) {
            return false;
        }

        $shop->loadMissing('lga.state');

        $activeShopCount = Shop::where('vendor_id', $shop->vendor_id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->count();

        return $activeShopCount === 1
            && strtolower(trim((string) ($shop->lga->name ?? ''))) === 'uyo'
            && strtolower(trim((string) ($shop->lga->state->name ?? ''))) === 'akwa ibom';
    }

    /**
     * Builds the authoritative snapshot payload for OrderItem creation.
     * Ensures immutable wholesale purchase cost and supplier identification.
     *
     * @param array $resolvedOffer
     * @param Product $product
     * @param int $quantity
     * @param string $unitPrice
     * @param string $lineTotal
     * @return array
     */
    public function buildOrderItemSnapshot(
        array $resolvedOffer,
        Product $product,
        int $quantity,
        string $unitPrice,
        string $lineTotal
    ): array {
        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price' => $resolvedOffer['wholesale_cost'],
            'line_total' => $lineTotal,
            'supplier_offer_id' => $resolvedOffer['supplier_offer_id'],
            'supplier_vendor_id' => $resolvedOffer['vendor_id'],
            'supplier_shop_id' => $resolvedOffer['shop_id'],
            'supplier_name_snapshot' => $resolvedOffer['supplier_name'],
            'supplier_shop_name_snapshot' => $resolvedOffer['shop_name'],
            'supplier_cost_snapshot' => $resolvedOffer['wholesale_cost'],
            'supplier_cost_currency' => $resolvedOffer['currency'] ?? 'NGN',
            'supplier_snapshot_at' => Carbon::now(),
            // Operational assignment initially mirrors the capture-time supplier.
            // After payment, recovery may change only fulfillment_* fields; the
            // supplier_* capture evidence remains immutable for financial audit.
            'fulfillment_supplier_offer_id' => $resolvedOffer['supplier_offer_id'],
            'fulfillment_supplier_vendor_id' => $resolvedOffer['vendor_id'],
            'fulfillment_supplier_shop_id' => $resolvedOffer['shop_id'],
            'fulfillment_supplier_name_snapshot' => $resolvedOffer['supplier_name'],
            'fulfillment_supplier_shop_name_snapshot' => $resolvedOffer['shop_name'],
            'fulfillment_supplier_cost_snapshot' => $resolvedOffer['wholesale_cost'],
            'fulfillment_supplier_snapshot_at' => Carbon::now(),
        ];
    }

    /**
     * Assigns order sourcing to a selected supplier and shop branch.
     *
     * @param Order $order
     * @param int $vendorId
     * @param int $shopId
     * @param string|null $notes
     * @return Order
     */
    public function assignOrderSourcing(Order $order, int $vendorId, int $shopId, ?string $notes = null): Order
    {
        $order->selected_supplier_id = $vendorId;
        $order->selected_shop_id = $shopId;
        $order->sourcing_status = 'assigned_to_supplier';
        $order->sourcing_notes = $notes;
        $order->sourced_at = Carbon::now();
        $order->save();

        return $order;
    }

    /**
     * Reassign selected order items to another confirmed Uyo supplier before payment.
     * Retail orders can contain several supplier groups, so overrides must target the
     * affected item IDs rather than changing a single order-wide supplier field.
     */
    public function overrideSupplierAssignmentGroup(
        OrderSupplierAssignment $assignment,
        int $newVendorId,
        int $newShopId,
        array $orderItemIds,
        User $adminUser,
        string $reason
    ): OrderSupplierAssignment {
        $isAdmin = $adminUser->isSuperAdmin()
            || $adminUser->isAdminEmployee()
            || in_array($adminUser->user_type, ['super_admin', 'admin_employee', 'admin'], true);
        if (!$isAdmin) {
            throw new InvalidArgumentException('Unauthorized: only administrators can override supplier sourcing.');
        }

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw new InvalidArgumentException('A non-empty override reason of at most 2,000 characters is required.');
        }

        $itemIds = array_values(array_unique(array_map('intval', $orderItemIds)));
        if (!$itemIds) {
            throw new InvalidArgumentException('Select at least one order item to reassign.');
        }

        return DB::transaction(function () use ($assignment, $newVendorId, $newShopId, $itemIds, $adminUser, $reason) {
            $lockedAssignment = OrderSupplierAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($lockedAssignment->order_id)->lockForUpdate()->firstOrFail();

            if (!$order->retailer_model_order || $order->payment_status !== 'pending'
                || $order->order_status !== 'pending' || $lockedAssignment->status !== 'awaiting_payment') {
                throw new InvalidArgumentException('Supplier overrides are allowed only before payment while the order and assignment are still pending.');
            }
            if ((int) $lockedAssignment->supplier_vendor_id === $newVendorId) {
                throw new InvalidArgumentException('Choose a different supplier account for an override.');
            }
            if ($order->expires_at && Carbon::now()->gte($order->expires_at)) {
                throw new InvalidArgumentException('This checkout reservation has expired; create a new checkout before changing suppliers.');
            }

            $newVendor = VendorProfile::withTrashed()->with('user')->find($newVendorId);
            $newShop = Shop::withTrashed()->with(['lga.state', 'vendor.user'])->find($newShopId);
            if (!$newVendor || $newVendor->trashed() || $newVendor->marketplace_status !== 'active'
                || !$newVendor->user || $newVendor->user->status !== 'active'
                || !$newVendor->isKycVerified()) {
                throw new InvalidArgumentException('The replacement supplier account must be active, KYC-verified, and approved.');
            }
            if (!$newShop || $newShop->trashed() || !$newShop->is_active
                || (int) $newShop->vendor_id !== (int) $newVendor->id) {
                throw new InvalidArgumentException('The replacement shop must be active and belong to the replacement supplier.');
            }
            if (Shop::where('vendor_id', $newVendor->id)->whereNull('deleted_at')->where('is_active', true)->count() !== 1) {
                throw new InvalidArgumentException('A private supplier must have exactly one active shop/pickup point.');
            }
            if (strtolower(trim((string) ($newShop->lga->name ?? ''))) !== 'uyo'
                || strtolower(trim((string) ($newShop->lga->state->name ?? ''))) !== 'akwa ibom') {
                throw new InvalidArgumentException('Replacement supplier shops must be physically based in Uyo, Akwa Ibom State.');
            }

            $items = OrderItem::where('order_id', $order->id)
                ->whereIn('id', $itemIds)
                ->where('supplier_vendor_id', $lockedAssignment->supplier_vendor_id)
                ->where('supplier_shop_id', $lockedAssignment->supplier_shop_id)
                ->lockForUpdate()
                ->get();
            if ($items->count() !== count($itemIds)) {
                throw new InvalidArgumentException('Every selected order item must belong to this supplier assignment.');
            }

            $oldItems = [];
            foreach ($items as $item) {
                // Lock the offer while checking and snapshotting its cost so a
                // concurrent availability/cost update cannot race this override.
                $offerCheckAt = Carbon::now();
                $offer = SupplierOffer::where('product_id', $item->product_id)
                    ->where('vendor_id', $newVendor->id)
                    ->where('shop_id', $newShop->id)
                    ->where('is_available', true)
                    ->whereNotNull('availability_confirmed_at')
                    ->where('availability_confirmed_at', '<=', $offerCheckAt)
                    ->whereNotNull('availability_confirmed_by')
                    ->whereNotNull('availability_expires_at')
                    ->where('availability_expires_at', '>', $offerCheckAt)
                    ->lockForUpdate()
                    ->first();

                if (!$offer || BcMathService::comp((string) $offer->wholesale_cost, '0.00') <= 0) {
                    throw new InvalidArgumentException("The replacement supplier has no fresh confirmed offer for {$item->product_name}.");
                }

                $oldItems[] = [
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'supplier_vendor_id' => $item->supplier_vendor_id,
                    'supplier_shop_id' => $item->supplier_shop_id,
                    'supplier_cost_snapshot' => $item->supplier_cost_snapshot,
                ];

                $item->supplier_offer_id = $offer->id;
                $item->supplier_vendor_id = $newVendor->id;
                $item->supplier_shop_id = $newShop->id;
                $item->supplier_name_snapshot = $newVendor->business_name ?? $newVendor->user->name;
                $item->supplier_shop_name_snapshot = $newShop->name;
                $item->supplier_cost_snapshot = BcMathService::add((string) $offer->wholesale_cost, '0.00');
                $item->supplier_cost_currency = 'NGN';
                $item->supplier_snapshot_at = Carbon::now();
                $item->fulfillment_supplier_offer_id = $offer->id;
                $item->fulfillment_supplier_vendor_id = $newVendor->id;
                $item->fulfillment_supplier_shop_id = $newShop->id;
                $item->fulfillment_supplier_name_snapshot = $newVendor->business_name ?? $newVendor->user->name;
                $item->fulfillment_supplier_shop_name_snapshot = $newShop->name;
                $item->fulfillment_supplier_cost_snapshot = BcMathService::add((string) $offer->wholesale_cost, '0.00');
                $item->fulfillment_supplier_snapshot_at = Carbon::now();
                $item->cost_price = BcMathService::add((string) $offer->wholesale_cost, '0.00');
                $item->save();
            }

            $remainingOldItems = OrderItem::where('order_id', $order->id)
                ->where('supplier_vendor_id', $lockedAssignment->supplier_vendor_id)
                ->where('supplier_shop_id', $lockedAssignment->supplier_shop_id)
                ->exists();
            if (!$remainingOldItems) {
                $lockedAssignment->delete();
            }

            $newAssignment = OrderSupplierAssignment::firstOrCreate([
                'order_id' => $order->id,
                'supplier_vendor_id' => $newVendor->id,
                'supplier_shop_id' => $newShop->id,
            ], [
                'status' => 'awaiting_payment',
            ]);
            if ($newAssignment->status !== 'awaiting_payment') {
                throw new InvalidArgumentException('The replacement supplier group is not in a pending state and cannot be merged safely.');
            }

            $supplierCostTotal = '0.00';
            foreach (OrderItem::where('order_id', $order->id)->get() as $item) {
                $supplierCostTotal = BcMathService::add(
                    $supplierCostTotal,
                    BcMathService::mul((string) $item->supplier_cost_snapshot, (string) $item->quantity)
                );
            }
            $order->supplier_cost_total_snapshot = $supplierCostTotal;
            $order->fulfillment_supplier_cost_total_snapshot = $supplierCostTotal;
            $order->retailer_merchandise_margin = BcMathService::sub(
                BcMathService::sub((string) $order->merchandise_subtotal, $supplierCostTotal),
                (string) $order->customer_cashback_5
            );
            $order->selected_supplier_id = null;
            $order->selected_shop_id = null;
            $order->sourcing_status = 'awaiting_payment';
            $order->sourced_at = null;
            $order->save();

            AdminAuditLog::create([
                'user_id' => $adminUser->id,
                'action' => 'admin_override_supplier_assignment_items',
                'target_type' => Order::class,
                'target_id' => $order->id,
                'old_values' => [
                    'assignment_id' => $lockedAssignment->id,
                    'items' => $oldItems,
                ],
                'new_values' => [
                    'replacement_assignment_id' => $newAssignment->id,
                    'supplier_vendor_id' => $newVendor->id,
                    'supplier_shop_id' => $newShop->id,
                    'order_item_ids' => $itemIds,
                    'reason' => $reason,
                    'admin_user_id' => $adminUser->id,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System/CLI',
            ]);

            return $newAssignment->fresh();
        });
    }

    /**
     * Admin override of supplier sourcing assignment.
     * Records full before/after state in AdminAuditLog for compliance.
     *
     * @param Order $order
     * @param int $newVendorId
     * @param int $newShopId
     * @param User $adminUser
     * @param string $reason
     * @return Order
     * @throws InvalidArgumentException
     */
    public function overrideSupplierAssignment(
        Order $order,
        int $newVendorId,
        int $newShopId,
        User $adminUser,
        string $reason
    ): Order {
        if ($order->retailer_model_order) {
            throw new InvalidArgumentException('Retailer orders require an audited item-level supplier override before payment.');
        }

        $isAdmin = $adminUser->isSuperAdmin()
            || $adminUser->isAdminEmployee()
            || in_array($adminUser->user_type, ['super_admin', 'admin_employee', 'admin'], true);

        if (!$isAdmin) {
            throw new InvalidArgumentException('Unauthorized: only administrators can override supplier sourcing.');
        }

        $newVendor = VendorProfile::find($newVendorId);
        if (!$newVendor || $newVendor->trashed() || $newVendor->marketplace_status !== 'active') {
            throw new InvalidArgumentException('Selected replacement supplier is invalid or inactive.');
        }

        $newShop = Shop::find($newShopId);
        if (!$newShop || $newShop->trashed() || !$newShop->is_active) {
            throw new InvalidArgumentException('Selected replacement shop is invalid or inactive.');
        }

        if ((int)$newShop->vendor_id !== (int)$newVendorId) {
            throw new InvalidArgumentException('Selected shop does not belong to the selected supplier.');
        }

        $oldValues = [
            'selected_supplier_id' => $order->selected_supplier_id,
            'selected_shop_id' => $order->selected_shop_id,
            'sourcing_status' => $order->sourcing_status,
            'sourcing_notes' => $order->sourcing_notes,
        ];

        $newValues = [
            'selected_supplier_id' => $newVendorId,
            'selected_shop_id' => $newShopId,
            'sourcing_status' => 'admin_reassigned',
            'reason' => $reason,
        ];

        AdminAuditLog::create([
            'user_id' => $adminUser->id,
            'action' => 'admin_override_supplier_sourcing',
            'target_type' => Order::class,
            'target_id' => $order->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System/CLI',
        ]);

        $order->selected_supplier_id = $newVendorId;
        $order->selected_shop_id = $newShopId;
        $order->sourcing_status = 'admin_reassigned';
        $order->sourcing_notes = "Admin override by {$adminUser->email}: {$reason}";
        $order->save();

        return $order;
    }

    /**
     * Supplier confirms physical availability of items for an assigned order.
     *
     * @param Order $order
     * @param int $vendorId
     * @param string|null $notes
     * @return Order
     * @throws InvalidArgumentException
     */
    public function confirmSupplierAvailability(Order $order, int $vendorId, ?string $notes = null): Order
    {
        // This order-level compatibility method cannot authorize new retailer
        // orders: they require per-supplier assignment rows and the row-locked
        // confirmation endpoint. In particular, never let it clear a rejection hold.
        if ($order->retailer_model_order) {
            throw new InvalidArgumentException('Retailer orders must use the supplier-assignment confirmation workflow; order-level confirmation is disabled.');
        }

        if ((int)$order->selected_supplier_id !== (int)$vendorId) {
            throw new InvalidArgumentException('Unauthorized: order is not assigned to this supplier.');
        }

        $order->sourcing_status = 'supplier_confirmed';
        if ($notes) {
            $order->sourcing_notes = $order->sourcing_notes ? ($order->sourcing_notes . ' | ' . $notes) : $notes;
        }
        $order->save();

        return $order;
    }
}
