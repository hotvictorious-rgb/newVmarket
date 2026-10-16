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
        // 1. Enrich checkout_intents with 100% spendable refund wallet credits applied
        Schema::table('checkout_intents', function (Blueprint $table) {
            $table->decimal('wallet_credit_applied', 24, 2)->default(0.00)->after('cashback_applied');
        });

        // 2. Enrich orders with wallet credit deduction and delivery attempt telemetry
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('wallet_credit_applied', 24, 2)->default(0.00)->after('cashback_redeemed');
            $table->unsignedTinyInteger('delivery_attempt_count')->default(0)->after('verification_code');
            $table->string('delivery_failure_reason', 100)->nullable()->after('delivery_attempt_count');
            $table->text('delivery_attempt_notes')->nullable()->after('delivery_failure_reason');
            $table->timestamp('last_delivery_attempt_at')->nullable()->after('delivery_attempt_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'wallet_credit_applied',
                'delivery_attempt_count',
                'delivery_failure_reason',
                'delivery_attempt_notes',
                'last_delivery_attempt_at',
            ]);
        });

        Schema::table('checkout_intents', function (Blueprint $table) {
            $table->dropColumn('wallet_credit_applied');
        });
    }
};
