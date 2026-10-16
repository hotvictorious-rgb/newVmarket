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
        // 1. Item-Level Return Cases
        Schema::create('return_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 32)->unique(); // RET-YYYYMMDD-XXXX
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('order_id')->constrained('orders'); // Child order or standalone order
            $table->foreignId('parent_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('vendor_id')->constrained('vendor_profiles');
            $table->foreignId('collection_point_id')->nullable()->constrained('logistics_collection_points')->nullOnDelete();

            $table->enum('return_reason', [
                'defective_product',
                'wrong_item_sent',
                'misdescribed_counterfeit',
                'damaged_in_transit',
                'change_of_mind',
                'missing_item',
                'other',
            ]);
            $table->text('customer_description')->nullable();

            $table->enum('status', [
                'requested',
                'under_review',
                'approved_for_return',
                'received_at_hub',
                'inspected',
                'refund_pending',
                'refunded',
                'replacement_completed',
                'rejected',
                'closed',
            ])->default('requested');

            $table->enum('resolution_type', [
                'refund',
                'replacement',
                'repair',
                'rejected',
            ])->default('refund');

            $table->decimal('total_refundable_amount', 24, 2)->default(0.00);
            $table->boolean('refund_delivery_fee')->default(false);
            $table->decimal('delivery_fee_refunded', 24, 2)->default(0.00);
            $table->decimal('refunded_amount', 24, 2)->default(0.00);
            $table->string('paystack_refund_id')->nullable();
            $table->string('refund_proof_path')->nullable();
            $table->string('bank_reference')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['vendor_id', 'status']);
            $table->index(['order_id', 'status']);
        });

        // 2. Individual Order Items for Partial Return (Item-Level Precision)
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_case_id')->constrained('return_cases')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items');
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 24, 2);
            $table->decimal('line_total', 24, 2);
            $table->decimal('allocated_discount', 24, 2)->default(0.00);
            $table->decimal('refundable_amount', 24, 2);

            $table->enum('item_condition', [
                'unopened_sealed',
                'opened_tested',
                'damaged_defective',
                'used_worn',
                'missing_parts',
            ])->default('opened_tested');

            $table->enum('status', [
                'requested',
                'approved',
                'rejected',
                'inspected_accepted',
                'inspected_rejected',
                'refunded',
            ])->default('requested');

            $table->timestamps();

            $table->index(['return_case_id', 'order_item_id']);
        });

        // 3. Return Evidence (Customer photos/videos & packaging labels)
        Schema::create('return_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_case_id')->constrained('return_cases')->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->constrained('users');
            $table->string('uploaded_by_role', 50)->default('customer');
            $table->string('file_path');
            $table->enum('evidence_type', [
                'photo',
                'video',
                'serial_number_photo',
                'receipt',
                'inspection_photo',
            ])->default('photo');
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        // 4. Return Physical Inspections at Collection Hub
        Schema::create('return_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_case_id')->constrained('return_cases')->cascadeOnDelete();
            $table->foreignId('collection_point_id')->constrained('logistics_collection_points');
            $table->foreignId('inspected_by_user_id')->constrained('users');

            $table->enum('condition_rating', [
                'matches_claim',
                'tampered_counterfeit',
                'physical_abuse_by_customer',
                'defective_as_claimed',
                'wrong_serial_number',
                'resalable_sealed',
            ]);
            $table->string('serial_number_or_imei')->nullable();
            $table->boolean('packaging_intact')->default(true);
            $table->text('inspection_notes')->nullable();

            $table->enum('inspection_decision', [
                'accept_for_refund',
                'reject_customer_fault',
                'vendor_fault_confirmed',
                'resalable_change_of_mind',
            ]);

            $table->timestamp('inspected_at');
            $table->timestamps();
        });

        // 5. Vendor Recovery Ledger (For claims after vendor settlement disbursement)
        Schema::create('vendor_recovery_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendor_profiles');
            $table->foreignId('return_case_id')->constrained('return_cases');
            $table->foreignId('order_id')->constrained('orders');
            $table->decimal('amount', 24, 2);
            $table->enum('status', [
                'pending_recovery',
                'recovered_from_payout',
                'written_off',
            ])->default('pending_recovery');
            $table->text('notes')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_recovery_ledgers');
        Schema::dropIfExists('return_inspections');
        Schema::dropIfExists('return_evidence');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('return_cases');
    }
};
