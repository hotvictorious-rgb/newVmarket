<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashback_recovery_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('return_case_id')->constrained('return_cases')->cascadeOnDelete();
            $table->decimal('amount', 24, 2);
            $table->decimal('recovered_amount', 24, 2)->default('0.00');
            $table->string('status', 32)->default('pending_recovery');
            $table->string('idempotency_key', 191)->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashback_recovery_ledgers');
    }
};
