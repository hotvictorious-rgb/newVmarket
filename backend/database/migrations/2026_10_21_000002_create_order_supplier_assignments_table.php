<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_supplier_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('supplier_vendor_id')->constrained('vendor_profiles')->restrictOnDelete();
            $table->foreignId('supplier_shop_id')->constrained('shops')->restrictOnDelete();
            $table->string('status', 40)->default('awaiting_payment');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('supplier_confirmed_at')->nullable();
            $table->timestamp('supplier_rejected_at')->nullable();
            $table->text('supplier_response_notes')->nullable();
            $table->foreignId('admin_override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_override_reason')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'supplier_vendor_id', 'supplier_shop_id'], 'order_supplier_assignments_unique_source');
            $table->index(['supplier_vendor_id', 'status'], 'order_supplier_assignments_supplier_status_idx');
            $table->index(['order_id', 'status'], 'order_supplier_assignments_order_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_supplier_assignments');
    }
};
