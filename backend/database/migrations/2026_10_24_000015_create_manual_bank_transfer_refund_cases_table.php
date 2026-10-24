<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('manual_bank_transfer_refund_cases')) {
            return;
        }

        Schema::create('manual_bank_transfer_refund_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unique('order_id', 'mbt_refund_order_unique');
            $table->foreign('order_id', 'mbt_refund_order_fk')->references('id')->on('orders')->restrictOnDelete();
            $table->unsignedBigInteger('manual_bank_transfer_payment_id');
            // Short explicit names avoid PostgreSQL's 63-byte identifier truncation
            // causing the unique constraint and foreign key names to collide.
            $table->unique('manual_bank_transfer_payment_id', 'mbt_refund_payment_unique');
            $table->foreign('manual_bank_transfer_payment_id', 'mbt_refund_payment_fk')->references('id')->on('manual_bank_transfer_payments')->restrictOnDelete();
            $table->string('idempotency_key', 150)->unique('mbt_refund_idempotency_unique');
            $table->unsignedBigInteger('amount_kobo');
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 40)->default('pending_admin_review')->index('mbt_refund_status_idx');
            $table->string('reason', 1000);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users', indexName: 'mbt_refund_created_admin_fk')->nullOnDelete();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('users', indexName: 'mbt_refund_reviewed_admin_fk')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->string('manual_dispatch_reference', 150)->nullable()->unique('mbt_refund_dispatch_ref_unique');
            $table->string('manual_dispatch_proof_path', 500)->nullable();
            $table->timestamp('manual_dispatch_confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'mbt_refund_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_bank_transfer_refund_cases');
    }
};
