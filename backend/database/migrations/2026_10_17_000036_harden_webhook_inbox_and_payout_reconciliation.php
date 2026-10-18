<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Upgrade processed_webhook_events to a full Transactional Inbox State Machine
        if (Schema::hasTable('processed_webhook_events')) {
            Schema::table('processed_webhook_events', function (Blueprint $table) {
                if (!Schema::hasColumn('processed_webhook_events', 'status')) {
                    $table->string('status', 32)->default('completed')->after('payload_hash');
                }
                if (!Schema::hasColumn('processed_webhook_events', 'error_reason')) {
                    $table->text('error_reason')->nullable()->after('status');
                }
                if (!Schema::hasColumn('processed_webhook_events', 'attempts')) {
                    $table->unsignedInteger('attempts')->default(1)->after('error_reason');
                }
            });
        }

        // 2. Add explicit allocation lifecycle status & audit timestamp to vendor_payout_allocations
        if (Schema::hasTable('vendor_payout_allocations')) {
            Schema::table('vendor_payout_allocations', function (Blueprint $table) {
                if (!Schema::hasColumn('vendor_payout_allocations', 'status')) {
                    $table->enum('status', ['allocated', 'reversed'])->default('allocated')->after('allocated_amount');
                }
                if (!Schema::hasColumn('vendor_payout_allocations', 'reversed_at')) {
                    $table->timestamp('reversed_at')->nullable()->after('status');
                }
            });
        }

        // 3. Add gateway_status to vendor_payout_requests
        if (Schema::hasTable('vendor_payout_requests')) {
            Schema::table('vendor_payout_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('vendor_payout_requests', 'gateway_status')) {
                    $table->string('gateway_status', 64)->nullable()->after('status');
                }
            });
        }

        // 4. Add gateway_status to logistics_payout_requests
        if (Schema::hasTable('logistics_payout_requests')) {
            Schema::table('logistics_payout_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('logistics_payout_requests', 'gateway_status')) {
                    $table->string('gateway_status', 64)->nullable()->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('processed_webhook_events')) {
            Schema::table('processed_webhook_events', function (Blueprint $table) {
                $columns = array_filter(['status', 'error_reason', 'attempts'], fn($col) => Schema::hasColumn('processed_webhook_events', $col));
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('vendor_payout_allocations')) {
            Schema::table('vendor_payout_allocations', function (Blueprint $table) {
                $columns = array_filter(['status', 'reversed_at'], fn($col) => Schema::hasColumn('vendor_payout_allocations', $col));
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('vendor_payout_requests') && Schema::hasColumn('vendor_payout_requests', 'gateway_status')) {
            Schema::table('vendor_payout_requests', function (Blueprint $table) {
                $table->dropColumn('gateway_status');
            });
        }

        if (Schema::hasTable('logistics_payout_requests') && Schema::hasColumn('logistics_payout_requests', 'gateway_status')) {
            Schema::table('logistics_payout_requests', function (Blueprint $table) {
                $table->dropColumn('gateway_status');
            });
        }
    }
};