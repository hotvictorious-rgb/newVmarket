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
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('vendor_handover_ready_at')->nullable()->after('pickup_verification_code');
            $table->boolean('pickup_confirmed_by_rider')->default(false)->after('vendor_handover_ready_at');
            $table->string('pickup_proof_image')->nullable()->after('pickup_confirmed_by_rider');
            $table->timestamp('picked_up_at')->nullable()->after('pickup_proof_image');
            $table->text('pickup_notes')->nullable()->after('picked_up_at');
            $table->unsignedTinyInteger('pickup_failed_attempts')->default(0)->after('pickup_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'vendor_handover_ready_at',
                'pickup_confirmed_by_rider',
                'pickup_proof_image',
                'picked_up_at',
                'pickup_notes',
                'pickup_failed_attempts',
            ]);
        });
    }
};
