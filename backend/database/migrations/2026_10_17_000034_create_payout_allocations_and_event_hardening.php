<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add dedicated indexed transfer reference & claim columns to vendor_payout_requests
        if (Schema::hasTable('vendor_payout_requests')) {
            Schema::table('vendor_payout_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('vendor_payout_requests', 'transfer_reference')) {
                    $table->string('transfer_reference', 64)->nullable()->unique()->after('status');
                }
                if (!Schema::hasColumn('vendor_payout_requests', 'paystack_transfer_code')) {
                    $table->string('paystack_transfer_code', 64)->nullable()->index()->after('transfer_reference');
                }
                if (!Schema::hasColumn('vendor_payout_requests', 'processing_token')) {
                    $table->string('processing_token', 64)->nullable()->after('paystack_transfer_code');
                }
                if (!Schema::hasColumn('vendor_payout_requests', 'processing_started_at')) {
                    $table->timestamp('processing_started_at')->nullable()->after('processing_token');
                }
            });
        }

        // 2. Add dedicated indexed transfer reference & claim columns to logistics_payout_requests
        if (Schema::hasTable('logistics_payout_requests')) {
            Schema::table('logistics_payout_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('logistics_payout_requests', 'transfer_reference')) {
                    $table->string('transfer_reference', 64)->nullable()->unique()->after('status');
                }
                if (!Schema::hasColumn('logistics_payout_requests', 'paystack_transfer_code')) {
                    $table->string('paystack_transfer_code', 64)->nullable()->index()->after('transfer_reference');
                }
                if (!Schema::hasColumn('logistics_payout_requests', 'processing_token')) {
                    $table->string('processing_token', 64)->nullable()->after('paystack_transfer_code');
                }
                if (!Schema::hasColumn('logistics_payout_requests', 'processing_started_at')) {
                    $table->timestamp('processing_started_at')->nullable()->after('processing_token');
                }
            });
        }

        // 3. Add concurrency claim token to return_cases
        if (Schema::hasTable('return_cases')) {
            Schema::table('return_cases', function (Blueprint $table) {
                if (!Schema::hasColumn('return_cases', 'refund_claim_token')) {
                    $table->string('refund_claim_token', 64)->nullable()->after('paystack_refund_id');
                }
                if (!Schema::hasColumn('return_cases', 'refund_claimed_at')) {
                    $table->timestamp('refund_claimed_at')->nullable()->after('refund_claim_token');
                }
            });
        }

        // 4. Create immutable vendor_payout_allocations table
        if (!Schema::hasTable('vendor_payout_allocations')) {
            Schema::create('vendor_payout_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_payout_request_id')->constrained('vendor_payout_requests')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->decimal('allocated_amount', 24, 2);
                $table->timestamps();

                $table->unique(['vendor_payout_request_id', 'order_id'], 'vpa_payout_order_unique');
                $table->index('order_id');
            });
        }

        // 5. Create processed_webhook_events table for strict idempotency
        if (!Schema::hasTable('processed_webhook_events')) {
            Schema::create('processed_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_id', 128)->unique();
                $table->string('event_type', 64);
                $table->string('payload_hash', 64)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index('event_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_webhook_events');
        Schema::dropIfExists('vendor_payout_allocations');
    }
};
