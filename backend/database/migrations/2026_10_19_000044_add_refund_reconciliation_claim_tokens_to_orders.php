<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'refund_reconciliation_token')) {
                    $table->string('refund_reconciliation_token', 64)->nullable()->index();
                }
                if (!Schema::hasColumn('orders', 'refund_reconciliation_claimed_at')) {
                    $table->timestamp('refund_reconciliation_claimed_at')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'refund_reconciliation_token')) {
                    $table->dropIndex(['refund_reconciliation_token']);
                    $table->dropColumn('refund_reconciliation_token');
                }
                if (Schema::hasColumn('orders', 'refund_reconciliation_claimed_at')) {
                    $table->dropIndex(['refund_reconciliation_claimed_at']);
                    $table->dropColumn('refund_reconciliation_claimed_at');
                }
            });
        }
    }
};
