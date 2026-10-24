<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('logistics_payout_requests')
            && !Schema::hasColumn('logistics_payout_requests', 'treasury_bank_credit_batch_id')) {
            Schema::table('logistics_payout_requests', function (Blueprint $table) {
                $table->foreignId('treasury_bank_credit_batch_id')
                    ->nullable()
                    ->after('logistics_company_id')
                    ->constrained('treasury_bank_credit_batches', 'id', 'lpr_treasury_batch_fk')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('logistics_payout_requests')
            && Schema::hasColumn('logistics_payout_requests', 'treasury_bank_credit_batch_id')) {
            Schema::table('logistics_payout_requests', function (Blueprint $table) {
                $table->dropForeign('lpr_treasury_batch_fk');
                $table->dropColumn('treasury_bank_credit_batch_id');
            });
        }
    }
};
