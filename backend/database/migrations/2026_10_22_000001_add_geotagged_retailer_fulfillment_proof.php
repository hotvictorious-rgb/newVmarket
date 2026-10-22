<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'pickup_proof_latitude')) {
                    $table->decimal('pickup_proof_latitude', 10, 7)->nullable();
                }
                if (!Schema::hasColumn('orders', 'pickup_proof_longitude')) {
                    $table->decimal('pickup_proof_longitude', 10, 7)->nullable();
                }
                if (!Schema::hasColumn('orders', 'pickup_proof_captured_at')) {
                    $table->timestamp('pickup_proof_captured_at')->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_proof_latitude')) {
                    $table->decimal('delivery_proof_latitude', 10, 7)->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_proof_longitude')) {
                    $table->decimal('delivery_proof_longitude', 10, 7)->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_proof_captured_at')) {
                    $table->timestamp('delivery_proof_captured_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('order_supplier_assignments')) {
            Schema::table('order_supplier_assignments', function (Blueprint $table) {
                if (!Schema::hasColumn('order_supplier_assignments', 'collection_latitude')) {
                    $table->decimal('collection_latitude', 10, 7)->nullable();
                }
                if (!Schema::hasColumn('order_supplier_assignments', 'collection_longitude')) {
                    $table->decimal('collection_longitude', 10, 7)->nullable();
                }
                if (!Schema::hasColumn('order_supplier_assignments', 'collection_captured_at')) {
                    $table->timestamp('collection_captured_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_supplier_assignments')) {
            Schema::table('order_supplier_assignments', function (Blueprint $table) {
                foreach (['collection_latitude', 'collection_longitude', 'collection_captured_at'] as $column) {
                    if (Schema::hasColumn('order_supplier_assignments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach ([
                    'pickup_proof_latitude', 'pickup_proof_longitude', 'pickup_proof_captured_at',
                    'delivery_proof_latitude', 'delivery_proof_longitude', 'delivery_proof_captured_at',
                ] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
