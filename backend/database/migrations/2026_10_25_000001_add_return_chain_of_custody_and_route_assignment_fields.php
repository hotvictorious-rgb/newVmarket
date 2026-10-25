<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforces physical custody tracking, OTP verification, photo proofs,
     * route-based rider assignment, and zero-fee return movements.
     */
    public function up(): void
    {
        Schema::table('return_cases', function (Blueprint $table) {
            $table->string('customer_return_otp', 6)->nullable()->after('rejection_category');
            $table->string('customer_collection_photo_path')->nullable()->after('customer_return_otp');
            $table->timestamp('customer_collected_at')->nullable()->after('customer_collection_photo_path');
            $table->text('collection_failed_reason')->nullable()->after('customer_collected_at');
            $table->timestamp('collection_failed_at')->nullable()->after('collection_failed_reason');
            $table->string('destination_type', 30)->default('hub')->after('collection_failed_at');
            $table->string('hub_receipt_otp', 6)->nullable()->after('destination_type');
            $table->string('hub_received_photo_path')->nullable()->after('hub_receipt_otp');
            $table->timestamp('hub_received_at')->nullable()->after('hub_received_photo_path');
            $table->text('supplier_handover_failed_reason')->nullable()->after('hub_received_at');
            $table->timestamp('supplier_handover_failed_at')->nullable()->after('supplier_handover_failed_reason');
            $table->string('assigned_logistics_route_notes')->nullable()->after('supplier_handover_failed_at');
            $table->decimal('return_delivery_fee_charged', 24, 2)->default(0.00)->after('assigned_logistics_route_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_cases', function (Blueprint $table) {
            $table->dropColumn([
                'customer_return_otp',
                'customer_collection_photo_path',
                'customer_collected_at',
                'collection_failed_reason',
                'collection_failed_at',
                'destination_type',
                'hub_receipt_otp',
                'hub_received_photo_path',
                'hub_received_at',
                'supplier_handover_failed_reason',
                'supplier_handover_failed_at',
                'assigned_logistics_route_notes',
                'return_delivery_fee_charged',
            ]);
        });
    }
};
