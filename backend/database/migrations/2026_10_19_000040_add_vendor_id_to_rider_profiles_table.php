<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Rewires Rider profiles so delivery persons belong to a Vendor (merchant).
     */
    public function up(): void
    {
        Schema::table('rider_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('rider_profiles', 'vendor_id')) {
                $table->foreignId('vendor_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('vendor_profiles')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('rider_profiles', 'shop_id')) {
                $table->foreignId('shop_id')
                    ->nullable()
                    ->after('vendor_id')
                    ->constrained('shops')
                    ->nullOnDelete();
            }

            $table->index(['vendor_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // SQLite requires dependent indexes to be removed before dropping a
        // column. PostgreSQL removes them automatically with the column.
        if (DB::getDriverName() === 'sqlite'
            && Schema::hasIndex('rider_profiles', 'rider_profiles_vendor_id_is_active_index')) {
            Schema::table('rider_profiles', function (Blueprint $table) {
                $table->dropIndex('rider_profiles_vendor_id_is_active_index');
            });
        }

        Schema::table('rider_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('rider_profiles', 'vendor_id')) {
                $table->dropForeign(['vendor_id']);
                $table->dropColumn('vendor_id');
            }
            if (Schema::hasColumn('rider_profiles', 'shop_id')) {
                $table->dropForeign(['shop_id']);
                $table->dropColumn('shop_id');
            }
        });
    }
};
