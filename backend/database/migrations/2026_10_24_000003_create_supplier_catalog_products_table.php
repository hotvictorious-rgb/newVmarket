<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_catalog_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_vendor_id')->constrained('vendor_profiles')->restrictOnDelete();
            $table->foreignId('supplier_shop_id')->constrained('shops')->restrictOnDelete();
            $table->string('product_name', 180);
            $table->text('description')->nullable();
            $table->string('photo_path', 2048)->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('model', 180)->nullable();
            $table->string('variant', 180)->nullable();
            $table->string('condition', 40)->nullable();
            $table->string('package_tier', 40)->nullable();
            $table->json('specifications')->nullable();
            $table->decimal('source_price', 12, 2);
            $table->boolean('is_in_stock')->default(false);
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('canonical_product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('linked_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('linked_at')->nullable();
            $table->text('match_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_vendor_id', 'status', 'is_in_stock'], 'supplier_catalog_owner_stock_idx');
            $table->index(['canonical_product_id', 'status'], 'supplier_catalog_canonical_idx');
        });

        Schema::table('supplier_offers', function (Blueprint $table) {
            $table->foreignId('supplier_catalog_product_id')
                ->nullable()
                ->unique()
                ->constrained('supplier_catalog_products')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_catalog_product_id');
        });

        Schema::dropIfExists('supplier_catalog_products');
    }
};
