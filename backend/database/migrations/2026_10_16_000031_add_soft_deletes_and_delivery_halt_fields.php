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
        // 1. Soft Deletes on Shops
        if (Schema::hasTable('shops') && !Schema::hasColumn('shops', 'deleted_at')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 2. Soft Deletes on Vendor Profiles
        if (Schema::hasTable('vendor_profiles') && !Schema::hasColumn('vendor_profiles', 'deleted_at')) {
            Schema::table('vendor_profiles', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 3. Soft Deletes on Logistics Company Profiles
        if (Schema::hasTable('logistics_company_profiles') && !Schema::hasColumn('logistics_company_profiles', 'deleted_at')) {
            Schema::table('logistics_company_profiles', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 4. Soft Deletes on Rider Profiles
        if (Schema::hasTable('rider_profiles') && !Schema::hasColumn('rider_profiles', 'deleted_at')) {
            Schema::table('rider_profiles', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 5. Soft Deletes on Products
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'deleted_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 6. Delivery Halt Audit Fields on Orders
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'delivery_halted_reason')) {
                    $table->string('delivery_halted_reason')->nullable()->after('delivery_failure_reason');
                }
                if (!Schema::hasColumn('orders', 'delivery_halted_at')) {
                    $table->timestamp('delivery_halted_at')->nullable()->after('delivery_halted_reason');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('shops') && Schema::hasColumn('shops', 'deleted_at')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('vendor_profiles') && Schema::hasColumn('vendor_profiles', 'deleted_at')) {
            Schema::table('vendor_profiles', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('logistics_company_profiles') && Schema::hasColumn('logistics_company_profiles', 'deleted_at')) {
            Schema::table('logistics_company_profiles', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('rider_profiles') && Schema::hasColumn('rider_profiles', 'deleted_at')) {
            Schema::table('rider_profiles', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'deleted_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'delivery_halted_reason')) {
                    $table->dropColumn('delivery_halted_reason');
                }
                if (Schema::hasColumn('orders', 'delivery_halted_at')) {
                    $table->dropColumn('delivery_halted_at');
                }
            });
        }
    }
};
