<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_journal_entries')
            && !Schema::hasColumn('financial_journal_entries', 'idempotency_key')) {
            Schema::table('financial_journal_entries', function (Blueprint $table) {
                $table->string('idempotency_key', 150)->nullable()->unique('fje_idempotency_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('financial_journal_entries')
            && Schema::hasColumn('financial_journal_entries', 'idempotency_key')) {
            Schema::table('financial_journal_entries', function (Blueprint $table) {
                $table->dropUnique('fje_idempotency_key_unique');
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
