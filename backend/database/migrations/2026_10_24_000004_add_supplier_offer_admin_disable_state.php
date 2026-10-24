<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_offers', function (Blueprint $table) {
            $table->boolean('is_admin_disabled')->default(false)->index();
            $table->string('admin_disabled_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_offers', function (Blueprint $table) {
            $table->dropColumn(['is_admin_disabled', 'admin_disabled_reason']);
        });
    }
};
