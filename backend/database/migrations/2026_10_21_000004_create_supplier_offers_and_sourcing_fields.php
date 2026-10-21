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
        // 1. Create supplier_offers table linking canonical VM products to private supplier offers
        if (!Schema::hasTable('supplier_offers')) {
            Schema::create('supplier_offers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->decimal('wholesale_cost', 24, 2);
                $table->boolean('is_available')->default(true);
                $table->string('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['product_id', 'is_available']);
                $table->index(['vendor_id', 'shop_id']);
            });
        }

        // 2. Add sourcing state machine fields to orders table
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'sourcing_status')) {
                    $table->string('sourcing_status', 32)->default('pending')->after('fulfillment_type');
                }
                if (!Schema::hasColumn('orders', 'selected_supplier_id')) {
                    $table->foreignId('selected_supplier_id')->nullable()->after('sourcing_status')->constrained('vendor_profiles');
                }
                if (!Schema::hasColumn('orders', 'selected_shop_id')) {
                    $table->foreignId('selected_shop_id')->nullable()->after('selected_supplier_id')->constrained('shops');
                }
                if (!Schema::hasColumn('orders', 'sourcing_notes')) {
                    $table->string('sourcing_notes')->nullable()->after('selected_shop_id');
                }
                if (!Schema::hasColumn('orders', 'sourced_at')) {
                    $table->timestamp('sourced_at')->nullable()->after('sourcing_notes');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_offers');

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'sourced_at')) {
                    $table->dropColumn('sourced_at');
                }
                if (Schema::hasColumn('orders', 'sourcing_notes')) {
                    $table->dropColumn('sourcing_notes');
                }
                if (Schema::hasColumn('orders', 'selected_shop_id')) {
                    $table->dropConstrainedForeignId('selected_shop_id');
                }
                if (Schema::hasColumn('orders', 'selected_supplier_id')) {
                    $table->dropConstrainedForeignId('selected_supplier_id');
                }
                if (Schema::hasColumn('orders', 'sourcing_status')) {
                    $table->dropColumn('sourcing_status');
                }
            });
        }
    }
};
