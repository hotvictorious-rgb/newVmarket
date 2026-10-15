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
        // 1. Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('images')->nullable();
            
            // Financial & Stock Invariants
            $table->decimal('unit_price', 24, 2);
            $table->boolean('is_in_stock')->default(true); // Canonical binary visibility to customer
            $table->unsignedInteger('inventory_quantity')->default(1);
            
            // Listing Lifecycle & 7-Day Freshness Window
            $table->enum('listing_status', ['listed', 'unlisted', 'delisted'])->default('listed');
            $table->timestamp('listing_confirmed_at')->nullable();
            $table->boolean('is_vmarket_flagship')->default(false); // In-house direct retail items
            $table->boolean('is_approved')->default(true); // Super Admin marketplace approval
            $table->enum('package_tier', ['standard', 'fragile', 'bulky'])->default('standard');
            
            $table->timestamps();

            $table->index(['shop_id', 'is_in_stock', 'listing_status']);
            $table->index(['category_id', 'is_approved', 'listing_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
