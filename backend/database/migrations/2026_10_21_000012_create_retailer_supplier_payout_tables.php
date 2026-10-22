<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('retailer_supplier_payout_requests')) {
            Schema::create('retailer_supplier_payout_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_vendor_id')->constrained('vendor_profiles')->restrictOnDelete();
                $table->decimal('amount', 24, 2);
                $table->enum('status', ['pending', 'processing', 'uncertain', 'paid', 'rejected', 'failed', 'reversed'])->default('pending')->index();
                $table->string('gateway_status', 32)->nullable()->index();
                $table->string('transfer_reference', 64)->nullable()->unique();
                $table->string('paystack_transfer_code', 64)->nullable()->index();
                $table->string('recipient_code', 128)->nullable();
                $table->string('processing_token', 100)->nullable();
                $table->timestamp('processing_started_at')->nullable();
                $table->string('bank_name');
                $table->string('bank_code')->nullable();
                $table->string('account_number');
                $table->string('account_name');
                $table->foreignId('approved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('disbursed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamps();

                $table->index(['supplier_vendor_id', 'status'], 'rspr_supplier_status_index');
            });
        }

        if (!Schema::hasTable('retailer_supplier_payout_allocations')) {
            Schema::create('retailer_supplier_payout_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('retailer_supplier_payout_request_id')
                    ->constrained('retailer_supplier_payout_requests', 'id', 'rspa_payout_req_foreign')->cascadeOnDelete();
                $table->foreignId('supplier_vendor_id')->constrained('vendor_profiles')->restrictOnDelete();
                $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
                $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
                $table->decimal('amount', 24, 2);
                $table->enum('status', ['allocated', 'settled', 'reversed'])->default('allocated')->index();
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();

                $table->unique(['retailer_supplier_payout_request_id', 'order_item_id'], 'rspr_item_unique');
                $table->index(['supplier_vendor_id', 'order_item_id', 'status'], 'rspr_supplier_item_status_index');
                $table->index(['order_id', 'status'], 'rspr_order_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retailer_supplier_payout_allocations');
        Schema::dropIfExists('retailer_supplier_payout_requests');
    }
};
