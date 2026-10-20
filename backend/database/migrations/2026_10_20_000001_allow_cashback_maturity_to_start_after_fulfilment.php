<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_cashback_ledgers')) {
            return;
        }

        Schema::table('customer_cashback_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('customer_cashback_ledgers', 'matures_at')) {
                $table->timestamp('matures_at')->nullable()->change();
            }
            if (Schema::hasColumn('customer_cashback_ledgers', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_cashback_ledgers')) {
            return;
        }

        // Legacy rows created before fulfilment need a deterministic fallback before
        // restoring NOT NULL constraints. This rollback is compatibility-only; new
        // application writes must never use these fallback dates.
        DB::table('customer_cashback_ledgers')
            ->whereNull('matures_at')
            ->orWhereNull('expires_at')
            ->update([
                'matures_at' => now(),
                'expires_at' => now()->addDays(365),
            ]);

        Schema::table('customer_cashback_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('customer_cashback_ledgers', 'matures_at')) {
                $table->timestamp('matures_at')->nullable(false)->change();
            }
            if (Schema::hasColumn('customer_cashback_ledgers', 'expires_at')) {
                $table->timestamp('expires_at')->nullable(false)->change();
            }
        });
    }
};
