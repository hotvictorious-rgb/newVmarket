<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('manual_bank_transfer_payments')) {
            return;
        }

        Schema::create('manual_bank_transfer_payments', function (Blueprint $table) {
            $table->id();
            // One immutable manual-transfer payment record per order, including rejected submissions.
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('evidence_path', 500);
            $table->string('evidence_mime', 100);
            $table->unsignedBigInteger('evidence_size_bytes');
            $table->char('evidence_sha256', 64);
            $table->string('status', 32)->default('pending_review')->index();
            $table->string('bank_transaction_reference', 150)->nullable()->unique();
            $table->unsignedBigInteger('credited_amount_kobo')->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->json('evidence_history')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_bank_transfer_payments');
    }
};
