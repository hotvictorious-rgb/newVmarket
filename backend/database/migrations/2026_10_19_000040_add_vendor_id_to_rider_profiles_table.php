<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
