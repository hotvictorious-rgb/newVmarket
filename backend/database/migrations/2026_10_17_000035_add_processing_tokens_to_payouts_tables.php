<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_payout_requests')) {
            Schema::table('vendor_payout_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('vendor_payout_requests', 'processing_token')) {
                    $table->string('processing_token', 64)->nullable()->after('paystack_transfer_code');
                }
                if (!Schema::hasColumn('vendor_payout_requests', 'processing_started_at')) {
                    $table->timestamp('processing_started_at')->nullable()->after('processing_token');
                }
            });
        }

        if (Schema::hasTable('logistics_payout_requests')) {
            Schema::table('logistics_payout_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('logistics_payout_requests', 'processing_token')) {
                    $table->string('processing_token', 64)->nullable()->after('paystack_transfer_code');
                }
                if (!Schema::hasColumn('logistics_payout_requests', 'processing_started_at')) {
                    $table->timestamp('processing_started_at')->nullable()->after('processing_token');
                }
            });
        }
    }

    public function down(): void
    {
    }
};
