<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert legacy offer rows into supplier-owned private catalog records.
     *
     * The existing offer-to-canonical-product relationship is retained because
     * that relationship was already explicitly stored in the legacy model.
     * Missing supplier-specific attributes are not invented; each imported row
     * is marked with an audit note so admins can review exact model/variant data.
     */
    public function up(): void
    {
        if (!Schema::hasTable('supplier_offers')
            || !Schema::hasTable('supplier_catalog_products')
            || !Schema::hasColumn('supplier_offers', 'supplier_catalog_product_id')) {
            return;
        }

        DB::table('supplier_offers')
            ->whereNull('supplier_catalog_product_id')
            ->orderBy('id')
            ->chunkById(100, function ($offers): void {
                foreach ($offers as $offer) {
                    $product = DB::table('products')->where('id', $offer->product_id)->first();
                    $vendor = DB::table('vendor_profiles')->where('id', $offer->vendor_id)->first();
                    $shop = DB::table('shops')->where('id', $offer->shop_id)->first();

                    // Do not fabricate ownership or canonical links for orphaned rows.
                    // Those remain detached for explicit manual reconciliation.
                    if (!$product || !$vendor || !$shop || (int) $shop->vendor_id !== (int) $vendor->id
                        || !is_numeric($offer->wholesale_cost)
                        || (float) $offer->wholesale_cost <= 0) {
                        continue;
                    }

                    $now = now();
                    $legacyNote = 'Legacy offer migration #' . $offer->id
                        . ': existing canonical link retained; supplier-specific model/variant fields were not present in the legacy schema and require admin review.';

                    $catalogId = DB::table('supplier_catalog_products')->insertGetId([
                        'supplier_vendor_id' => $vendor->id,
                        'supplier_shop_id' => $shop->id,
                        'product_name' => mb_substr((string) $product->name, 0, 180),
                        'description' => $product->description,
                        'photo_path' => null,
                        'brand' => null,
                        'model' => null,
                        'variant' => null,
                        'condition' => $product->condition ?? null,
                        'package_tier' => $product->package_tier ?? null,
                        'specifications' => null,
                        'source_price' => $offer->wholesale_cost,
                        'is_in_stock' => (bool) $offer->is_available && !(bool) ($offer->is_admin_disabled ?? false),
                        'status' => $offer->deleted_at ? 'retired' : 'active',
                        'canonical_product_id' => $product->id,
                        'linked_by_admin_id' => $offer->availability_confirmed_by,
                        'linked_at' => $offer->availability_confirmed_at ?? $offer->created_at ?? $now,
                        'match_notes' => $legacyNote,
                        'created_at' => $offer->created_at ?? $now,
                        'updated_at' => $now,
                        'deleted_at' => $offer->deleted_at,
                    ]);

                    DB::table('supplier_offers')
                        ->where('id', $offer->id)
                        ->whereNull('supplier_catalog_product_id')
                        ->update(['supplier_catalog_product_id' => $catalogId]);
                }
            });
    }

    /**
     * This data migration is intentionally non-destructive on rollback.
     * Once admins may have reviewed or edited imported records, deleting them
     * during rollback could erase operational audit history. Roll forward instead.
     */
    public function down(): void
    {
        // Intentionally no-op: preserve migrated supplier catalog and audit data.
    }
};
