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
            $table->timestamp('rider_arrived_at')->nullable()->after('verification_code');
            $table->boolean('delivery_confirmed_by_customer')->default(false)->after('rider_arrived_at');
            $table->string('proof_of_delivery_image')->nullable()->after('delivery_confirmed_by_customer');
            $table->text('customer_delivery_notes')->nullable()->after('proof_of_delivery_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'rider_arrived_at',
                'delivery_confirmed_by_customer',
                'proof_of_delivery_image',
                'customer_delivery_notes',
            ]);
        });
    }
};
