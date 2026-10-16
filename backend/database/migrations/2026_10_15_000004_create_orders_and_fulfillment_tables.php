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
        // 1. Two-Phase Checkout Intents (Cryptographic SHA-256 Lock)
        Schema::create('checkout_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fingerprint', 64)->unique();
            $table->enum('fulfillment_type', ['doorstep_delivery', 'in_shop_pickup']);
            $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses');
            $table->foreignId('shop_id')->nullable()->constrained('shops');
            
            // Financial Breakdown via BCMath
            $table->decimal('merchandise_subtotal', 24, 2);
            $table->decimal('delivery_fee', 24, 2)->default(0.00);
            $table->decimal('cashback_applied', 24, 2)->default(0.00);
            $table->decimal('total_payable', 24, 2);
            $table->json('payload_snapshot');
            $table->enum('status', ['pending', 'locked', 'executed', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // 2. Orders Core & Escrow Engine
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('parent_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('vendor_id')->constrained('vendor_profiles');
            $table->foreignId('shop_id')->constrained('shops');
            $table->foreignId('rider_id')->nullable()->constrained('rider_profiles')->nullOnDelete();
            
            $table->enum('fulfillment_type', ['doorstep_delivery', 'in_shop_pickup'])->default('doorstep_delivery');
            $table->enum('order_status', [
                'pending',
                'confirmed',
                'processing',
                'ready_for_pickup',
                'out_for_delivery',
                'delivered',
                'disputed',
                'returned',
                'canceled',
                'failed'
            ])->default('pending');
            $table->enum('payment_status', ['unpaid', 'pending', 'paid', 'refunded', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_channel', 50)->nullable();
            $table->string('paystack_transaction_id', 100)->nullable();
            $table->string('payment_method')->default('paystack');
            $table->string('payment_reference')->nullable()->index();

            // Canonical Route & Directional Lane
            $table->foreignId('origin_lga_id')->constrained('lgas');
            $table->foreignId('destination_lga_id')->constrained('lgas');
            $table->foreignId('delivery_lane_id')->nullable()->constrained('delivery_lanes');

            // Rigorous Financial Invariants (BCMath Delta = ₦0.00)
            $table->decimal('merchandise_subtotal', 24, 2);
            $table->decimal('delivery_fee', 24, 2)->default(0.00);
            $table->decimal('cashback_redeemed', 24, 2)->default(0.00);
            $table->decimal('grand_total', 24, 2);
            $table->decimal('vendor_net_90', 24, 2); // 90% guaranteed to merchant
            $table->decimal('customer_cashback_5', 24, 2); // 5% customer rewards ledger
            $table->decimal('platform_margin_5', 24, 2); // 5% VMarket retained revenue
            $table->decimal('logistics_fee_retained', 24, 2)->default(0.00); // 100% segregated shipping

            // Dual Custody Handshakes & Universal Verification Codes
            $table->string('pickup_verification_code', 6)->nullable(); // Vendor -> Rider collection OTP
            $table->string('verification_code', 6)->nullable(); // Rider -> Customer delivery OTP OR In-Shop Handover OTP
            $table->timestamp('received_at')->nullable(); // Exact customer physical custody stamp
            $table->timestamp('refund_window_expires_at')->nullable(); // received_at + 24 hours

            // Vendor Settlement State Machine
            $table->enum('vendor_settlement_status', [
                'held',
                'eligible',
                'settled',
                'disputed',
                'refunded'
            ])->default('held');
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by_admin_id')->nullable()->constrained('users');
            $table->string('settlement_proof_path')->nullable(); // Mandatory transfer screenshot
            $table->string('delivery_halted_reason')->nullable();
            $table->timestamp('delivery_halted_at')->nullable();

            $table->timestamps();

            $table->index(['vendor_id', 'order_status']);
            $table->index(['customer_id', 'order_status']);
            $table->index(['rider_id', 'order_status']);
            $table->index(['vendor_settlement_status', 'refund_window_expires_at']);
        });

        // 3. Order Items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('product_name');
            $table->decimal('unit_price', 24, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('line_total', 24, 2);
            $table->timestamps();
        });

        // 4. In-Shop Pickup Reservations (Inspect First, Pay Later Engine)
        Schema::create('pickup_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_code', 16)->unique(); // RES-XXXXXXXX
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('vendor_id')->constrained('vendor_profiles');
            $table->foreignId('shop_id')->constrained('shops');
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 24, 2);
            $table->decimal('estimated_total', 24, 2);
            
            $table->enum('status', [
                'reserved',
                'inspected_accepted',
                'inspected_rejected',
                'payment_completed',
                'handed_over',
                'expired',
                'canceled'
            ])->default('reserved');
            
            $table->timestamp('inspected_at')->nullable();
            $table->foreignId('inspected_by_staff_id')->nullable()->constrained('users');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickup_reservations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('checkout_intents');
    }
};
