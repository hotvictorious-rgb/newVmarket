<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforces immutable tri-party photo audit trails for delivery attempts,
     * item-level doorstep defects, and vendor counter returns.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_attempt_photo_path')->nullable()->after('delivery_attempt_notes');
            $table->json('doorstep_rejection_photos')->nullable()->after('proof_of_delivery_image');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('defect_photo_path')->nullable()->after('rejection_notes');
        });

        Schema::table('return_cases', function (Blueprint $table) {
            $table->string('vendor_return_photo_path')->nullable()->after('doorstep_photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_attempt_photo_path', 'doorstep_rejection_photos']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['defect_photo_path']);
        });

        Schema::table('return_cases', function (Blueprint $table) {
            $table->dropColumn(['vendor_return_photo_path']);
        });
    }
};
