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

        return SupplierOffer::query()
            ->with(['vendor.user', 'shop'])
            ->where('product_id', $product->id)
            ->where('is_available', true)
            ->where('wholesale_cost', '>', 0)
            ->whereNotNull('availability_confirmed_at')
            ->where('availability_confirmed_at', '<=', $now)
            ->whereNotNull('availability_confirmed_by')
            ->whereNotNull('availability_expires_at')
            ->where('availability_expires_at', '>', $now)
            ->whereHas('vendor', function ($query) {
                $query->whereNull('vendor_profiles.deleted_at')
                    ->where('marketplace_status', 'active')
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
            })
            ->orderBy('wholesale_cost')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }
}
