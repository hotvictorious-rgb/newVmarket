<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_wallet_credits')
            && !Schema::hasColumn('customer_wallet_credits', 'refund_reserved_from_status')) {
            Schema::table('customer_wallet_credits', function (Blueprint $table) {
                $table->string('refund_reserved_from_status', 30)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_wallet_credits')
            && Schema::hasColumn('customer_wallet_credits', 'refund_reserved_from_status')) {
            Schema::table('customer_wallet_credits', function (Blueprint $table) {
                $table->dropColumn('refund_reserved_from_status');
            });
        }
    }
};
