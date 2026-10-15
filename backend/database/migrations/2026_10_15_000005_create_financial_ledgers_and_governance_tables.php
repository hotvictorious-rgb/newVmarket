<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Customer Cashback Rewards Ledger (5% Commercial Split)
        Schema::create('customer_cashback_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->decimal('amount', 24, 2);
            $table->enum('status', [
                'pending_maturation',
                'available',
                'quarantined_dispute',
                'redeemed',
                'expired',
                'forfeited'
            ])->default('pending_maturation');
            
            $table->timestamp('matures_at'); // received_at + 24 hours
            $table->timestamp('expires_at'); // matures_at + 365 days
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        // 2. 24-Hour Return Window Dispute & Arbitration
        Schema::create('disputes_and_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users');
            $table->enum('reason', [
                'wrong_item',
                'damaged_item',
                'materially_defective',
                'different_from_advertised',
                'missing_component'
            ]);
            $table->text('customer_description');
            $table->json('evidence_photos')->nullable();
            $table->enum('status', ['filed', 'under_review', 'approved_refund', 'rejected'])->default('filed');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('users');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        // 3. Payment Transactions & Webhook Idempotency Registry
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('gateway')->default('paystack');
            $table->decimal('amount', 24, 2);
            $table->string('currency', 10)->default('NGN');
            $table->enum('status', ['initialized', 'success', 'failed'])->default('initialized');
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });

        // 4. Immutable Administrative & Financial Audit Logs
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // e.g., 'settlement_disbursed', 'kyc_verified', 'lane_updated'
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('disputes_and_returns');
        Schema::dropIfExists('customer_cashback_ledgers');
    }
};
