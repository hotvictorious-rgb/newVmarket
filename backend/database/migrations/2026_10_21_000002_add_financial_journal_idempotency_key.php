<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal writers already persist idempotency_key, but the base journal schema
     * only guarantees transaction_reference/event_id. Add a database-enforced
     * idempotency fence so recovery retries cannot create duplicate ledger effects.
     */
    public function up(): void
    {
        if (!Schema::hasTable('financial_journal_entries')) {
            return;
        }

        if (!Schema::hasColumn('financial_journal_entries', 'idempotency_key')) {
            Schema::table('financial_journal_entries', function (Blueprint $table) {
                $table->string('idempotency_key', 100)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('financial_journal_entries')
            || !Schema::hasColumn('financial_journal_entries', 'idempotency_key')) {
            return;
        }

        Schema::table('financial_journal_entries', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
