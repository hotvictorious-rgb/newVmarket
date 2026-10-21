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
                $table->string('idempotency_key', 100)->nullable();
            });
        }

        // A partially applied/manual schema may already have the column without
        // the unique index. Column existence alone is not sufficient protection
        // against concurrent duplicate journal writes.
        $hasUniqueIdempotencyIndex = collect(Schema::getIndexes('financial_journal_entries'))
            ->contains(function (array $index): bool {
                return !empty($index['unique'])
                    && ($index['columns'] ?? []) === ['idempotency_key'];
            });
        if (!$hasUniqueIdempotencyIndex) {
            Schema::table('financial_journal_entries', function (Blueprint $table) {
                $table->unique('idempotency_key', 'financial_journal_entries_idempotency_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('financial_journal_entries')
            || !Schema::hasColumn('financial_journal_entries', 'idempotency_key')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('financial_journal_entries'))
            ->filter(function (array $index): bool {
                return !empty($index['unique'])
                    && ($index['columns'] ?? []) === ['idempotency_key'];
            });
        foreach ($indexes as $index) {
            Schema::table('financial_journal_entries', function (Blueprint $table) use ($index) {
                $table->dropUnique($index['name']);
            });
        }

        Schema::table('financial_journal_entries', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
    }
};
