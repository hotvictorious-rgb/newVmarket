<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cashback_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->decimal('amount', 24, 2);
            $table->enum('status', ['reserved', 'consumed', 'released'])->default('reserved');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['expires_at', 'status']);
        });

        // Add refund reference to orders if not present
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'paystack_refund_reference')) {
                $table->string('paystack_refund_reference')->nullable()->after('paystack_transaction_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'paystack_refund_reference')) {
                $table->dropColumn('paystack_refund_reference');
            }
        });
        Schema::dropIfExists('cashback_reservations');
    }
};
