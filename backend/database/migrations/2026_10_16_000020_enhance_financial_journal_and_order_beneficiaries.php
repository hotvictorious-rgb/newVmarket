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
        // 1. Add event_id and polymorphic beneficiary to financial_journal_entries
        Schema::table('financial_journal_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('financial_journal_entries', 'event_id')) {
                $table->string('event_id')->nullable()->index()->after('transaction_reference');
            }
            if (!Schema::hasColumn('financial_journal_entries', 'beneficiary_type')) {
                $table->string('beneficiary_type')->nullable()->after('event_id');
                $table->unsignedBigInteger('beneficiary_id')->nullable()->after('beneficiary_type');
                $table->index(['beneficiary_type', 'beneficiary_id'], 'fje_beneficiary_index');
            }
        });

        // 2. Add frozen logistics_company_id to orders table
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'logistics_company_id')) {
                $table->foreignId('logistics_company_id')
                    ->nullable()
                    ->after('delivery_lane_id')
                    ->constrained('logistics_company_profiles')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'logistics_company_id')) {
                $table->dropForeign(['logistics_company_id']);
                $table->dropColumn('logistics_company_id');
            }
        });

        Schema::table('financial_journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('financial_journal_entries', 'beneficiary_type')) {
                $table->dropIndex('fje_beneficiary_index');
                $table->dropColumn(['beneficiary_type', 'beneficiary_id']);
            }
            if (Schema::hasColumn('financial_journal_entries', 'event_id')) {
                $table->dropIndex(['event_id']);
                $table->dropColumn('event_id');
            }
        });
    }
};
