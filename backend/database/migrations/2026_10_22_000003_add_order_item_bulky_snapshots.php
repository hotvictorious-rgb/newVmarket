<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'package_tier_snapshot')) {
                $table->string('package_tier_snapshot', 30)->nullable();
            }
            if (!Schema::hasColumn('order_items', 'is_bulky_snapshot')) {
                $table->boolean('is_bulky_snapshot')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            foreach (['package_tier_snapshot', 'is_bulky_snapshot'] as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
