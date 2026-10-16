<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'condition')) {
                $table->string('condition', 32)->default('brand_new')->after('category_id');
            }
            if (!Schema::hasColumn('products', 'slash_price')) {
                $table->decimal('slash_price', 24, 2)->nullable()->after('unit_price');
            }
            if (!Schema::hasColumn('products', 'warranty_type')) {
                $table->string('warranty_type', 32)->default('in_store_testing')->after('slash_price');
            }
            if (Schema::hasColumn('products', 'package_tier')) {
                $table->string('package_tier', 32)->default('bike')->change();
            } else {
                $table->string('package_tier', 32)->default('bike');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('products', 'condition')) {
                $drop[] = 'condition';
            }
            if (Schema::hasColumn('products', 'slash_price')) {
                $drop[] = 'slash_price';
            }
            if (Schema::hasColumn('products', 'warranty_type')) {
                $drop[] = 'warranty_type';
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
