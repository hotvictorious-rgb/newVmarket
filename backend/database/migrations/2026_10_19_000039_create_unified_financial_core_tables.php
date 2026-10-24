<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforce strict database invariants for VMARKET financial core.
     */
    public function up(): void
    {
        // 1. Authoritative Financial Operations Table
        if (!Schema::hasTable('financial_operations')) {
            Schema::create('financial_operations', function (Blueprint $table) {
                $table->id();
                $table->string('operation_reference', 100)->unique();
                $table->string('idempotency_key', 100)->unique();
                $table->enum('operation_type', [
                    'customer_refund',
                    'vendor_payout',
                    'logistics_payout',
                    'order_capture',
                    'fee_reversal',
                ])->index();
                $table->string('owner_type', 150)->nullable();
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->unsignedBigInteger('amount_kobo'); // Strict integer kobo, positive invariant
                $table->string('currency', 10)->default('NGN');
                $table->enum('status', [
                    'pending',
                    'holding_reservation',
                    'initiated',
                    'confirmed',
                    'failed',
                    'reversed',
                ])->default('pending')->index();
                $table->string('gateway_provider', 50)->default('paystack');
                $table->string('gateway_reference', 100)->nullable()->index();
                $table->string('lease_token', 100)->nullable();
                $table->timestamp('lease_expires_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();

                $table->index(['owner_type', 'owner_id']);
                $table->index(['operation_type', 'status']);
                $table->index(['status', 'lease_expires_at']);
            });
        }

        // 2. Gateway Attempts Table (Each discrete call to Paystack)
        if (!Schema::hasTable('gateway_attempts')) {
            Schema::create('gateway_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('financial_operation_id')->constrained('financial_operations')->cascadeOnDelete();
                $table->string('gateway_provider', 50)->default('paystack');
                $table->string('endpoint', 100);
                $table->string('attempt_reference', 100)->unique();
                $table->string('gateway_reference', 100)->nullable()->index();
                $table->string('request_hash', 64)->nullable();
                $table->json('request_payload')->nullable();
                $table->integer('response_status_code')->nullable();
                $table->json('response_payload')->nullable();
                $table->enum('outcome', [
                    'pending',
                    'success',
                    'failed',
                    'uncertain_timeout',
                ])->default('pending')->index();
                $table->text('error_message')->nullable();
                $table->timestamp('attempted_at')->useCurrent();
                $table->timestamps();

                $table->index(['financial_operation_id', 'outcome']);
            });
        }

        // 3. Financial Reservations Table (Strict Allocation & Refund Invariant Enforcement)
        if (!Schema::hasTable('financial_reservations')) {
            Schema::create('financial_reservations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('financial_operation_id')->nullable()->constrained('financial_operations')->nullOnDelete();
                $table->string('source_type', 150);
                $table->unsignedBigInteger('source_id');
                $table->unsignedBigInteger('reserved_amount_kobo');
                $table->enum('purpose', [
                    'vendor_payout_hold',
                    'customer_refund_hold',
                    'cashback_lock',
                    'logistics_hold',
                ])->index();
                $table->enum('status', [
                    'active',
                    'committed',
                    'released',
                ])->default('active')->index();
                $table->timestamp('committed_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->index(['source_type', 'source_id', 'status']);
                $table->index(['financial_operation_id', 'status']);
            });
        }

        // 4. Link Financial Journal Entries to Financial Operations
        if (Schema::hasTable('financial_journal_entries')) {
            Schema::table('financial_journal_entries', function (Blueprint $table) {
                if (!Schema::hasColumn('financial_journal_entries', 'financial_operation_id')) {
                    $table->foreignId('financial_operation_id')->nullable()->after('order_id')->constrained('financial_operations')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('financial_journal_entries') && Schema::hasColumn('financial_journal_entries', 'financial_operation_id')) {
            Schema::table('financial_journal_entries', function (Blueprint $table) {
                $table->dropForeign(['financial_operation_id']);
                $table->dropColumn('financial_operation_id');
            });
        }

        Schema::dropIfExists('financial_reservations');
        Schema::dropIfExists('gateway_attempts');
        Schema::dropIfExists('financial_operations');
    }
};