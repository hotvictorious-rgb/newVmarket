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
        // 1. Add corporate wallet balance to logistics companies
        if (Schema::hasTable('logistics_company_profiles') && !Schema::hasColumn('logistics_company_profiles', 'wallet_balance')) {
            Schema::table('logistics_company_profiles', function (Blueprint $table) {
                $table->decimal('wallet_balance', 24, 2)->default(0.00)->after('commission_rate');
            });
        }

        // 2. Logistics Collection Points per LGA / State
        if (!Schema::hasTable('logistics_collection_points')) {
            Schema::create('logistics_collection_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_company_id')->constrained('logistics_company_profiles')->cascadeOnDelete();
            $table->foreignId('state_id')->constrained('states')->cascadeOnDelete();
            $table->foreignId('lga_id')->constrained('lgas')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address');
            $table->string('landmark')->nullable();
            $table->string('contact_phone');
            $table->string('contact_person')->nullable();
            $table->string('operating_hours')->default('08:00 AM - 06:00 PM');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['lga_id', 'is_active']);
            $table->index(['logistics_company_id', 'is_active']);
            });
        }

        // 3. Link collection_point_id to orders
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'collection_point_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('collection_point_id')->nullable()->after('shop_id')->constrained('logistics_collection_points')->nullOnDelete();
            });
        }

        // 4. Optional assigned_logistics_company_id on delivery_lanes
        if (Schema::hasTable('delivery_lanes') && !Schema::hasColumn('delivery_lanes', 'assigned_logistics_company_id')) {
            Schema::table('delivery_lanes', function (Blueprint $table) {
                $table->foreignId('assigned_logistics_company_id')->nullable()->after('destination_lga_id')->constrained('logistics_company_profiles')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('delivery_lanes') && Schema::hasColumn('delivery_lanes', 'assigned_logistics_company_id')) {
            Schema::table('delivery_lanes', function (Blueprint $table) {
                $table->dropForeign(['assigned_logistics_company_id']);
                $table->dropColumn('assigned_logistics_company_id');
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'collection_point_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['collection_point_id']);
                $table->dropColumn('collection_point_id');
            });
        }

        Schema::dropIfExists('logistics_collection_points');

        if (Schema::hasTable('logistics_company_profiles') && Schema::hasColumn('logistics_company_profiles', 'wallet_balance')) {
            Schema::table('logistics_company_profiles', function (Blueprint $table) {
                $table->dropColumn('wallet_balance');
            });
        }
    }
};
