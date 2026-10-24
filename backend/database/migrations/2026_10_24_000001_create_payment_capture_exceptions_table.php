<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_capture_exceptions')) {
            return;
        }

        Schema::create('payment_capture_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('gateway_provider', 40)->default('paystack');
            $table->string('gateway_transaction_id', 100)->nullable();
            $table->string('payment_reference', 100)->nullable()->index();
            $table->unsignedBigInteger('amount_kobo')->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('exception_type', 60);
            $table->string('idempotency_key', 100)->unique();
            $table->string('status', 40)->default('pending_admin_review')->index();
            $table->json('evidence')->nullable();
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['gateway_provider', 'gateway_transaction_id'], 'pce_gw_provider_tx_idx');
            $table->index(['order_id', 'status'], 'pce_order_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_capture_exceptions');
    }
};
