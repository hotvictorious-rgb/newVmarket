<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'fulfillment_supplier_offer_id')) {
                $table->foreignId('fulfillment_supplier_offer_id')->nullable()->after('supplier_offer_id')
                    ->constrained('supplier_offers')->nullOnDelete();
            }
            if (!Schema::hasColumn('order_items', 'fulfillment_supplier_vendor_id')) {
                $table->foreignId('fulfillment_supplier_vendor_id')->nullable()->after('fulfillment_supplier_offer_id')
                    ->constrained('vendor_profiles')->nullOnDelete();
            }
            if (!Schema::hasColumn('order_items', 'fulfillment_supplier_shop_id')) {
                $table->foreignId('fulfillment_supplier_shop_id')->nullable()->after('fulfillment_supplier_vendor_id')
                    ->constrained('shops')->nullOnDelete();
            }
            if (!Schema::hasColumn('order_items', 'fulfillment_supplier_name_snapshot')) {
                $table->string('fulfillment_supplier_name_snapshot')->nullable()->after('fulfillment_supplier_shop_id');
            }
            if (!Schema::hasColumn('order_items', 'fulfillment_supplier_shop_name_snapshot')) {
                $table->string('fulfillment_supplier_shop_name_snapshot')->nullable()->after('fulfillment_supplier_name_snapshot');
            }
            if (!Schema::hasColumn('order_items', 'fulfillment_supplier_cost_snapshot')) {
                $table->decimal('fulfillment_supplier_cost_snapshot', 24, 2)->nullable()->after('fulfillment_supplier_shop_name_snapshot');
            }
            if (!Schema::hasColumn('order_items', 'fulfillment_supplier_snapshot_at')) {
                $table->timestamp('fulfillment_supplier_snapshot_at')->nullable()->after('fulfillment_supplier_cost_snapshot');
            }
            $table->index(
                ['order_id', 'fulfillment_supplier_vendor_id', 'fulfillment_supplier_shop_id'],
                'order_items_fulfillment_supplier_idx'
            );
        });

        // Existing orders start with the capture-time supplier as their operational
        // supplier. Future post-payment recovery changes only fulfillment_* fields.
        DB::table('order_items')->whereNull('fulfillment_supplier_vendor_id')->update([
            'fulfillment_supplier_offer_id' => DB::raw('supplier_offer_id'),
            'fulfillment_supplier_vendor_id' => DB::raw('supplier_vendor_id'),
            'fulfillment_supplier_shop_id' => DB::raw('supplier_shop_id'),
            'fulfillment_supplier_name_snapshot' => DB::raw('supplier_name_snapshot'),
            'fulfillment_supplier_shop_name_snapshot' => DB::raw('supplier_shop_name_snapshot'),
            'fulfillment_supplier_cost_snapshot' => DB::raw('supplier_cost_snapshot'),
            'fulfillment_supplier_snapshot_at' => DB::raw('supplier_snapshot_at'),
        ]);

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'fulfillment_supplier_cost_total_snapshot')) {
                $table->decimal('fulfillment_supplier_cost_total_snapshot', 24, 2)
                    ->nullable()->after('supplier_cost_total_snapshot');
            }
        });

        DB::table('orders')->whereNull('fulfillment_supplier_cost_total_snapshot')->update([
            'fulfillment_supplier_cost_total_snapshot' => DB::raw('supplier_cost_total_snapshot'),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'fulfillment_supplier_cost_total_snapshot')) {
                $table->dropColumn('fulfillment_supplier_cost_total_snapshot');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_fulfillment_supplier_idx');
            foreach ([
                'fulfillment_supplier_snapshot_at',
                'fulfillment_supplier_cost_snapshot',
                'fulfillment_supplier_shop_name_snapshot',
                'fulfillment_supplier_name_snapshot',
            ] as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
            foreach ([
                'fulfillment_supplier_shop_id',
                'fulfillment_supplier_vendor_id',
                'fulfillment_supplier_offer_id',
            ] as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};
