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
        // 1. Countries
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('iso_code', 3)->unique();
            $table->string('phone_code', 10);
            $table->string('currency_code', 10)->default('NGN');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. States
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->string('name');
            $table->string('state_code', 10)->nullable();
            $table->string('code', 10)->nullable(); // Compatibility alias used by older fixtures/integrations.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Local Government Areas (LGAs)
        Schema::create('lgas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained('states')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['state_id', 'is_active']);
        });

        // 4. Directional Delivery Lanes (Origin LGA -> Destination LGA)
        Schema::create('delivery_lanes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_lga_id')->constrained('lgas')->cascadeOnDelete();
            $table->foreignId('destination_lga_id')->constrained('lgas')->cascadeOnDelete();
            $table->decimal('base_fee', 24, 2)->default(500.00);
            $table->decimal('bulky_surcharge', 24, 2)->default(0.00);
            $table->unsignedInteger('estimated_delivery_hours_min')->default(2);
            $table->unsignedInteger('estimated_delivery_hours_max')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['origin_lga_id', 'destination_lga_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_lanes');
        Schema::dropIfExists('lgas');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
    }
};
