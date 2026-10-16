<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'payment_channel')) {
                $table->string('payment_channel', 50)->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('orders', 'paystack_transaction_id')) {
                $table->string('paystack_transaction_id', 100)->nullable()->after('payment_channel');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
            if (Schema::hasColumn('orders', 'payment_channel')) {
                $table->dropColumn('payment_channel');
            }
            if (Schema::hasColumn('orders', 'paystack_transaction_id')) {
                $table->dropColumn('paystack_transaction_id');
            }
        });
    }
};
