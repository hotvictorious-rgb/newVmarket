<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canonical products belong to Victorious MARKET. Supplier/vendor and shop
     * references are legacy/default sourcing hints only and may be absent.
     * Existing references are preserved; supplier eligibility is evaluated
     * through supplier_offers.
     */
    public function up(): void
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->change();
            }
            if (Schema::hasColumn('products', 'shop_id')) {
                $table->foreignId('shop_id')->nullable()->change();
            }
        });
    }

    /**
     * Refuse rollback if retailer-owned products have been created without
     * legacy supplier references. This migration is forward-first and must
     * not silently delete or fabricate ownership data during rollback.
     */
    public function down(): void
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        if (Schema::hasColumn('products', 'vendor_id')
            && DB::table('products')->whereNull('vendor_id')->exists()) {
            throw new RuntimeException(
                'Cannot reverse product supplier decoupling: products.vendor_id contains null values.'
            );
        }

        if (Schema::hasColumn('products', 'shop_id')
            && DB::table('products')->whereNull('shop_id')->exists()) {
            throw new RuntimeException(
                'Cannot reverse product supplier decoupling: products.shop_id contains null values.'
            );
        }

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable(false)->change();
            }
            if (Schema::hasColumn('products', 'shop_id')) {
                $table->foreignId('shop_id')->nullable(false)->change();
            }
        });
    }
};
