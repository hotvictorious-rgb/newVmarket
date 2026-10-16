<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * High-Performance Composite & Covering Indexes for Marketplace Escrow,
     * Real-Time Logistics Corridor Telemetry, and High-Velocity Catalog Browsing.
     */
    public function up(): void
    {
        // 1. Orders Table: High-velocity queries, state machines, and OTP lookups
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['shop_id', 'order_status'], 'idx_orders_shop_status');
            $table->index(['order_status'], 'idx_orders_status');
            $table->index(['customer_id', 'id'], 'idx_orders_customer_id');
            $table->index(['rider_id', 'order_status', 'updated_at'], 'idx_orders_rider_status_updated');
            $table->index(['logistics_company_id', 'order_status'], 'idx_orders_logistics_status');
            $table->index(['pickup_verification_code'], 'idx_orders_pickup_otp');
            $table->index(['verification_code'], 'idx_orders_delivery_otp');
        });

        // 2. Products Table: Public marketplace catalog eligibility and vendor inventory
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_approved', 'listing_status', 'listing_confirmed_at'], 'idx_products_marketplace_eligible');
            $table->index(['vendor_id', 'id'], 'idx_products_vendor_id');
            $table->index(['is_vmarket_flagship', 'is_approved'], 'idx_products_flagship_approved');
        });

        // 3. Users Table: Role authentication and active tenant guard checks
        Schema::table('users', function (Blueprint $table) {
            $table->index(['user_type', 'status'], 'idx_users_type_status');
        });

        // 4. Return Cases Table: Handshake 3 vendor OTP verification and case queues
        Schema::table('return_cases', function (Blueprint $table) {
            $table->index(['vendor_return_otp'], 'idx_return_cases_vendor_otp');
            $table->index(['order_id', 'vendor_return_otp'], 'idx_return_cases_order_otp');
            $table->index(['vendor_id', 'created_at'], 'idx_return_cases_vendor_created');
        });

        // 5. Order Items Table: Doorstep partial manifest item-level status reconciliation
        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['order_id', 'item_delivery_status'], 'idx_order_items_order_delivery_status');
        });

        // 6. Pickup Reservations Table: Counter inspection queues and expiration sweeps
        Schema::table('pickup_reservations', function (Blueprint $table) {
            $table->index(['customer_id', 'id'], 'idx_reservations_customer_id');
            $table->index(['shop_id', 'status', 'expires_at'], 'idx_reservations_shop_status_expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pickup_reservations', function (Blueprint $table) {
            $table->dropIndex('idx_reservations_customer_id');
            $table->dropIndex('idx_reservations_shop_status_expires');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('idx_order_items_order_delivery_status');
        });

        Schema::table('return_cases', function (Blueprint $table) {
            $table->dropIndex('idx_return_cases_vendor_otp');
            $table->dropIndex('idx_return_cases_order_otp');
            $table->dropIndex('idx_return_cases_vendor_created');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_type_status');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_marketplace_eligible');
            $table->dropIndex('idx_products_vendor_id');
            $table->dropIndex('idx_products_flagship_approved');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_shop_status');
            $table->dropIndex('idx_orders_status');
            $table->dropIndex('idx_orders_customer_id');
            $table->dropIndex('idx_orders_rider_status_updated');
            $table->dropIndex('idx_orders_logistics_status');
            $table->dropIndex('idx_orders_pickup_otp');
            $table->dropIndex('idx_orders_delivery_otp');
        });
    }
};
