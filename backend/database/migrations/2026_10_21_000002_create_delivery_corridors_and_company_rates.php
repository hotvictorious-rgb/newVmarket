<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_corridors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->foreignId('lga_id')->constrained('lgas')->restrictOnDelete();
            $table->json('landmark_aliases');
            $table->unsignedSmallInteger('estimated_delivery_hours_min')->default(2);
            $table->unsignedSmallInteger('estimated_delivery_hours_max')->default(4);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->index(['lga_id', 'is_active']);
        });

        Schema::create('logistics_company_corridor_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_company_id')->constrained('logistics_company_profiles')->restrictOnDelete();
            $table->foreignId('delivery_corridor_id')->constrained('delivery_corridors')->restrictOnDelete();
            $table->decimal('base_payable', 12, 2);
            $table->decimal('bulky_surcharge_per_unit', 12, 2)->default(0);
            $table->unsignedInteger('tariff_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['logistics_company_id', 'delivery_corridor_id'], 'logistics_company_corridor_rate_unique');
            $table->index(['delivery_corridor_id', 'is_active']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_corridor_id')->nullable()->after('delivery_lane_id')->constrained('delivery_corridors')->restrictOnDelete();
            $table->decimal('logistics_company_base_payable_snapshot', 12, 2)->nullable()->after('logistics_company_payable_default_amount');
            $table->decimal('logistics_company_bulky_surcharge_per_unit_snapshot', 12, 2)->nullable()->after('logistics_company_base_payable_snapshot');
            $table->unsignedInteger('logistics_company_bulky_quantity_snapshot')->nullable()->after('logistics_company_bulky_surcharge_per_unit_snapshot');
            $table->unsignedInteger('logistics_company_payable_tariff_version_snapshot')->nullable()->after('logistics_company_bulky_quantity_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_corridor_id');
            $table->dropColumn([
                'logistics_company_base_payable_snapshot',
                'logistics_company_bulky_surcharge_per_unit_snapshot',
                'logistics_company_bulky_quantity_snapshot',
                'logistics_company_payable_tariff_version_snapshot',
            ]);
        });

        Schema::dropIfExists('logistics_company_corridor_rates');
        Schema::dropIfExists('delivery_corridors');
    }
};
