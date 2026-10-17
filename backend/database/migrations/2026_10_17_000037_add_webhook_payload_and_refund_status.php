<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('processed_webhook_events')) {
            Schema::table('processed_webhook_events', function (Blueprint $table) {
                if (!Schema::hasColumn('processed_webhook_events', 'payload')) {
                    $table->longText('payload')->nullable()->after('payload_hash');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'paystack_refund_status')) {
                    $table->string('paystack_refund_status', 32)->nullable()->after('paystack_refund_reference');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('processed_webhook_events')) {
            Schema::table('processed_webhook_events', function (Blueprint $table) {
                if (Schema::hasColumn('processed_webhook_events', 'payload')) {
                    $table->dropColumn('payload');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'paystack_refund_status')) {
                    $table->dropColumn('paystack_refund_status');
                }
            });
        }
    }
};