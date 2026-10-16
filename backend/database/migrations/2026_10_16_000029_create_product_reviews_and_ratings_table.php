<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1 to 5 stars
            $table->string('headline', 150)->nullable();
            $table->text('comment');
            $table->boolean('is_verified_buyer')->default(true);
            $table->string('status', 32)->default('active'); // active, revoked_returned, hidden_admin
            $table->string('revoked_reason', 255)->nullable();
            $table->timestamps();

            // Mathematical guarantee: One verified review per order-product purchase
            $table->unique(['order_id', 'product_id'], 'uq_order_product_review');
            $table->index(['product_id', 'status'], 'idx_product_review_status');
            $table->index(['customer_id', 'status'], 'idx_customer_review_status');
        });

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'rating')) {
                $table->decimal('rating', 3, 2)->default(0.00)->after('unit_price');
            }
            if (!Schema::hasColumn('products', 'reviews_count')) {
                $table->unsignedInteger('reviews_count')->default(0)->after('rating');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');

        Schema::table('products', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('products', 'rating')) {
                $drop[] = 'rating';
            }
            if (Schema::hasColumn('products', 'reviews_count')) {
                $drop[] = 'reviews_count';
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};