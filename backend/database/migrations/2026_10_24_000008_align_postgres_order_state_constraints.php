<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original PostgreSQL enum CHECK constraints survived later column
     * conversions to strings. They no longer represent the states written by
     * the current order and settlement state machines.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || !Schema::hasTable('orders')) {
            return;
        }

        if (Schema::hasColumn('orders', 'order_status')) {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_order_status_check');
            DB::statement("
                ALTER TABLE orders
                ADD CONSTRAINT orders_order_status_check
                CHECK (order_status IN (
                    'pending', 'confirmed', 'processing', 'ready_for_dispatch',
                    'ready_for_pickup', 'ready_for_counter_pickup', 'out_for_delivery',
                    'delivered', 'picked_up', 'paid_stock_unavailable', 'disputed', 'returned', 'cancelled',
                    'canceled', 'failed'
                ))
            ");
        }

        if (Schema::hasColumn('orders', 'vendor_settlement_status')) {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_vendor_settlement_status_check');
            DB::statement("
                ALTER TABLE orders
                ADD CONSTRAINT orders_vendor_settlement_status_check
                CHECK (vendor_settlement_status IN (
                    'held', 'eligible', 'settled', 'disputed', 'refunded',
                    'pending', 'processing', 'paid', 'failed', 'reversed',
                    'cancelled', 'canceled', 'approved', 'rejected',
                    'on_hold', 'payout_pending', 'awaiting_admin_approval',
                    'refund_pending', 'settlement_frozen_dispute', 'not_required', 'paid_out'
                ))
            ");
        }
    }

    public function down(): void
    {
        // Keep the compatibility constraints in place. Reinstating the obsolete
        // constraints would make valid current order states impossible to store.
    }
};
