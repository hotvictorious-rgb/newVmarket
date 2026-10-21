<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive retailer accounting snapshots. Existing rows remain legacy by default.
     * New checkout writers must explicitly mark retailer_model_order=true.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('retailer_model_order')->default(false)->after('fulfillment_type');
            $table->decimal('supplier_cost_total_snapshot', 24, 2)->nullable()->after('merchandise_subtotal');
            $table->decimal('retailer_merchandise_margin', 24, 2)->nullable()->after('supplier_cost_total_snapshot');
            $table->decimal('logistics_company_payable_default_amount', 24, 2)->nullable()->after('delivery_fee');
            $table->decimal('logistics_company_payable_amount', 24, 2)->nullable()->after('logistics_company_payable_default_amount');
            $table->text('logistics_company_payable_override_reason')->nullable()->after('logistics_company_payable_amount');
            $table->foreignId('logistics_company_payable_override_by')->nullable()->after('logistics_company_payable_override_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('logistics_company_payable_override_at')->nullable()->after('logistics_company_payable_override_by');
            $table->unsignedInteger('logistics_company_payable_override_revision')->default(0)->after('logistics_company_payable_override_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['logistics_company_payable_override_by']);
            $table->dropColumn([
                'retailer_model_order',
                'supplier_cost_total_snapshot',
                'retailer_merchandise_margin',
                'logistics_company_payable_default_amount',
                'logistics_company_payable_amount',
                'logistics_company_payable_override_reason',
                'logistics_company_payable_override_by',
                'logistics_company_payable_override_at',
                'logistics_company_payable_override_revision',
            ]);
        });
    }
};
