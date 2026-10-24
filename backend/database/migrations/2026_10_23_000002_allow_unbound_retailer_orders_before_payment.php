<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retailer checkout may create a pending order before a supplier is bound.
     * Legacy marketplace rows retain their existing vendor/shop references.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->change();
            $table->foreignId('shop_id')->nullable()->change();
        });
    }

    /**
     * Do not make these columns NOT NULL again if a pending retailer order has
     * no supplier yet. That would destroy the payment-before-sourcing invariant.
     */
    public function down(): void
    {
        $hasUnboundOrders = DB::table('orders')
            ->whereNull('vendor_id')
            ->orWhereNull('shop_id')
            ->exists();

        if ($hasUnboundOrders) {
            throw new RuntimeException(
                'Cannot reverse supplier-unbound order support while any order has a null supplier/shop reference.'
            );
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable(false)->change();
            $table->foreignId('shop_id')->nullable(false)->change();
        });
    }
};
