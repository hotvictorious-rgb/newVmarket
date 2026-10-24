<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('financial_operations', 'gateway_reference')) {
            Schema::table('financial_operations', function (Blueprint $table) {
                $table->string('gateway_reference', 100)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // Retain the nullable reconciliation reference on rollback so webhook
        // evidence is not destroyed by an application rollback.
    }
};
