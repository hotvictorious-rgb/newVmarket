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
    /**
     * Resolves the optimal whole-order sourcing plan across all cart items.
     *
     * Selection Policy (The Binding Consolidated Sourcing Law):
     * 1. Evaluates all eligible candidate offers for every cart item.
     * 2. Forms feasible whole-order combinatorial plans capable of fulfilling the COMPLETE order.
     * 3. Sorts feasible plans deterministically in this strict priority:
     *    - FEWEST distinct supplier pickup locations (consolidate stops, eliminate dispatch sprawl).
     *    - LOWEST total wholesale cost among plans with the same minimal pickup location count.
     *    - HIGHEST supplier historical fulfilment reliability score.
     *    - Deterministic tie-break by ID.
     * 4. Returns null if any item cannot be fulfilled by an eligible Uyo supplier.
     */
    public function resolveOptimalWholeOrderPlan(
        array $items,
        string $fulfillmentType = 'doorstep_delivery',
        ?int $preferredLgaId = null
    ): ?array {
        if (empty($items)) {
            return null;
        }

        // 1. Gather all eligible candidate offers for each cart item
        $candidatesPerItem = [];
        foreach ($items as $idx => $cartItem) {
            $product = $cartItem['product'] ?? null;
            $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));

            if (!$product instanceof Product) {
                return null;
            }

            if (!$product->is_approved || $product->listing_status !== 'listed' || !$product->is_in_stock || !$product->isFresh()) {
                return null;
            }

            $rawOffers = $this->offerSelectionService
                ? $this->offerSelectionService->getEligibleOffers($product, $fulfillmentType)
                : collect();

            $eligible = [];
            foreach ($rawOffers as $offer) {
                if ($this->isEligibleUyoShop($offer->shop)
                    && (int) $offer->shop->vendor_id === (int) $offer->vendor_id
                    && $offer->vendor
                    && !$offer->vendor->trashed()
                    && $offer->vendor->marketplace_status === 'active'
                    && $offer->vendor->isKycVerified()
                    && $offer->vendor->user
                    && $offer->vendor->user->status === 'active'
                    && !$offer->isPenaltyActive()
                    && bccomp((string) $offer->wholesale_cost, '0.00', 2) > 0
                ) {
                    $eligible[] = [
                        'supplier_offer_id' => $offer->id,
                        'vendor_id' => (int) $offer->vendor_id,
                        'shop_id' => (int) $offer->shop_id,
                        'supplier_name' => $offer->vendor->business_name ?? ($offer->vendor->user->name ?? 'Supplier'),
                        'shop_name' => $offer->shop->name ?? 'Shop Branch',
                        'wholesale_cost' => bcadd((string) $offer->wholesale_cost, '0', 2),
                        'currency' => 'NGN',
                        'source_type' => 'supplier_offer',
                        'offer_model' => $offer,
                        'reliability' => $this->getSupplierReliabilityScore((int) $offer->vendor_id),
                    ];
                }
            }

            if (empty($eligible)) {
                // Whole-order feasibility failure: one or more items cannot be sourced
                return null;
            }

            $candidatesPerItem[$idx] = [
                'product' => $product,
                'quantity' => $quantity,
                'offers' => $eligible,
            ];
        }

        // 2. Optimization: Single-Stop Fast-Path (Consolidated Sourcing Law: Fewest Stops First)
        // If one or more single shops have eligible offers for 100% of the cart items, any 1-stop plan
        // will strictly beat any >= 2 stop plan. We prune evaluation strictly to 1-stop candidates.
        $singleStopShops = null;
        foreach ($candidatesPerItem as $itemData) {
            $shopsForItem = [];
            foreach ($itemData['offers'] as $offer) {
                $shopsForItem[$offer['shop_id']] = true;
            }
            if ($singleStopShops === null) {
                $singleStopShops = $shopsForItem;
            } else {
                $singleStopShops = array_intersect_key($singleStopShops, $shopsForItem);
            }
        }

        if (!empty($singleStopShops)) {
            // Prune each item's candidate offers strictly to the single-stop shops
            foreach ($candidatesPerItem as $cIdx => $itemData) {
                $candidatesPerItem[$cIdx]['offers'] = array_values(array_filter(
                    $itemData['offers'],
                    fn($offer) => isset($singleStopShops[$offer['shop_id']])
                ));
            }
        } else {
            // No single shop can fulfill the whole cart (requires >= 2 stops).
            // Prevent combinatorial Cartesian explosion (M^N) via Consolidation Potential Pruning:
            $candidatesPerItem = $this->pruneCandidatesForCombinatorialSafety($candidatesPerItem);
        }

        // 3. Generate and evaluate Cartesian feasible plans
        $plans = $this->generateCombinatorialPlans($candidatesPerItem);
        if (empty($plans)) {
            return null;
        }

        // 3. Sort feasible plans according to the Consolidated Sourcing Law
        usort($plans, function ($planA, $planB) use ($preferredLgaId) {
            // Priority 1: Fewest distinct supplier pickup locations
            $locCountA = count($planA['distinct_shops']);
            $locCountB = count($planB['distinct_shops']);
            if ($locCountA !== $locCountB) {
                return $locCountA <=> $locCountB;
            }

            // Priority 2: Lowest total wholesale cost among equally consolidated plans
            $costComp = bccomp($planA['total_wholesale_cost'], $planB['total_wholesale_cost'], 2);
            if ($costComp !== 0) {
                return $costComp;
            }

            // Priority 3: Reliability (higher average reliability wins)
            if (abs($planA['avg_reliability'] - $planB['avg_reliability']) > 0.001) {
                return $planB['avg_reliability'] <=> $planA['avg_reliability'];
            }

            // Priority 4: Preferred LGA match if specified
            if ($preferredLgaId !== null) {
                $aMatch = in_array($preferredLgaId, $planA['lga_ids'], true) ? 1 : 0;
                $bMatch = in_array($preferredLgaId, $planB['lga_ids'], true) ? 1 : 0;
                if ($aMatch !== $bMatch) {
                    return $bMatch <=> $aMatch;
                }
            }

            // Deterministic tie-breaker
            return strcmp($planA['deterministic_key'], $planB['deterministic_key']);
        });

        $bestPlan = $plans[0];

        return [
            'locations_count' => count($bestPlan['distinct_shops']),
            'distinct_shop_ids' => array_values($bestPlan['distinct_shops']),
            'total_wholesale_cost' => $bestPlan['total_wholesale_cost'],
            'avg_reliability' => $bestPlan['avg_reliability'],
            'items' => $bestPlan['items'],
            'primary_shop_id' => $bestPlan['items'][0]['resolved_offer']['shop_id'] ?? null,
            'primary_vendor_id' => $bestPlan['items'][0]['resolved_offer']['vendor_id'] ?? null,
        ];
    }

    /**
     * Generates all feasible whole-order combinatorial plans.
     */
    private function generateCombinatorialPlans(array $candidatesPerItem): array
    {
        $itemKeys = array_keys($candidatesPerItem);
        $totalItems = count($itemKeys);

        $buildCombinations = function ($index) use (&$buildCombinations, $candidatesPerItem, $itemKeys, $totalItems) {
            if ($index === $totalItems) {
                return [[]];
            }

            $currentKey = $itemKeys[$index];
            $itemData = $candidatesPerItem[$currentKey];
            $product = $itemData['product'];
            $quantity = $itemData['quantity'];
            $offers = $itemData['offers'];

            $subCombinations = $buildCombinations($index + 1);
            $results = [];

            foreach ($offers as $offer) {
                $line = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'resolved_offer' => $offer,
                ];
                foreach ($subCombinations as $sub) {
                    $results[] = array_merge([$line], $sub);
                }
            }

            return $results;
        };

        $rawCombinations = $buildCombinations(0);
        $plans = [];

        foreach ($rawCombinations as $combo) {
            $distinctShops = [];
            $lgaIds = [];
            $totalCost = '0.00';
            $totalReliability = 0.0;
            $offerIds = [];

            foreach ($combo as $line) {
                $offer = $line['resolved_offer'];
                $distinctShops[$offer['shop_id']] = $offer['shop_id'];
                if ($offer['offer_model'] && $offer['offer_model']->shop) {
                    $lgaIds[] = $offer['offer_model']->shop->lga_id;
                }
                $itemCost = bcmul((string) $offer['wholesale_cost'], (string) $line['quantity'], 2);
                $totalCost = bcadd($totalCost, $itemCost, 2);
                $totalReliability += (float) ($offer['reliability'] ?? 100.0);
                $offerIds[] = $offer['supplier_offer_id'];
            }

            sort($offerIds);
            $plans[] = [
                'items' => $combo,
                'distinct_shops' => array_values($distinctShops),
                'lga_ids' => array_unique($lgaIds),
                'total_wholesale_cost' => $totalCost,
                'avg_reliability' => count($combo) > 0 ? round($totalReliability / count($combo), 2) : 100.0,
                'deterministic_key' => implode('-', $offerIds),
            ];
        }

        return $plans;
    }

    /**
     * Intelligently prunes candidate offers to prevent M^N Cartesian explosion
     * while preserving maximum consolidation potential and lowest wholesale cost.
     */
    private function pruneCandidatesForCombinatorialSafety(array $candidatesPerItem): array
    {
        // Count how many cart items each shop can fulfill (consolidation potential)
        $shopItemCounts = [];
        foreach ($candidatesPerItem as $itemData) {
            $seenInItem = [];
            foreach ($itemData['offers'] as $offer) {
                $sid = $offer['shop_id'];
                if (!isset($seenInItem[$sid])) {
                    $shopItemCounts[$sid] = ($shopItemCounts[$sid] ?? 0) + 1;
                    $seenInItem[$sid] = true;
                }
            }
        }

        // Rank offers within each item:
        // 1. Higher consolidation potential (shops appearing across multiple cart items)
        // 2. Lower wholesale cost
        // 3. Higher supplier reliability score
        foreach ($candidatesPerItem as $idx => $itemData) {
            $offers = $itemData['offers'];
            usort($offers, function ($a, $b) use ($shopItemCounts) {
                $countA = $shopItemCounts[$a['shop_id']] ?? 0;
                $countB = $shopItemCounts[$b['shop_id']] ?? 0;
                if ($countA !== $countB) {
                    return $countB <=> $countA; // More shared items first
                }
                $costComp = bccomp($a['wholesale_cost'], $b['wholesale_cost'], 2);
                if ($costComp !== 0) {
                    return $costComp; // Cheaper wholesale cost first
                }
                return ($b['reliability'] ?? 100.0) <=> ($a['reliability'] ?? 100.0);
            });

            // Keep top 4 candidates per item
            $candidatesPerItem[$idx]['offers'] = array_slice($offers, 0, 4);
        }

        // Dynamic safety cap: If total combinations exceed 1000, progressively trim
        $calcCombos = function ($items) {
            $combos = 1;
            foreach ($items as $item) {
                $combos *= max(1, count($item['offers']));
            }
            return $combos;
        };

        while ($calcCombos($candidatesPerItem) > 1000) {
            $maxCount = 0;
            $maxIdx = null;
            foreach ($candidatesPerItem as $idx => $item) {
                $c = count($item['offers']);
                if ($c > $maxCount && $c > 1) {
                    $maxCount = $c;
                    $maxIdx = $idx;
                }
            }
            if ($maxIdx === null) {
                break;
            }
            array_pop($candidatesPerItem[$maxIdx]['offers']);
        }

        return $candidatesPerItem;
    }

    /**
     * Compute dynamic supplier historical fulfilment reliability score (0-100%).
     */
    public function getSupplierReliabilityScore(int $vendorId): float
    {
        $completed = OrderSupplierAssignment::where('supplier_vendor_id', $vendorId)
            ->whereIn('status', ['collected', 'completed'])
            ->count();
        $breaches = OrderSupplierAssignment::where('supplier_vendor_id', $vendorId)
            ->whereIn('status', ['supplier_rejected', 'timed_out'])
            ->count();

        $total = $completed + $breaches;
        if ($total === 0) {
            return 100.0; // Standard 100% baseline for verified suppliers
        }

        return round(($completed / $total) * 100, 2);
    }

    /**
     * Resolves the cheapest eligible and available private supplier offer for a given product.
     * Backwards-compatible single-item interface to whole-order consolidation logic.
     */
    public function resolveCheapestOffer(
        Product $product,
        int $quantity = 1,
        ?int $preferredLgaId = null,
        string $fulfillmentType = 'doorstep_delivery'
    ): ?array {
        $plan = $this->resolveOptimalWholeOrderPlan(
            [['product' => $product, 'quantity' => $quantity]],
            $fulfillmentType,
            $preferredLgaId
        );

        return $plan ? ($plan['items'][0]['resolved_offer'] ?? null) : null;
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
     * Bind supplier sourcing only after the order's payment has been authoritatively
     * verified. Call from payment settlement; checkout may only run a non-binding
     * feasibility check.
     */
    public function allocateVerifiedPaidOrder(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (!$lockedOrder->retailer_model_order || $lockedOrder->payment_status !== 'paid') {
                throw new InvalidArgumentException('Supplier allocation requires a retailer order with verified paid status.');
            }

            $items = OrderItem::where('order_id', $lockedOrder->id)->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                return ['success' => false, 'reason' => 'The paid order has no order lines to source.'];
            }

            $allSnapshotted = $items->every(fn ($item) =>
                !empty($item->supplier_offer_id)
                && !empty($item->supplier_vendor_id)
                && !empty($item->supplier_shop_id)
                && $item->supplier_cost_snapshot !== null
            );
            if ($allSnapshotted) {
                if (!OrderSupplierAssignment::where('order_id', $lockedOrder->id)->exists()
                    || $lockedOrder->supplier_cost_total_snapshot === null) {
                    throw new \RuntimeException('Partial supplier allocation state detected; manual reconciliation is required.');
                }
                return ['success' => true, 'replayed' => true, 'locations_count' => (int) $lockedOrder->sourcing_plan_locations_count];
            }

            if ($items->contains(fn ($item) =>
                $item->supplier_offer_id !== null
                || $item->supplier_vendor_id !== null
                || $item->supplier_shop_id !== null
                || $item->supplier_cost_snapshot !== null
            )) {
                throw new \RuntimeException('Partial supplier snapshots detected; allocation is blocked for admin reconciliation.');
            }

            $planItems = [];
            foreach ($items as $item) {
                $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                if (!$product) {
                    return ['success' => false, 'reason' => "Product for order line {$item->id} no longer exists."];
                }
                $planItems[] = ['product' => $product, 'quantity' => (int) $item->quantity];
            }

            $plan = $this->resolveOptimalWholeOrderPlan($planItems, (string) $lockedOrder->fulfillment_type);
            if (!$plan) {
                return ['success' => false, 'reason' => 'No eligible Uyo supplier plan remains after verified payment; supplier availability changed or no eligible alternative exists.'];
            }

            // Validate and lock every selected offer before writing any item
            // snapshot. A late invalid line must not commit a partial allocation.
            $validatedLines = [];
            foreach (array_values($plan['items']) as $index => $planLine) {
                $item = $items[$index] ?? null;
                if (!$item || (int) $item->product_id !== (int) $planLine['product']->id) {
                    throw new \RuntimeException('Sourcing plan no longer matches the locked order lines.');
                }

                $offerData = $planLine['resolved_offer'];
                $offer = SupplierOffer::whereKey($offerData['supplier_offer_id'])
                    ->with(['vendor.user', 'shop.lga.state'])
                    ->lockForUpdate()
                    ->first();

                $now = Carbon::now();
                if (!$offer
                    || !$offer->is_available
                    || $offer->isPenaltyActive()
                    || !$offer->availability_expires_at
                    || $offer->availability_expires_at->lte($now)
                    || BcMathService::comp((string) ($offer->wholesale_cost ?? '0'), '0.00') <= 0
                    || !$offer->vendor
                    || $offer->vendor->trashed()
                    || $offer->vendor->marketplace_status !== 'active'
                    || !$offer->vendor->isKycVerified()
                    || !$offer->vendor->user
                    || $offer->vendor->user->status !== 'active'
                    || !$this->isEligibleUyoShop($offer->shop)
                    || (int) $offer->vendor_id !== (int) $offer->shop->vendor_id
                ) {
                    return ['success' => false, 'reason' => "Supplier availability changed while allocating order line {$item->id}."];
                }

                $validatedLines[] = [
                    'item' => $item,
                    'product' => $planLine['product'],
                    'resolved' => [
                        'supplier_offer_id' => (int) $offer->id,
                        'vendor_id' => (int) $offer->vendor_id,
                        'shop_id' => (int) $offer->shop_id,
                        'supplier_name' => $offer->vendor->business_name ?? $offer->vendor->user->name,
                        'shop_name' => $offer->shop->name,
                        'wholesale_cost' => BcMathService::add((string) $offer->wholesale_cost, '0.00'),
                        'currency' => 'NGN',
                    ],
                ];
            }

            $groups = [];
            $supplierCostTotal = '0.00';
            foreach ($validatedLines as $line) {
                $item = $line['item'];
                $resolved = $line['resolved'];
                $item->fill($this->buildOrderItemSnapshot(
                    $resolved,
                    $line['product'],
                    (int) $item->quantity,
                    (string) $item->unit_price,
                    (string) $item->line_total,
                    (string) ($item->package_tier ?? 'bike')
                ));
                $item->save();

                $groupKey = $resolved['vendor_id'] . ':' . $resolved['shop_id'];
                $groups[$groupKey] = [
                    'supplier_vendor_id' => $resolved['vendor_id'],
                    'supplier_shop_id' => $resolved['shop_id'],
                ];
                $supplierCostTotal = BcMathService::add(
                    $supplierCostTotal,
                    BcMathService::mul($resolved['wholesale_cost'], (string) $item->quantity)
                );
            }

            foreach ($groups as $group) {
                OrderSupplierAssignment::firstOrCreate(
                    [
                        'order_id' => $lockedOrder->id,
                        'supplier_vendor_id' => $group['supplier_vendor_id'],
                        'supplier_shop_id' => $group['supplier_shop_id'],
                    ],
                    [
                        'status' => 'awaiting_supplier_confirmation',
                        'assigned_at' => Carbon::now(),
                    ]
                );
            }

            $firstSnapshot = $items->first()?->fresh();
            $lockedOrder->vendor_id = $firstSnapshot?->supplier_vendor_id;
            $lockedOrder->shop_id = $firstSnapshot?->supplier_shop_id;
            $lockedOrder->supplier_cost_total_snapshot = $supplierCostTotal;
            $lockedOrder->fulfillment_supplier_cost_total_snapshot = $supplierCostTotal;
            $lockedOrder->retailer_merchandise_margin = BcMathService::sub(
                BcMathService::sub((string) $lockedOrder->merchandise_subtotal, $supplierCostTotal),
                (string) $lockedOrder->customer_cashback_5
            );
            $lockedOrder->sourcing_plan_locations_count = count($groups);
            $lockedOrder->sourcing_status = 'awaiting_supplier_confirmation';
            $lockedOrder->sourced_at = Carbon::now();
            $lockedOrder->save();

            return ['success' => true, 'replayed' => false, 'locations_count' => count($groups)];
        });
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
        string $lineTotal,
        string $packageTier = 'bike'
    ): array {
        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price' => $resolvedOffer['wholesale_cost'],
            'line_total' => $lineTotal,
            'package_tier' => $packageTier,
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
        $isAdmin = $adminUser->status === 'active'
            && ($adminUser->isSuperAdmin()
                || $adminUser->isAdminEmployee()
                || in_array($adminUser->user_type, ['super_admin', 'admin_employee', 'admin'], true));
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

            $isPaidException = $order->retailer_model_order
                && $order->payment_status === 'paid'
                && in_array($order->sourcing_status, ['stockout_revalidation_hold', 'supplier_rejected', 'timed_out', 'counter_breach', 'awaiting_supplier_confirmation'], true)
                && in_array($lockedAssignment->status, ['stockout_revalidation_hold', 'supplier_rejected', 'timed_out', 'counter_breach', 'awaiting_supplier_confirmation'], true);

            $isPendingPrePayment = $order->retailer_model_order
                && $order->payment_status === 'pending'
                && $order->order_status === 'pending'
                && $lockedAssignment->status === 'awaiting_payment';

            if (!$isPaidException && !$isPendingPrePayment) {
                throw new InvalidArgumentException('Supplier overrides are allowed only before payment or on paid orders under an authorized sourcing exception hold.');
            }
            if ((int) $lockedAssignment->supplier_vendor_id === $newVendorId) {
                throw new InvalidArgumentException('Choose a different supplier account for an override.');
            }
            if ($isPendingPrePayment && $order->expires_at && Carbon::now()->gte($order->expires_at)) {
                throw new InvalidArgumentException('This checkout reservation has expired; create a new checkout before changing suppliers.');
            }

            if ($isPaidException) {
                $recoveryService = app(\App\Services\SupplierReassignmentRecoveryService::class);
                $idempotencyKey = 'override-' . $lockedAssignment->id . '-' . microtime(true);
                $result = $recoveryService->reassignRejectedItems(
                    $order->id,
                    $lockedAssignment->id,
                    $newVendorId,
                    $newShopId,
                    $itemIds,
                    $adminUser,
                    $reason,
                    $idempotencyKey
                );

                return OrderSupplierAssignment::findOrFail($result['replacement_assignment_id']);
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
