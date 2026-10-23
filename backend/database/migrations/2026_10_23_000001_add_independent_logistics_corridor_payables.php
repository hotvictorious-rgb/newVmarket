<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_lanes', function (Blueprint $table) {
            // Keep the logistics company's contracted corridor payable independent of customer delivery pricing.
            $table->decimal('logistics_company_base_payable', 12, 2)->nullable();
            $table->decimal('logistics_company_bulky_surcharge', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_lanes', function (Blueprint $table) {
            $table->dropColumn([
                'logistics_company_base_payable',
                'logistics_company_bulky_surcharge',
            ]);
        });
    }
};
