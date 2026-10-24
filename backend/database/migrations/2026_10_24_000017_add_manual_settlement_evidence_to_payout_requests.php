<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('retailer_supplier_payout_requests')) {
            Schema::table('retailer_supplier_payout_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('retailer_supplier_payout_requests', 'settlement_proof_path')) {
                    $table->string('settlement_proof_path', 500)->nullable()->after('disbursed_at');
                }
                if (!Schema::hasColumn('retailer_supplier_payout_requests', 'bank_transaction_reference')) {
                    $table->string('bank_transaction_reference', 150)->nullable()->unique('rspr_bank_tx_ref_unique')->after('settlement_proof_path');
                }
            });
        }

        if (Schema::hasTable('logistics_payout_requests')) {
            Schema::table('logistics_payout_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('logistics_payout_requests', 'bank_transaction_reference')) {
                    $table->string('bank_transaction_reference', 150)->nullable()->unique('lpr_bank_tx_ref_unique')->after('settlement_proof_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('retailer_supplier_payout_requests')) {
            Schema::table('retailer_supplier_payout_requests', function (Blueprint $table) {
                $table->dropUnique('rspr_bank_tx_ref_unique');
                $table->dropColumn(['settlement_proof_path', 'bank_transaction_reference']);
            });
        }

        if (Schema::hasTable('logistics_payout_requests')) {
            Schema::table('logistics_payout_requests', function (Blueprint $table) {
                $table->dropUnique('lpr_bank_tx_ref_unique');
                $table->dropColumn('bank_transaction_reference');
            });
        }
    }
};
