<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('treasury_bank_credit_batches')) {
            Schema::table('treasury_bank_credit_batches', function (Blueprint $table) {
                if (!Schema::hasColumn('treasury_bank_credit_batches', 'gross_collection_amount_kobo')) {
                    $table->unsignedBigInteger('gross_collection_amount_kobo')->default(0);
                }
                if (!Schema::hasColumn('treasury_bank_credit_batches', 'provider_fee_amount_kobo')) {
                    $table->unsignedBigInteger('provider_fee_amount_kobo')->default(0);
                }
            });
        }

        if (!Schema::hasTable('treasury_bank_credit_batch_allocations')) {
            Schema::create('treasury_bank_credit_batch_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('treasury_bank_credit_batch_id')
                    ->constrained('treasury_bank_credit_batches', 'id', 'tbcba_batch_fk')
                    ->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders', 'id', 'tbcba_order_fk')->restrictOnDelete();
                $table->string('gateway_transaction_id', 100)->unique();
                $table->string('payment_reference', 100)->unique();
                $table->unsignedBigInteger('amount_kobo');
                $table->string('currency', 10)->default('NGN');
                $table->string('idempotency_key', 120)->unique();
                $table->foreignId('added_by_admin_id')->constrained('users', 'id', 'tbcba_added_admin_fk');
                $table->timestamp('added_at')->useCurrent();
                $table->timestamps();
                $table->index(['treasury_bank_credit_batch_id', 'order_id'], 'tbcba_batch_order_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // The parent treasury migration removes the referenced batch table;
            // SQLite cannot drop the named foreign key/column independently.
            Schema::dropIfExists('treasury_bank_credit_batch_allocations');
            return;
        }
        Schema::dropIfExists('treasury_bank_credit_batch_allocations');
        if (Schema::hasTable('treasury_bank_credit_batches')) {
            Schema::table('treasury_bank_credit_batches', function (Blueprint $table) {
                if (Schema::hasColumn('treasury_bank_credit_batches', 'provider_fee_amount_kobo')) {
                    $table->dropColumn('provider_fee_amount_kobo');
                }
                if (Schema::hasColumn('treasury_bank_credit_batches', 'gross_collection_amount_kobo')) {
                    $table->dropColumn('gross_collection_amount_kobo');
                }
            });
        }
    }
};
