<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_recovery_ledgers', function (Blueprint $table) {
            $table->string('idempotency_key', 191)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_recovery_ledgers', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
