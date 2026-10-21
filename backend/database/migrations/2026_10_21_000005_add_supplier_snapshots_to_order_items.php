<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'supplier_offer_id')) {
                $table->foreignId('supplier_offer_id')->nullable()->after('product_id')
                    ->constrained('supplier_offers')->nullOnDelete();
            }
            if (!Schema::hasColumn('order_items', 'supplier_vendor_id')) {
                $table->foreignId('supplier_vendor_id')->nullable()->after('supplier_offer_id')
                    ->constrained('vendor_profiles')->nullOnDelete();
            }
            if (!Schema::hasColumn('order_items', 'supplier_shop_id')) {
                $table->foreignId('supplier_shop_id')->nullable()->after('supplier_vendor_id')
                    ->constrained('shops')->nullOnDelete();
            }
            if (!Schema::hasColumn('order_items', 'supplier_name_snapshot')) {
                $table->string('supplier_name_snapshot')->nullable()->after('supplier_shop_id');
            }
            if (!Schema::hasColumn('order_items', 'supplier_shop_name_snapshot')) {
                $table->string('supplier_shop_name_snapshot')->nullable()->after('supplier_name_snapshot');
            }
            if (!Schema::hasColumn('order_items', 'supplier_cost_snapshot')) {
                $table->decimal('supplier_cost_snapshot', 24, 2)->nullable()->after('supplier_shop_name_snapshot');
            }
            if (!Schema::hasColumn('order_items', 'supplier_cost_currency')) {
                $table->char('supplier_cost_currency', 3)->default('NGN')->after('supplier_cost_snapshot');
            }
            if (!Schema::hasColumn('order_items', 'supplier_snapshot_at')) {
                $table->timestamp('supplier_snapshot_at')->nullable()->after('supplier_cost_currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            foreach ([
                'supplier_snapshot_at',
                'supplier_cost_currency',
                'supplier_cost_snapshot',
                'supplier_shop_name_snapshot',
                'supplier_name_snapshot',
            ] as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('order_items', 'supplier_shop_id')) {
                $table->dropConstrainedForeignId('supplier_shop_id');
            }
            if (Schema::hasColumn('order_items', 'supplier_vendor_id')) {
                $table->dropConstrainedForeignId('supplier_vendor_id');
            }
            if (Schema::hasColumn('order_items', 'supplier_offer_id')) {
                $table->dropConstrainedForeignId('supplier_offer_id');
            }
        });
    }
};
