<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforce the three-quantity inventory model: Physical, Allocated, Held.
     * Available = max(0, Physical - Allocated - Held)
     */
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'physical_quantity')) {
                    $table->unsignedInteger('physical_quantity')->default(0)->after('inventory_quantity');
                }
                if (!Schema::hasColumn('products', 'allocated_quantity')) {
                    $table->unsignedInteger('allocated_quantity')->default(0)->after('physical_quantity');
                }
                if (!Schema::hasColumn('products', 'held_quantity')) {
                    $table->unsignedInteger('held_quantity')->default(0)->after('allocated_quantity');
                }
            });

            // Backfill physical_quantity from current inventory_quantity
            DB::table('products')->where('physical_quantity', 0)->update([
                'physical_quantity' => DB::raw('inventory_quantity'),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $cols = array_filter(['physical_quantity', 'allocated_quantity', 'held_quantity'], fn($c) => Schema::hasColumn('products', $c));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};