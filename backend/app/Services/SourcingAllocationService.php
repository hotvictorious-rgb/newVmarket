<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\OrderItem;
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
     * 6. Fallback: If no explicit supplier_offers rows exist, check product's default vendor and shop.
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
        // 1. If an unexpired confirmed offer exists via SupplierOfferSelectionService, prioritize it
        if ($this->offerSelectionService) {
            try {
                $confirmedOffer = $this->offerSelectionService->selectLowestCost($product, $fulfillmentType);
                if ($confirmedOffer && $this->isEligibleUyoShop($confirmedOffer->shop)) {
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
            if ($offer->vendor->user && $offer->vendor->user->status !== 'active') {
                return false;
            }
            // Must have active shop
            if (!$offer->shop || $offer->shop->trashed() || !$offer->shop->is_active || !$this->isEligibleUyoShop($offer->shop)) {
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

        // 2. Backward compatibility fallback to catalog listing defaults if no supplier_offers exist
        if ($product->vendor_id && $product->shop_id) {
            $product->loadMissing(['vendor.user', 'shop']);
            $vendor = $product->vendor;
            $shop = $product->shop;

            $isVendorActive = $vendor && !$vendor->trashed() && $vendor->marketplace_status === 'active';
            $isUserActive = !$vendor || !$vendor->user || $vendor->user->status === 'active';
            $isShopActive = $shop && !$shop->trashed() && $shop->is_active;

            if ($isVendorActive && $isUserActive && $isShopActive && $this->isEligibleUyoShop($shop)) {
                $fallbackCost = ($product->cost_price && bccomp((string)$product->cost_price, '0.00', 2) > 0)
                    ? (string)$product->cost_price
                    : bcmul((string)$product->unit_price, '0.85', 2);
                $cost = bcadd($fallbackCost, '0', 2);
                return [
                    'supplier_offer_id' => null,
                    'vendor_id' => (int)$product->vendor_id,
                    'shop_id' => (int)$product->shop_id,
                    'supplier_name' => $vendor->business_name ?? ($vendor->user->name ?? 'Supplier'),
                    'shop_name' => $shop->name ?? 'Shop Branch',
                    'wholesale_cost' => $cost,
                    'currency' => 'NGN',
                    'source_type' => 'catalog_fallback',
                    'offer_model' => null,
                ];
            }
        }

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

        return strtolower(trim((string) ($shop->lga->name ?? ''))) === 'uyo'
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
