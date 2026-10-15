<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Dynamic & Cached Banners Management for Storefront Hero and Promotional Strips.
     */
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('banner_type', 50)->default('hero_slider'); // hero_slider, promo_strip, category_banner, popup_modal
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('badge_text', 100)->nullable()->default('VERIFIED OMNICHANNEL MARKET');
            $table->string('image_path')->nullable();
            $table->string('bg_gradient', 255)->default('linear-gradient(135deg, #FAF5FF 0%, #FFFFFF 100%)');
            $table->string('text_color', 50)->default('#0F172A');
            $table->string('cta_text', 100)->default('Shop Verified Items');
            $table->string('cta_url')->default('#catalog');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['banner_type', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};