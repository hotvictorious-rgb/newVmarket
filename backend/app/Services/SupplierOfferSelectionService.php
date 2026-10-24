<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SupplierOffer;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SupplierOfferSelectionService
{
    /**
     * Select the lowest-cost supplier offer with current, explicit availability
     * evidence. Supplier identity is intentionally resolved server-side and
     * must never be accepted from a customer request.
     *
     * Availability expiry is explicitly maintained by the authorized sourcing
     * workflow; this method does not invent a confirmation TTL.
     */
    public function selectLowestCost(Product $product, string $fulfillmentType): ?SupplierOffer
    {
        if (!in_array($fulfillmentType, ['doorstep_delivery', 'in_shop_pickup'], true)) {
            throw new InvalidArgumentException('Unsupported fulfillment type.');
        }

        if (
            !$product->is_approved
            || $product->listing_status !== 'listed'
            || !$product->is_in_stock
            || !$product->isFresh()
        ) {
            return null;
        }

        $now = Carbon::now();

        return $this->eligibleOffersQuery($product, $fulfillmentType, $now)
            ->orderBy('wholesale_cost')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Query all currently eligible offers for a product under the Consolidated Sourcing Law.
     * Enforces active Uyo vendor, KYC, single active shop, unexpired availability,
     * SKU 24-hour unavailability penalty cooldown expired, and fewer than 5 active pending handovers.
     */
    public function getEligibleOffers(Product $product, string $fulfillmentType): \Illuminate\Database\Eloquent\Collection
    {
        if (!in_array($fulfillmentType, ['doorstep_delivery', 'in_shop_pickup'], true)) {
            throw new InvalidArgumentException('Unsupported fulfillment type.');
        }

        if (
            !$product->is_approved
            || $product->listing_status !== 'listed'
            || !$product->is_in_stock
            || !$product->isFresh()
        ) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $now = Carbon::now();

        return $this->eligibleOffersQuery($product, $fulfillmentType, $now)
            ->orderBy('wholesale_cost')
            ->orderBy('id')
            ->get();
    }

    /**
     * Base query for eligible supplier offers.
     */
    public function eligibleOffersQuery(Product $product, string $fulfillmentType, Carbon $now): \Illuminate\Database\Eloquent\Builder
    {
        return SupplierOffer::query()
            ->with(['vendor.user', 'shop'])
            ->where('product_id', $product->id)
            ->where('is_available', true)
            ->where('wholesale_cost', '>', 0)
            ->whereRaw('supplier_offers.wholesale_cost < (select products.unit_price from products where products.id = supplier_offers.product_id)')
            ->whereNotNull('availability_confirmed_at')
            ->where('availability_confirmed_at', '<=', $now)
            ->whereNotNull('availability_confirmed_by')
            ->whereNotNull('availability_expires_at')
            ->where('availability_expires_at', '>', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('unavailability_penalty_expires_at')
                    ->orWhere('unavailability_penalty_expires_at', '<=', $now);
            })
            ->where(function ($catalogLink) {
                // Legacy offers remain readable during migration, but any offer
                // linked to a private supplier record must still match that record.
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
            ->whereHas('vendor', function ($query) {
                $query->whereNull('vendor_profiles.deleted_at')
                    ->where('marketplace_status', 'active')
                    ->where('kyc_status', 'verified')
                    ->whereRaw('(select count(*) from shops as active_supplier_shops where active_supplier_shops.vendor_id = vendor_profiles.id and active_supplier_shops.deleted_at is null and active_supplier_shops.is_active = 1) = 1')
                    ->whereRaw('(select count(*) from order_supplier_assignments as pending_assignments where pending_assignments.supplier_vendor_id = vendor_profiles.id and pending_assignments.status in (\'awaiting_supplier_confirmation\', \'supplier_confirmed\', \'ready_for_collection\')) < 5')
                    ->whereHas('user', function ($userQuery) {
                        $userQuery->where('status', 'active');
                    });
            })
            ->whereHas('shop', function ($query) use ($fulfillmentType) {
                $query->whereNull('shops.deleted_at')
                    ->whereColumn('shops.vendor_id', 'supplier_offers.vendor_id')
                    ->where('is_active', true);

                if ($fulfillmentType === 'in_shop_pickup') {
                    $query->where('pickup_enabled', true);
                }

                $query->whereHas('lga', function ($lga) {
                    $lga->whereRaw('LOWER(TRIM(lgas.name)) = ?', ['uyo'])
                        ->whereHas('state', function ($state) {
                            $state->whereRaw('LOWER(TRIM(states.name)) = ?', ['akwa ibom']);
                        });
                });
            });
    }
}
