<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add cost_price (wholesale sourcing cost) to products
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'cost_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('cost_price', 24, 2)->default(0.00)->after('unit_price');
            });
        }

        // 2. Add cost_price snapshot to order_items
        if (Schema::hasTable('order_items') && !Schema::hasColumn('order_items', 'cost_price')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->decimal('cost_price', 24, 2)->default(0.00)->after('unit_price');
            });
        }

        // 3. Add supplier_payable and platform_gross_margin to orders
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'supplier_payable')) {
                    $table->decimal('supplier_payable', 24, 2)->default(0.00)->after('grand_total');
                }
                if (!Schema::hasColumn('orders', 'platform_gross_margin')) {
                    $table->decimal('platform_gross_margin', 24, 2)->default(0.00)->after('supplier_payable');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'cost_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'cost_price')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'supplier_payable')) {
                    $table->dropColumn('supplier_payable');
                }
                if (Schema::hasColumn('orders', 'platform_gross_margin')) {
                    $table->dropColumn('platform_gross_margin');
                }
            });
        }
    }
};
