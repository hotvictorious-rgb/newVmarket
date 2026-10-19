<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('return_cases')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE return_cases MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'requested'");
                DB::statement("ALTER TABLE return_cases MODIFY COLUMN return_reason VARCHAR(50) NOT NULL DEFAULT 'other'");
            } else {
                Schema::table('return_cases', function (Blueprint $table) {
                    $table->string('status', 50)->default('requested')->change();
                    $table->string('return_reason', 50)->default('other')->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op
    }
};