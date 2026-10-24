<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('treasury_bank_credit_batches')) {
            Schema::create('treasury_bank_credit_batches', function (Blueprint $table) {
                $table->id();
                $table->string('gateway_provider', 40)->default('paystack');
                $table->string('gateway_settlement_reference', 120)->unique();
                $table->string('bank_credit_reference', 120)->unique();
                $table->string('corporate_account_last4', 4);
                $table->unsignedBigInteger('net_credit_amount_kobo');
                $table->string('currency', 10)->default('NGN');
                $table->date('statement_value_date');
                $table->string('statement_evidence_reference', 255);
                $table->text('reconciliation_notes');
                $table->string('status', 40)->default('pending_second_review')->index();
                $table->foreignId('prepared_by_admin_id')->constrained('users');
                $table->foreignId('confirmed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('prepared_at')->useCurrent();
                $table->timestamp('confirmed_at')->nullable();
                $table->string('idempotency_key', 120)->unique();
                $table->timestamps();
                $table->index(['status', 'statement_value_date']);
            });
        }

        if (Schema::hasTable('retailer_supplier_payout_requests')
            && !Schema::hasColumn('retailer_supplier_payout_requests', 'treasury_bank_credit_batch_id')) {
            Schema::table('retailer_supplier_payout_requests', function (Blueprint $table) {
                $table->foreignId('treasury_bank_credit_batch_id')
                    ->nullable()
                    ->after('supplier_vendor_id')
                    ->constrained('treasury_bank_credit_batches', 'id', 'rspr_treasury_batch_fk')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('retailer_supplier_payout_requests')
            && Schema::hasColumn('retailer_supplier_payout_requests', 'treasury_bank_credit_batch_id')) {
            Schema::table('retailer_supplier_payout_requests', function (Blueprint $table) {
                $table->dropForeign('rspr_treasury_batch_fk');
                $table->dropColumn('treasury_bank_credit_batch_id');
            });
        }
        Schema::dropIfExists('treasury_bank_credit_batches');
    }
};
