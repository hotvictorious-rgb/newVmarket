<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A retailer-model customer order can contain items sourced from several
     * private suppliers. The legacy order-level vendor/shop pointers are
     * retained for historical marketplace orders only and must not force a
     * fake single-vendor or single-shop owner on new customer orders.
     */
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'vendor_id')) {
                $table->dropForeign(['vendor_id']);
                $table->foreignId('vendor_id')->nullable()->change();
                $table->foreign('vendor_id')
                    ->references('id')
                    ->on('vendor_profiles')
                    ->nullOnDelete();
            }

            if (Schema::hasColumn('orders', 'shop_id')) {
                $table->dropForeign(['shop_id']);
                $table->foreignId('shop_id')->nullable()->change();
                $table->foreign('shop_id')
                    ->references('id')
                    ->on('shops')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * This migration is forward-first. Refuse rollback rather than inventing
     * a legacy vendor/shop owner for centrally owned retailer orders.
     */
    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        if ((Schema::hasColumn('orders', 'vendor_id') && DB::table('orders')->whereNull('vendor_id')->exists())
            || (Schema::hasColumn('orders', 'shop_id') && DB::table('orders')->whereNull('shop_id')->exists())) {
            throw new RuntimeException(
                'Cannot restore required orders.vendor_id/orders.shop_id while centrally owned retailer orders exist without a legacy owner.'
            );
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'vendor_id')) {
                $table->dropForeign(['vendor_id']);
                $table->foreignId('vendor_id')->nullable(false)->change();
                $table->foreign('vendor_id')->references('id')->on('vendor_profiles');
            }

            if (Schema::hasColumn('orders', 'shop_id')) {
                $table->dropForeign(['shop_id']);
                $table->foreignId('shop_id')->nullable(false)->change();
                $table->foreign('shop_id')->references('id')->on('shops');
            }
        });
    }
};
