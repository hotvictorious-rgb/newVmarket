<?php

namespace Database\Seeders;

use App\Models\AdminEmployeeProfile;
use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorEmployeeProfile;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EcosystemDemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('Password123!');
        $nigeria = Country::where('iso_code', 'NGA')->first();
        $state = State::where('name', 'Akwa Ibom')->first();
        $uyo = Lga::where('name', 'Uyo')->first();

        // 1. Super Admin
        $superAdmin = User::updateOrCreate([
            'email' => 'admin@victoriousmarket.com.ng',
        ], [
            'name' => 'Victorious Super Admin',
            'phone' => '+2348011110001',
            'user_type' => 'super_admin',
            'status' => 'active',
            'password' => $password,
        ]);

        // 2. Super Admin Employees (Finance, Dispatch, Support)
        $financeUser = User::updateOrCreate([
            'email' => 'finance@victoriousmarket.com.ng',
        ], [
            'name' => 'Finance Controller',
            'phone' => '+2348011110002',
            'user_type' => 'admin_employee',
            'status' => 'active',
            'password' => $password,
        ]);
        AdminEmployeeProfile::updateOrCreate(['user_id' => $financeUser->id], [
            'role' => 'finance',
            'permissions' => ['view_settlements', 'disburse_settlements', 'view_ledgers'],
        ]);

        $dispatchUser = User::updateOrCreate([
            'email' => 'dispatch@victoriousmarket.com.ng',
        ], [
            'name' => 'Logistics Dispatch Officer',
            'phone' => '+2348011110003',
            'user_type' => 'admin_employee',
            'status' => 'active',
            'password' => $password,
        ]);
        AdminEmployeeProfile::updateOrCreate(['user_id' => $dispatchUser->id], [
            'role' => 'dispatch',
            'permissions' => ['assign_riders', 'view_active_orders', 'manage_lanes'],
        ]);

        $supportUser = User::updateOrCreate([
            'email' => 'support@victoriousmarket.com.ng',
        ], [
            'name' => 'Customer Support Lead',
            'phone' => '+2348011110004',
            'user_type' => 'admin_employee',
            'status' => 'active',
            'password' => $password,
        ]);
        AdminEmployeeProfile::updateOrCreate(['user_id' => $supportUser->id], [
            'role' => 'support',
            'permissions' => ['view_disputes', 'arbitrate_returns', 'contact_customers'],
        ]);

        // 3. Vendor (Merchant)
        $vendorUser = User::updateOrCreate([
            'email' => 'vendor@victoriousmarket.com.ng',
        ], [
            'name' => 'Victor Bassey',
            'phone' => '+2348022220001',
            'user_type' => 'vendor',
            'status' => 'active',
            'password' => $password,
        ]);
        $vendorProfile = VendorProfile::updateOrCreate(['user_id' => $vendorUser->id], [
            'business_name' => 'Victor Tech Innovations',
            'nin_number' => '12345678901',
            'cac_number' => 'RC-987654',
            'kyc_status' => 'verified',
            'kyc_reviewed_at' => Carbon::now(),
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Victor Tech Innovations',
            'bank_code' => '057',
            'bank_updated_at' => Carbon::now()->subDays(5), // Passed 48h cooldown
            'marketplace_status' => 'active',
        ]);

        // Primary Shop Branch in Uyo
        $primaryShop = Shop::updateOrCreate(['vendor_id' => $vendorProfile->id, 'slug' => 'victor-tech-uyo-plaza'], [
            'name' => 'Victor Tech Uyo Flagship Plaza',
            'address' => '42 Ikot Ekpene Road, Uyo, Akwa Ibom',
            'country_id' => $nigeria->id,
            'state_id' => $state->id,
            'lga_id' => $uyo->id,
            'latitude' => 5.03770000,
            'longitude' => 7.91280000,
            'phone' => '+2348022220001',
            'email' => 'shop@victortech.ng',
            'is_primary_branch' => true,
            'pickup_enabled' => true,
            'pickup_prep_time_minutes' => 20,
            'pickup_opening_time' => '08:30:00',
            'pickup_closing_time' => '18:00:00',
        ]);

        // 4. Vendor Employee (Branch Staff with shop_id isolation)
        $staffUser = User::updateOrCreate([
            'email' => 'staff@victoriousmarket.com.ng',
        ], [
            'name' => 'Aniefiok Akpan (Counter Staff)',
            'phone' => '+2348022220002',
            'user_type' => 'vendor_employee',
            'status' => 'active',
            'password' => $password,
        ]);
        VendorEmployeeProfile::updateOrCreate(['user_id' => $staffUser->id], [
            'vendor_id' => $vendorProfile->id,
            'shop_id' => $primaryShop->id,
            'role' => 'inspection_staff',
        ]);

        // 5. Logistics Company (Fleet Operator)
        $logisticsUser = User::updateOrCreate([
            'email' => 'logistics@victoriousmarket.com.ng',
        ], [
            'name' => 'Akwa Express Logistics Ltd',
            'phone' => '+2348033330001',
            'user_type' => 'logistics_company',
            'status' => 'active',
            'password' => $password,
        ]);
        $logisticsCompany = LogisticsCompanyProfile::updateOrCreate(['user_id' => $logisticsUser->id], [
            'company_name' => 'Akwa Express Logistics',
            'registration_number' => 'RC-554433',
            'contact_email' => 'dispatch@akwaexpress.ng',
            'contact_phone' => '+2348033330001',
            'hq_address' => '15 Oron Road, Uyo',
            'lga_id' => $uyo->id,
            'fleet_size' => 12,
            'commission_rate' => 0.00,
            'status' => 'active',
        ]);

        // 6. Delivery Rider
        $riderUser = User::updateOrCreate([
            'email' => 'rider@victoriousmarket.com.ng',
        ], [
            'name' => 'Edidiong Udoh (Rider Alpha)',
            'phone' => '+2348044440001',
            'user_type' => 'rider',
            'status' => 'active',
            'password' => $password,
        ]);
        RiderProfile::updateOrCreate(['user_id' => $riderUser->id], [
            'logistics_company_id' => $logisticsCompany->id,
            'assigned_lga_id' => $uyo->id,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'UYO-123-AK',
            'nin_number' => '98765432101',
            'is_active' => true,
            'is_online' => true,
            'current_latitude' => 5.03800000,
            'current_longitude' => 7.91500000,
            'current_balance' => '15000.00',
        ]);

        // 7. Customer (Shopper)
        $customerUser = User::updateOrCreate([
            'email' => 'customer@victoriousmarket.com.ng',
        ], [
            'name' => 'Emeka Okonkwo',
            'phone' => '+2348055550001',
            'user_type' => 'customer',
            'status' => 'active',
            'password' => $password,
        ]);
        CustomerAddress::updateOrCreate(['user_id' => $customerUser->id, 'is_default' => true], [
            'contact_name' => 'Emeka Okonkwo',
            'phone' => '+2348055550001',
            'address_line' => '18 Aka Road, near Plaza, Uyo',
            'country_id' => $nigeria->id,
            'state_id' => $state->id,
            'lga_id' => $uyo->id,
            'landmark' => 'Beside First Bank Aka Road',
            'is_default' => true,
        ]);

        // Seed Categories
        $electronics = Category::updateOrCreate(['slug' => 'electronics-gadgets'], [
            'name' => 'Electronics & Gadgets',
            'priority' => 1,
            'is_active' => true,
        ]);

        $appliances = Category::updateOrCreate(['slug' => 'home-appliances'], [
            'name' => 'Home Appliances',
            'priority' => 2,
            'is_active' => true,
        ]);

        // Seed Verified Products
        Product::updateOrCreate(['slug' => 'oraimo-freepods-4-anc'], [
            'vendor_id' => $vendorProfile->id,
            'shop_id' => $primaryShop->id,
            'category_id' => $electronics->id,
            'name' => 'Oraimo FreePods 4 ANC Wireless Earbuds',
            'description' => 'Original Oraimo FreePods 4 with Active Noise Cancellation, high bass, and 35.5h battery lifespan.',
            'unit_price' => '32500.00',
            'is_in_stock' => true,
            'inventory_quantity' => 25,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(), // Freshness active
            'is_vmarket_flagship' => false,
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        Product::updateOrCreate(['slug' => 'infinix-hot-40-pro-256gb'], [
            'vendor_id' => $vendorProfile->id,
            'shop_id' => $primaryShop->id,
            'category_id' => $electronics->id,
            'name' => 'Infinix Hot 40 Pro (256GB / 8GB RAM)',
            'description' => 'Brand new sealed Infinix Hot 40 Pro with 108MP camera, Helio G99 processor, and 33W fast charge.',
            'unit_price' => '210000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 10,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'is_vmarket_flagship' => true,
            'is_approved' => true,
            'package_tier' => 'fragile',
        ]);

        // Secondary Shop matching screenshot: Emeka Electronics Plaza
        $emekaShop = Shop::updateOrCreate(['vendor_id' => $vendorProfile->id, 'slug' => 'emeka-electronics-plaza'], [
            'name' => 'Emeka Electronics Plaza',
            'address' => '12 Oron Road, Uyo, Akwa Ibom',
            'country_id' => $nigeria->id,
            'state_id' => $state->id,
            'lga_id' => $uyo->id,
            'latitude' => 5.03500000,
            'longitude' => 7.91000000,
            'phone' => '+2348022220005',
            'email' => 'emeka@electronics.ng',
            'is_primary_branch' => false,
            'pickup_enabled' => true,
            'pickup_prep_time_minutes' => 15,
            'pickup_opening_time' => '08:00:00',
            'pickup_closing_time' => '18:30:00',
        ]);

        Product::updateOrCreate(['slug' => 'heavy-duty-20000mah-power-bank'], [
            'vendor_id' => $vendorProfile->id,
            'shop_id' => $emekaShop->id,
            'category_id' => $electronics->id,
            'name' => 'Heavy Duty 20,000mAh Power Bank',
            'description' => 'Ultra-high capacity 20,000mAh power bank with dual USB-C PD fast charge and LED battery display.',
            'unit_price' => '32000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 40,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'is_vmarket_flagship' => false,
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        Product::updateOrCreate(['slug' => 'wireless-bluetooth-earbuds-pro'], [
            'vendor_id' => $vendorProfile->id,
            'shop_id' => $emekaShop->id,
            'category_id' => $electronics->id,
            'name' => 'Wireless Bluetooth Earbuds Pro',
            'description' => 'Crystal clear stereo sound, touch controls, low latency gaming mode, and 30-hour playback.',
            'unit_price' => '25000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 50,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'is_vmarket_flagship' => false,
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        Product::updateOrCreate(['slug' => 'original-fast-charger-65w-gan'], [
            'vendor_id' => $vendorProfile->id,
            'shop_id' => $emekaShop->id,
            'category_id' => $electronics->id,
            'name' => 'Original Fast Charger 65W GaN',
            'description' => 'Compact 65W GaN fast wall charger compatible with laptops, tablets, and smartphones with surge protection.',
            'unit_price' => '18500.00',
            'is_in_stock' => true,
            'inventory_quantity' => 60,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'is_vmarket_flagship' => false,
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        // Seed Default Announcement & Support Settings
        \App\Models\SystemSetting::set('support_phone', '09118949035', 'contact');
        \App\Models\SystemSetting::set('support_whatsapp', '09118949035', 'contact');
        \App\Models\SystemSetting::set('support_email', 'support@victoriousmarket.com', 'contact');
        \App\Models\SystemSetting::set('announcement_type', 'phone_order', 'announcement');
        \App\Models\SystemSetting::set('announcement_badge', '💬', 'announcement');
        \App\Models\SystemSetting::set('announcement_text', 'Call or Whatsapp 09118949035 to order', 'announcement');
        \App\Models\SystemSetting::set('announcement_bg_color', '#0F172A', 'announcement');
        \App\Models\SystemSetting::set('announcement_link', 'https://wa.me/2349118949035', 'announcement');
        \App\Models\SystemSetting::set('announcement_is_active', '1', 'announcement');

        $this->command->info('✅ Seeded complete 7-actor ecosystem accounts, shops, catalog items, and announcement settings.');
    }
}
