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
            if (!Schema::hasColumn('supplier_offers', 'availability_confirmed_at')) {
                $table->timestamp('availability_confirmed_at')->nullable();
            }
            if (!Schema::hasColumn('supplier_offers', 'availability_confirmed_by')) {
                $table->foreignId('availability_confirmed_by')->nullable()
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('supplier_offers', 'availability_expires_at')) {
                $table->timestamp('availability_expires_at')->nullable();
            }
            if (!Schema::hasIndex('supplier_offers', 'supplier_offer_eligibility_idx')) {
                $table->index(['product_id', 'is_available', 'availability_expires_at'], 'supplier_offer_eligibility_idx');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('supplier_offers')) {
            return;
        }

        Schema::table('supplier_offers', function (Blueprint $table) {
            if (Schema::hasIndex('supplier_offers', 'supplier_offer_eligibility_idx')) {
                $table->dropIndex('supplier_offer_eligibility_idx');
            }
            if (Schema::hasColumn('supplier_offers', 'availability_confirmed_by')) {
                $table->dropConstrainedForeignId('availability_confirmed_by');
            }
            if (Schema::hasColumn('supplier_offers', 'availability_expires_at')) {
                $table->dropColumn('availability_expires_at');
            }
            if (Schema::hasColumn('supplier_offers', 'availability_confirmed_at')) {
                $table->dropColumn('availability_confirmed_at');
            }
        });
    }
};
