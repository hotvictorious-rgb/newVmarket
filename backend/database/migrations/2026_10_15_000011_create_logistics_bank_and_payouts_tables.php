<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Institutional Bank Accounts & Corporate Withdrawal Engine for Logistics Companies.
     */
    public function up(): void
    {
        Schema::table('logistics_company_profiles', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('wallet_balance');
            $table->string('bank_code')->nullable()->after('bank_name');
            $table->string('account_number', 10)->nullable()->after('bank_code');
            $table->string('account_name')->nullable()->after('account_number');
            $table->timestamp('bank_updated_at')->nullable()->after('account_name');
        });

        Schema::create('logistics_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_company_id')->constrained('logistics_company_profiles')->cascadeOnDelete();
            $table->decimal('amount', 24, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_name');
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disbursed_at')->nullable();
            $table->string('settlement_proof_path')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['logistics_company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistics_payout_requests');

        Schema::table('logistics_company_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'bank_name',
                'bank_code',
                'account_number',
                'account_name',
                'bank_updated_at',
            ]);
        });
    }
};
