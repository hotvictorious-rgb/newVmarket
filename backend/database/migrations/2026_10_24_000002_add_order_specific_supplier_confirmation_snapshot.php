<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_supplier_assignments', function (Blueprint $table) {
            // Records exactly which paid order lines and requested quantities the
            // supplier explicitly accepted. This is order-scoped, not catalog stock.
            $table->json('confirmed_order_items')->nullable();
            $table->string('confirmation_snapshot_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_supplier_assignments', function (Blueprint $table) {
            $table->dropColumn(['confirmed_order_items', 'confirmation_snapshot_hash']);
        });
    }
};
