<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Expands orders.vendor_settlement_status from MySQL ENUM to VARCHAR(50)
     * to support runtime states (refund_pending, settlement_frozen_dispute, etc.)
     * without strict-mode MySQL Error 1265 truncation.
     */
    public function up(): void
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'vendor_settlement_status')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN vendor_settlement_status VARCHAR(50) NOT NULL DEFAULT 'held'");
        } else {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('vendor_settlement_status', 50)->default('held')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting to the original 5-value ENUM would truncate valid runtime statuses
        // written by the application state machine. Leave VARCHAR column in place.
    }
};
