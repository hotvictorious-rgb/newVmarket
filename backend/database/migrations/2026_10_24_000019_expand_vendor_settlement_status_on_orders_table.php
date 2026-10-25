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
     *
     * Forward-Only Reversibility Rationale:
     * Restoring MySQL's legacy 5-value ENUM ('held', 'eligible', 'settled', 'disputed', 'refunded')
     * during rollback would trigger strict-mode Data Truncation (Error 1265) on any live or
     * historical orders bearing valid runtime statuses ('refund_pending', 'settlement_frozen_dispute',
     * etc.). Following the architectural precedent in 2026_10_19_000041 (order_status) and
     * 2026_10_24_000008 (Postgres state constraints), this column remains VARCHAR(50) on rollback
     * to preserve data integrity and prevent unrecoverable schema downgrade errors.
     */
    public function down(): void
    {
        // Intentionally forward-only to prevent MySQL Error 1265 data truncation on valid order statuses.
    }
};
