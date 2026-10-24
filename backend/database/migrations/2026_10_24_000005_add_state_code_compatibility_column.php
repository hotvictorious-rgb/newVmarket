<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some historical fixtures and integrations use "code", while the
        // canonical geography schema originally named this field "state_code".
        // Guard the addition so databases whose schema already has "code" remain safe.
        if (!Schema::hasColumn('states', 'code')) {
            Schema::table('states', function (Blueprint $table) {
                $table->string('code', 10)->nullable()->index();
            });
        }

        if (Schema::hasColumn('states', 'state_code') && Schema::hasColumn('states', 'code')) {
            DB::table('states')->whereNull('code')
                ->whereNotNull('state_code')
                ->update(['code' => DB::raw('state_code')]);
        }
    }

    public function down(): void
    {
        // Deliberately retain the compatibility column. It may have existed
        // before this migration on some installations, and dropping it would
        // break existing geography integrations.
    }
};
