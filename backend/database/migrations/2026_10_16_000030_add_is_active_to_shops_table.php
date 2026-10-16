<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('shops') && !Schema::hasColumn('shops', 'is_active')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('is_primary_branch');
                $table->index(['vendor_id', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('shops') && Schema::hasColumn('shops', 'is_active')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropIndex(['vendor_id', 'is_active']);
                $table->dropColumn('is_active');
            });
        }
    }
};
