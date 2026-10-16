<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Authoritative Double-Entry Financial Journal Table
        if (!Schema::hasTable('financial_journal_entries')) {
            Schema::create('financial_journal_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->string('transaction_reference')->index();
                $table->string('account_name'); // 'cash_in_escrow', 'vendor_payable', 'logistics_payable', 'cashback_liability', 'platform_revenue'
                $table->enum('entry_type', ['debit', 'credit']);
                $table->decimal('amount', 24, 2);
                $table->string('currency', 10)->default('NGN');
                $table->string('description');
                $table->timestamps();

                $table->index(['account_name', 'entry_type']);
                $table->index(['transaction_reference', 'order_id']);
            });
        }

        // 2. Add expires_at column on orders table for unpaid checkout expiration
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('paid_at');
                $table->index(['payment_status', 'expires_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'expires_at')) {
                $table->dropIndex(['payment_status', 'expires_at']);
                $table->dropColumn('expires_at');
            }
        });

        Schema::dropIfExists('financial_journal_entries');
    }
};
