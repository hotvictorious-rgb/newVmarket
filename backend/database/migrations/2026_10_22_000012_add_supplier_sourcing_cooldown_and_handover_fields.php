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
        // 1. Add SKU 24-hour unavailability penalty cooldown to supplier_offers
        if (Schema::hasTable('supplier_offers')) {
            Schema::table('supplier_offers', function (Blueprint $table) {
                if (!Schema::hasColumn('supplier_offers', 'unavailability_penalty_expires_at')) {
                    $table->timestamp('unavailability_penalty_expires_at')->nullable()->after('availability_expires_at');
                    $table->index(['product_id', 'unavailability_penalty_expires_at'], 'supplier_offer_penalty_idx');
                }
            });
        }

        // 2. Add package_tier snapshot to order_items for immutable bulky/keke audit
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('order_items', 'package_tier')) {
                    $table->string('package_tier', 32)->default('bike')->after('line_total');
                }
            });
        }

        // 3. Add sourcing consolidation tracking and logistics subsidy fields to orders
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'sourcing_plan_locations_count')) {
                    $table->unsignedTinyInteger('sourcing_plan_locations_count')->default(1)->after('sourcing_status');
                }
                if (!Schema::hasColumn('orders', 'logistics_subsidy_amount')) {
                    $table->decimal('logistics_subsidy_amount', 24, 2)->default(0.00)->after('logistics_company_payable_amount');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'logistics_subsidy_amount')) {
                    $table->dropColumn('logistics_subsidy_amount');
                }
                if (Schema::hasColumn('orders', 'sourcing_plan_locations_count')) {
                    $table->dropColumn('sourcing_plan_locations_count');
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (Schema::hasColumn('order_items', 'package_tier')) {
                    $table->dropColumn('package_tier');
                }
            });
        }

        if (Schema::hasTable('supplier_offers')) {
            Schema::table('supplier_offers', function (Blueprint $table) {
                if (Schema::hasColumn('supplier_offers', 'unavailability_penalty_expires_at')) {
                    $table->dropIndex('supplier_offer_penalty_idx');
                    $table->dropColumn('unavailability_penalty_expires_at');
                }
            });
        }
    }
};
