<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('template_style', 50)->default('luxury_split')->after('banner_type');
            $table->string('discount_tag', 100)->nullable()->after('badge_text');
            $table->string('feature_tags', 255)->nullable()->after('discount_tag');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['template_style', 'discount_tag', 'feature_tags']);
        });
    }
};