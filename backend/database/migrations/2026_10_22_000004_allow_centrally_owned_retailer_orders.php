<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A retailer-model customer order can contain items sourced from several
     * private suppliers. orders.vendor_id is retained for legacy marketplace
     * orders only and must not force a fake single-vendor owner on new orders.
     */
    public function up(): void
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'vendor_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->foreignId('vendor_id')->nullable()->change();
            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendor_profiles')
                ->nullOnDelete();
        });
    }

    /**
     * This migration is forward-first. Refuse rollback rather than inventing
     * a vendor owner for centrally owned retailer orders.
     */
    public function down(): void
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'vendor_id')) {
            return;
        }

        if (DB::table('orders')->whereNull('vendor_id')->exists()) {
            throw new RuntimeException(
                'Cannot restore required orders.vendor_id while centrally owned retailer orders exist without a legacy vendor owner.'
            );
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->foreignId('vendor_id')->nullable(false)->change();
            $table->foreign('vendor_id')->references('id')->on('vendor_profiles');
        });
    }
};
