<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_cases', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->foreignId('vendor_id')->nullable()->change();
            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendor_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('return_cases')->whereNull('vendor_id')->exists()) {
            throw new RuntimeException('Cannot restore required return_cases.vendor_id while centrally owned retailer return cases exist.');
        }

        Schema::table('return_cases', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->foreignId('vendor_id')->nullable(false)->change();
            $table->foreign('vendor_id')->references('id')->on('vendor_profiles');
        });
    }
};
