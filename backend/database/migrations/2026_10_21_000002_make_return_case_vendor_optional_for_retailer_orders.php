<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retailer-model return cases are owned by Victorious MARKET centrally,
        // so they must not require or imply a private supplier beneficiary.
        Schema::table('return_cases', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->unsignedBigInteger('vendor_id')->nullable()->change();
            $table->foreign('vendor_id')->references('id')->on('vendor_profiles');
        });
    }

    public function down(): void
    {
        if (DB::table('return_cases')->whereNull('vendor_id')->exists()) {
            throw new RuntimeException(
                'Cannot make return_cases.vendor_id required while central retailer return cases have null vendor_id values.'
            );
        }

        Schema::table('return_cases', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->unsignedBigInteger('vendor_id')->nullable(false)->change();
            $table->foreign('vendor_id')->references('id')->on('vendor_profiles');
        });
    }
};
