<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_base_fee_snapshot', 24, 2)->default(0.00)->after('delivery_fee');
            $table->decimal('delivery_bulky_surcharge_per_unit_snapshot', 24, 2)->default(0.00)->after('delivery_base_fee_snapshot');
            $table->unsignedInteger('delivery_bulky_quantity_snapshot')->default(0)->after('delivery_bulky_surcharge_per_unit_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_base_fee_snapshot',
                'delivery_bulky_surcharge_per_unit_snapshot',
                'delivery_bulky_quantity_snapshot',
            ]);
        });
    }
};
