<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('checkout_idempotency_key', 100)->nullable();
            $table->string('checkout_request_hash', 64)->nullable();
            $table->string('paystack_initialization_status', 24)->default('not_started');
            $table->text('paystack_authorization_url')->nullable();
            $table->string('paystack_access_code', 255)->nullable();
            $table->unique(['customer_id', 'checkout_idempotency_key'], 'orders_customer_checkout_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_customer_checkout_idempotency_unique');
            $table->dropColumn([
                'checkout_idempotency_key',
                'checkout_request_hash',
                'paystack_initialization_status',
                'paystack_authorization_url',
                'paystack_access_code',
            ]);
        });
    }
};
