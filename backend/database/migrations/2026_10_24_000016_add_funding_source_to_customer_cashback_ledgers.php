<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_cashback_ledgers')) {
            Schema::table('customer_cashback_ledgers', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_cashback_ledgers', 'funding_source')) {
                    $table->string('funding_source', 32)->default('paystack')->after('amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_cashback_ledgers')) {
            Schema::table('customer_cashback_ledgers', function (Blueprint $table) {
                if (Schema::hasColumn('customer_cashback_ledgers', 'funding_source')) {
                    $table->dropColumn('funding_source');
                }
            });
        }
    }
};
