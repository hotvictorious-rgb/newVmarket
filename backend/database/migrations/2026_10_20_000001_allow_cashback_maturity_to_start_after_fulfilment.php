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

        // Reconcile legacy pending/quarantined lots to the authoritative order clock.
        // Unfulfilled orders have no claim-window expiry, so their cashback clocks remain unset.
        DB::table('customer_cashback_ledgers')
            ->whereIn('status', ['pending_maturation', 'quarantined_dispute'])
            ->orderBy('id')
            ->chunkById(200, function ($lots): void {
                foreach ($lots as $lot) {
                    $windowExpiry = DB::table('orders')
                        ->where('id', $lot->order_id)
                        ->value('refund_window_expires_at');

                    if (!$windowExpiry) {
                        DB::table('customer_cashback_ledgers')
                            ->where('id', $lot->id)
                            ->update(['matures_at' => null, 'expires_at' => null]);
                        continue;
                    }

                    $maturesAt = Carbon::parse($windowExpiry)->addHours(24);
                    DB::table('customer_cashback_ledgers')
                        ->where('id', $lot->id)
                        ->update([
                            'matures_at' => $maturesAt,
                            'expires_at' => $maturesAt->copy()->addDays(365),
                        ]);
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
