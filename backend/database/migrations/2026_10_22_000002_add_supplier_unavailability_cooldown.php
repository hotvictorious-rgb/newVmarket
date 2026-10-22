<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier_offers')) {
            return;
        }

        Schema::table('supplier_offers', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_offers', 'last_unavailable_at')) {
                $table->timestamp('last_unavailable_at')->nullable();
            }
            if (!Schema::hasColumn('supplier_offers', 'last_unavailable_by')) {
                $table->foreignId('last_unavailable_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('supplier_offers', 'last_unavailable_reason')) {
                $table->string('last_unavailable_reason', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('supplier_offers')) {
            return;
        }

        Schema::table('supplier_offers', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_offers', 'last_unavailable_by')) {
                $table->dropConstrainedForeignId('last_unavailable_by');
            }
            foreach (['last_unavailable_at', 'last_unavailable_reason'] as $column) {
                if (Schema::hasColumn('supplier_offers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
