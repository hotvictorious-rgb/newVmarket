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
        // 1. Super Admin Employee Profiles
        Schema::create('admin_employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['finance', 'dispatch', 'support', 'compliance'])->default('support');
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        // 2. Vendor Profiles
        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('business_name');
            $table->string('nin_number')->nullable();
            $table->string('nin_document_path')->nullable();
            $table->string('cac_number')->nullable();
            $table->string('cac_document_path')->nullable();
            $table->enum('kyc_status', ['unverified', 'submitted', 'verified', 'rejected'])->default('unverified');
            $table->timestamp('kyc_reviewed_at')->nullable();
            $table->text('kyc_notes')->nullable();

            // NUBAN Bank & 48-Hour Cooldown Protection
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank_code')->nullable();
            $table->timestamp('bank_updated_at')->nullable();
            $table->string('bank_otp_hash')->nullable();
            $table->timestamp('bank_otp_expires_at')->nullable();

            $table->enum('marketplace_status', ['active', 'suspended', 'probation'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Shops (Vendor Physical Branches & Pickup Counters)
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('address');
            $table->foreignId('country_id')->constrained('countries');
            $table->foreignId('state_id')->constrained('states');
            $table->foreignId('lga_id')->constrained('lgas');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary_branch')->default(false);
            $table->boolean('is_active')->default(true);

            // Pickup Station Settings
            $table->boolean('pickup_enabled')->default(true);
            $table->unsignedInteger('pickup_prep_time_minutes')->default(30);
            $table->time('pickup_opening_time')->default('08:00:00');
            $table->time('pickup_closing_time')->default('18:00:00');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lga_id', 'pickup_enabled']);
        });

        // 4. Vendor Employee Profiles (Enforces Strict Branch Isolation via shop_id)
        Schema::create('vendor_employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->enum('role', ['manager', 'inspection_staff', 'cashier', 'inventory_clerk'])->default('inspection_staff');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['vendor_id', 'shop_id']);
        });

        // 5. Logistics Company Profiles (Fleet Operators)
        Schema::create('logistics_company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('registration_number')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('hq_address')->nullable();
            $table->foreignId('lga_id')->nullable()->constrained('lgas');
            $table->unsignedInteger('fleet_size')->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0.00);
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // 6. Rider Profiles (Delivery Dispatch)
        Schema::create('rider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('logistics_company_id')->nullable()->constrained('logistics_company_profiles')->nullOnDelete();
            $table->foreignId('assigned_lga_id')->nullable()->constrained('lgas');
            $table->enum('vehicle_type', ['motorcycle', 'bicycle', 'van', 'transit_waybill'])->default('motorcycle');
            $table->string('license_plate')->nullable();
            $table->string('nin_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online')->default(false);
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->decimal('current_balance', 24, 2)->default(0.00);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_lga_id', 'is_online', 'is_active']);
        });

        // 7. Customer Address Book (Destination LGA Cascade)
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('contact_name');
            $table->string('phone');
            $table->text('address_line');
            $table->foreignId('country_id')->constrained('countries');
            $table->foreignId('state_id')->constrained('states');
            $table->foreignId('lga_id')->constrained('lgas');
            $table->string('landmark')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'lga_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('rider_profiles');
        Schema::dropIfExists('logistics_company_profiles');
        Schema::dropIfExists('vendor_employee_profiles');
        Schema::dropIfExists('shops');
        Schema::dropIfExists('vendor_profiles');
        Schema::dropIfExists('admin_employee_profiles');
    }
};
