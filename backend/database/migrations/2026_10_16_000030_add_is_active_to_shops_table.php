<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('shops')) {
            if (!Schema::hasColumn('shops', 'is_active')) {
                Schema::table('shops', function (Blueprint $table) {
                    $table->boolean('is_active')->default(true)->after('is_primary_branch');
                });
            }
            // Older installations may already have the column without this index.
            if (Schema::hasColumn('shops', 'vendor_id')
                && Schema::hasColumn('shops', 'is_active')
                && !Schema::hasIndex('shops', ['vendor_id', 'is_active'])) {
                Schema::table('shops', function (Blueprint $table) {
                    $table->index(['vendor_id', 'is_active']);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('shops') && Schema::hasColumn('shops', 'is_active')) {
            // SQLite cannot drop a column while a dependent index remains.
            // PostgreSQL drops dependent indexes automatically with the column.
            if (DB::getDriverName() === 'sqlite'
                && Schema::hasIndex('shops', 'shops_vendor_id_is_active_index')) {
                Schema::table('shops', function (Blueprint $table) {
                    $table->dropIndex('shops_vendor_id_is_active_index');
                });
            }
            Schema::table('shops', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
