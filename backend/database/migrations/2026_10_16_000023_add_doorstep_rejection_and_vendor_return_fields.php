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
        // 1. Enrich order_items with doorstep partial acceptance & rejection outcomes
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('accepted_quantity')->default(0)->after('quantity');
            $table->unsignedInteger('rejected_quantity')->default(0)->after('accepted_quantity');
            $table->string('rejection_category', 50)->nullable()->after('rejected_quantity'); // vendor_issue, transit_damage, customer_change_of_mind
            $table->text('rejection_notes')->nullable()->after('rejection_category');
            $table->string('item_delivery_status', 50)->default('pending')->after('rejection_notes'); // pending, accepted, rejected_in_transit, returned_to_vendor, disputed_by_vendor
        });

        // 2. Enrich return_cases with Doorstep Refusal, Vendor Return OTP & Counter Dispute fields
        Schema::table('return_cases', function (Blueprint $table) {
            $table->string('source', 50)->default('post_delivery_claim')->after('customer_id'); // doorstep_refusal, post_delivery_claim
            $table->string('rejection_category', 50)->nullable()->after('source'); // vendor_issue, transit_damage, customer_change_of_mind
            $table->string('vendor_return_otp', 6)->nullable()->after('rejection_category');
            $table->string('doorstep_photo_path')->nullable()->after('vendor_return_otp');
            $table->timestamp('returned_to_vendor_at')->nullable()->after('doorstep_photo_path');
            $table->foreignId('returned_by_rider_id')->nullable()->after('returned_to_vendor_at')->constrained('users')->nullOnDelete();
            
            // Vendor Counter Dispute Fields
            $table->boolean('vendor_disputed')->default(false)->after('returned_by_rider_id');
            $table->text('vendor_dispute_reason')->nullable()->after('vendor_disputed');
            $table->string('vendor_dispute_photo_path')->nullable()->after('vendor_dispute_reason');
            $table->timestamp('vendor_disputed_at')->nullable()->after('vendor_dispute_photo_path');
            
            // Super Admin Arbitration
            $table->string('admin_ruling', 50)->nullable()->after('vendor_disputed_at'); // uphold_vendor, uphold_customer, platform_absorbed
            $table->timestamp('admin_ruled_at')->nullable()->after('admin_ruling');
            $table->string('cost_allocation', 50)->nullable()->after('admin_ruled_at'); // vendor_100, customer_50_vendor_50, logistics_fleet_100, platform_absorbed
        });

        // 3. Customer Wallet Refund Credits (100% spendable immediately, zero waiting)
        Schema::create('customer_wallet_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('return_case_id')->nullable()->constrained('return_cases')->nullOnDelete();
            $table->decimal('amount', 24, 2);
            $table->string('credit_type', 50)->default('doorstep_refund_credit'); // doorstep_refund_credit, return_refund_credit, admin_credit
            $table->string('status', 30)->default('available'); // available, redeemed, quarantined
            $table->foreignId('spent_on_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_credits');

        Schema::table('return_cases', function (Blueprint $table) {
            $table->dropForeign(['returned_by_rider_id']);
            $table->dropColumn([
                'source',
                'rejection_category',
                'vendor_return_otp',
                'doorstep_photo_path',
                'returned_to_vendor_at',
                'returned_by_rider_id',
                'vendor_disputed',
                'vendor_dispute_reason',
                'vendor_dispute_photo_path',
                'vendor_disputed_at',
                'admin_ruling',
                'admin_ruled_at',
                'cost_allocation',
            ]);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'accepted_quantity',
                'rejected_quantity',
                'rejection_category',
                'rejection_notes',
                'item_delivery_status',
            ]);
        });
    }
};
