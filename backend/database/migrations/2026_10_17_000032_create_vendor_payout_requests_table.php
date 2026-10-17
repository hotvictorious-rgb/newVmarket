<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->decimal('amount', 24, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('bank_name');
            $table->string('bank_code')->nullable();
            $table->string('account_number');
            $table->string('account_name');
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disbursed_at')->nullable();
            $table->string('settlement_proof_path')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        if (Schema::hasTable('logistics_payout_requests') && !Schema::hasColumn('logistics_payout_requests', 'rejection_reason')) {
            Schema::table('logistics_payout_requests', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()->after('admin_notes');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payout_requests');

        if (Schema::hasTable('logistics_payout_requests') && Schema::hasColumn('logistics_payout_requests', 'rejection_reason')) {
            Schema::table('logistics_payout_requests', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};