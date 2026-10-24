<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'manual_bank_transfer_account_snapshot')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->json('manual_bank_transfer_account_snapshot')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'manual_bank_transfer_account_snapshot')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('manual_bank_transfer_account_snapshot');
            });
        }
    }
};
