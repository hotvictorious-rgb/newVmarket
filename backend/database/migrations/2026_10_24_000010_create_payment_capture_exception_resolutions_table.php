<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_capture_exception_resolutions')) {
            return;
        }

        Schema::create('payment_capture_exception_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_capture_exception_id')->unique()->constrained('payment_capture_exceptions')->cascadeOnDelete();
            $table->string('resolution_type', 40);
            $table->string('status', 40)->default('pending_manual_action')->index();
            $table->string('gateway_provider', 40)->default('paystack');
            $table->string('captured_transaction_id', 100)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->unsignedBigInteger('captured_amount_kobo')->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('refund_reference', 120)->nullable()->unique();
            $table->unsignedBigInteger('refunded_amount_kobo')->nullable();
            $table->foreignId('prepared_by_admin_id')->constrained('users');
            $table->foreignId('confirmed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->text('confirmation_notes')->nullable();
            $table->timestamp('prepared_at')->useCurrent();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->timestamps();

            $table->index(['status', 'resolution_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_capture_exception_resolutions');
    }
};
