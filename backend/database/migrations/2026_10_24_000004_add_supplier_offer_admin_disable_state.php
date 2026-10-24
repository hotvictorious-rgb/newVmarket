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
        if (!Schema::hasTable('supplier_offers')) {
            return;
        }

        Schema::table('supplier_offers', function (Blueprint $table) {
            // SQLite cannot drop an indexed column while its index still exists.
            // Drop the explicit Laravel-generated index first for reliable rollback
            // in both SQLite test teardown and PostgreSQL.
            $table->dropIndex('supplier_offers_is_admin_disabled_index');
            $table->dropColumn(['is_admin_disabled', 'admin_disabled_reason']);
        });
    }
};
