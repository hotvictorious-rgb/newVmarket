<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Decouples in-shop pickup statuses from doorstep delivery statuses in the orders table.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // MySQL: Convert ENUM to VARCHAR(50) to allow clean, decoupled statuses
            DB::statement("ALTER TABLE orders MODIFY COLUMN order_status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('order_status', 50)->default('pending')->change();
            });
        }

        // Add compound index for fast, segregated querying by fulfillment type and order status
        Schema::table('orders', function (Blueprint $table) {
            try {
                $table->index(['fulfillment_type', 'order_status'], 'idx_orders_fulfillment_status');
            } catch (\Throwable $e) {
                // Index might already exist
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_orders_fulfillment_status');
            } catch (\Throwable $e) {
                // Ignored if index does not exist
            }
        });
    }
};
