<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_supplier_assignments', function (Blueprint $table) {
            $table->string('supplier_pickup_code', 6)->nullable()->unique();
            $table->timestamp('handover_ready_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->string('collection_proof_image_path')->nullable();
            $table->text('collection_notes')->nullable();
            $table->unsignedSmallInteger('pickup_failed_attempts')->default(0);
            $table->timestamp('pickup_locked_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_supplier_assignments', function (Blueprint $table) {
            $table->dropUnique(['supplier_pickup_code']);
            $table->dropColumn([
                'supplier_pickup_code',
                'handover_ready_at',
                'collected_at',
                'collection_proof_image_path',
                'collection_notes',
                'pickup_failed_attempts',
                'pickup_locked_until',
            ]);
        });
    }
};
