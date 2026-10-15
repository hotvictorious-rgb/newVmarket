<?php

namespace Database\Seeders;

use App\Models\AdminEmployeeProfile;
use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\CustomerCashbackLedger;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorEmployeeProfile;
use App\Models\VendorProfile;
use App\Services\DemoAccountRegistry;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EcosystemDemoSeeder extends Seeder
{
    public function run(): void
    {
        $stdPasswordHash = Hash::make('Password123!');
        $demoPasswordHash = Hash::make(DemoAccountRegistry::DEMO_PASSWORD);

        $nigeria = Country::where('iso_code', 'NGA')->first();
        $state = State::where('name', 'Akwa Ibom')->first();
        $uyo = Lga::where('name', 'Uyo')->first();
        $eket = Lga::where('name', 'Eket')->first();
        $ikotEkpene = Lga::where('name', 'Ikot Ekpene')->first();

        // =========================================================================
        // 1. SUPER ADMIN (STRICTLY FROM ENVIRONMENT, is_demo = false)
        // =========================================================================
        $superAdminEmail = config('auth.superadmin.email', env('SUPERADMIN_EMAIL', 'admin@victoriousmarket.com.ng'));
        $superAdminPassword = config('auth.superadmin.password', env('SUPERADMIN_PASSWORD', 'Password123!'));
        $superAdminName = config('auth.superadmin.name', env('SUPERADMIN_NAME', 'Victorious Super Admin'));
        $superAdminPhone = config('auth.superadmin.phone', env('SUPERADMIN_PHONE', '+2348011110001'));

        $superAdmin = User::updateOrCreate([
            'email' => $superAdminEmail,
        ], [
            'name' => $superAdminName,
            'phone' => $superAdminPhone,
            'user_type' => 'super_admin',
            'status' => 'active',
            'is_demo' => false,
            'password' => Hash::make($superAdminPassword),
        ]);

        // =========================================================================
        // 2. BASE PLATFORM ACCOUNTS (For Standard Auth Tests, is_demo = false)
        // =========================================================================
        $baseStaff = User::updateOrCreate(['email' => 'finance@victoriousmarket.com.ng'], [
            'name' => 'Finance Controller',
            'phone' => '+2348011110002',
            'user_type' => 'admin_employee',
            'status' => 'active',
            'is_demo' => false,
            'password' => $stdPasswordHash,
        ]);
        AdminEmployeeProfile::updateOrCreate(['user_id' => $baseStaff->id], [
            'role' => 'finance',
            'permissions' => ['view_settlements', 'disburse_settlements', 'view_ledgers'],
        ]);

        $baseVendorUser = User::updateOrCreate(['email' => 'vendor@victoriousmarket.com.ng'], [
            'name' => 'Victor Tech Solutions',
            'phone' => '+2348022220001',
            'user_type' => 'vendor',
            'status' => 'active',
            'is_demo' => false,
            'password' => $stdPasswordHash,
        ]);
        $baseVendor = VendorProfile::updateOrCreate(['user_id' => $baseVendorUser->id], [
            'business_name' => 'Victor Tech Solutions',
            'bank_name' => 'Zenith Bank PLC',
            'account_number' => '1012345678',
            'account_name' => 'Victor Tech Solutions',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
        ]);
        $baseShop = Shop::updateOrCreate(['slug' => 'victor-tech-uyo-plaza'], [
            'vendor_id' => $baseVendor->id,
            'name' => 'Victor Tech Uyo Flagship Plaza',
            'address' => 'Shop 12, Ibom Plaza Commercial Center, Uyo',
            'country_id' => $nigeria ? $nigeria->id : 1,
            'state_id' => $state ? $state->id : 1,
            'lga_id' => $uyo ? $uyo->id : 1,
            'phone' => '+2348022220001',
            'email' => 'vendor@victoriousmarket.com.ng',
            'is_primary_branch' => true,
            'pickup_enabled' => true,
        ]);

        $baseCounter = User::updateOrCreate(['email' => 'staff@victoriousmarket.com.ng'], [
            'name' => 'Counter Inspection Clerk',
            'phone' => '+2348022220002',
            'user_type' => 'vendor_employee',
            'status' => 'active',
            'is_demo' => false,
            'password' => $stdPasswordHash,
        ]);
        VendorEmployeeProfile::updateOrCreate(['user_id' => $baseCounter->id], [
            'vendor_id' => $baseVendor->id,
            'shop_id' => $baseShop->id,
            'role' => 'inspection_staff',
        ]);

        $baseLogisticsUser = User::updateOrCreate(['email' => 'logistics@victoriousmarket.com.ng'], [
            'name' => 'Akwa Express Logistics Ltd',
            'phone' => '+2348033330001',
            'user_type' => 'logistics_company',
            'status' => 'active',
            'is_demo' => false,
            'password' => $stdPasswordHash,
        ]);
        $baseLogistics = LogisticsCompanyProfile::updateOrCreate(['user_id' => $baseLogisticsUser->id], [
            'company_name' => 'Akwa Express Logistics Ltd',
            'contact_email' => 'logistics@victoriousmarket.com.ng',
            'contact_phone' => '+2348044440001',
            'lga_id' => $uyo ? $uyo->id : 1,
            'fleet_size' => 15,
            'status' => 'active',
        ]);

        $baseRiderUser = User::updateOrCreate(['email' => 'rider@victoriousmarket.com.ng'], [
            'name' => 'Edidiong Udoh',
            'phone' => '+2348044440001',
            'user_type' => 'rider',
            'status' => 'active',
            'is_demo' => false,
            'password' => $stdPasswordHash,
        ]);
        RiderProfile::updateOrCreate(['user_id' => $baseRiderUser->id], [
            'logistics_company_id' => $baseLogistics->id,
            'assigned_lga_id' => $uyo ? $uyo->id : 1,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'UYO-123-AK',
            'is_active' => true,
            'is_online' => true,
        ]);

        $baseCustomer = User::updateOrCreate(['email' => 'customer@victoriousmarket.com.ng'], [
            'name' => 'Emeka Okonkwo',
            'phone' => '+2348055550001',
            'user_type' => 'customer',
            'status' => 'active',
            'is_demo' => false,
            'password' => $stdPasswordHash,
        ]);
        CustomerAddress::updateOrCreate(['user_id' => $baseCustomer->id], [
            'contact_name' => 'Emeka Okonkwo',
            'phone' => '+2348066660001',
            'address_line' => '42 Oron Road, Uyo Urban Center',
            'country_id' => $nigeria ? $nigeria->id : 1,
            'state_id' => $state ? $state->id : 1,
            'lga_id' => $uyo ? $uyo->id : 1,
            'is_default' => true,
        ]);

        // =========================================================================
        // 3. THE 18 DEMO PERSONAS (3 PER ROLE ACROSS 6 ROLES, is_demo = true)
        // =========================================================================

        // A. Admin Staff (3 Demo Personas)
        $demoStaff = [
            'demo.staff1@victoriousmarket.com.ng' => ['name' => 'Blessing Akpan', 'phone' => '+2348099010001', 'role' => 'compliance', 'perms' => ['moderate_catalog', 'verify_merchants']],
            'demo.staff2@victoriousmarket.com.ng' => ['name' => 'Iniobong Effiong', 'phone' => '+2348099010002', 'role' => 'finance', 'perms' => ['resolve_disputes', 'view_settlements']],
            'demo.staff3@victoriousmarket.com.ng' => ['name' => 'Kufre Asuquo', 'phone' => '+2348099010003', 'role' => 'dispatch', 'perms' => ['manage_lanes', 'assign_riders']],
        ];
        foreach ($demoStaff as $email => $ds) {
            $u = User::updateOrCreate(['email' => $email], [
                'name' => $ds['name'], 'phone' => $ds['phone'], 'user_type' => 'admin_employee', 'status' => 'active', 'is_demo' => true, 'password' => $demoPasswordHash,
            ]);
            AdminEmployeeProfile::updateOrCreate(['user_id' => $u->id], ['role' => $ds['role'], 'permissions' => $ds['perms']]);
        }

        // B. Vendors (3 Demo Personas)
        $demoVendors = [
            'demo.vendor1@victoriousmarket.com.ng' => ['name' => 'Victor Bassey (Victor Tech)', 'business' => 'Victor Tech Electronics Ltd', 'phone' => '+2348099020001', 'shop' => 'Victor Tech Uyo Flagship Plaza', 'slug' => 'demo-victor-tech', 'lga' => $uyo ? $uyo->id : 1],
            'demo.vendor2@victoriousmarket.com.ng' => ['name' => 'Mercy Etim (Ibom Fresh)', 'business' => 'Ibom Fresh Organics Hub', 'phone' => '+2348099020002', 'shop' => 'Ibom Fresh Eket Coldroom', 'slug' => 'demo-ibom-fresh', 'lga' => $eket ? $eket->id : 2],
            'demo.vendor3@victoriousmarket.com.ng' => ['name' => 'David Okon (Eket Style)', 'business' => 'Eket Style & Apparel House', 'phone' => '+2348099020003', 'shop' => 'Eket Style Boutique', 'slug' => 'demo-eket-style', 'lga' => $ikotEkpene ? $ikotEkpene->id : 3],
        ];
        foreach ($demoVendors as $email => $dv) {
            $u = User::updateOrCreate(['email' => $email], [
                'name' => $dv['name'], 'phone' => $dv['phone'], 'user_type' => 'vendor', 'status' => 'active', 'is_demo' => true, 'password' => $demoPasswordHash,
            ]);
            $vp = VendorProfile::updateOrCreate(['user_id' => $u->id], [
                'business_name' => $dv['business'], 'bank_name' => 'Zenith Bank PLC', 'account_number' => '101' . rand(1000000, 9999999), 'account_name' => $dv['business'], 'kyc_status' => 'verified', 'marketplace_status' => 'active',
            ]);
            Shop::updateOrCreate(['slug' => $dv['slug']], [
                'vendor_id' => $vp->id, 'name' => $dv['shop'], 'address' => 'Plaza Center ' . $dv['shop'], 'country_id' => $nigeria ? $nigeria->id : 1, 'state_id' => $state ? $state->id : 1, 'lga_id' => $dv['lga'], 'phone' => $dv['phone'], 'email' => $email, 'is_primary_branch' => true, 'pickup_enabled' => true,
            ]);
        }

        // C. Counter Staff (3 Demo Personas)
        $demoCounter = [
            'demo.counter1@victoriousmarket.com.ng' => ['name' => 'Idara Bassey', 'phone' => '+2348099030001', 'role' => 'inspection_staff'],
            'demo.counter2@victoriousmarket.com.ng' => ['name' => 'Samuel Udoh', 'phone' => '+2348099030002', 'role' => 'cashier'],
            'demo.counter3@victoriousmarket.com.ng' => ['name' => 'Grace Sunday', 'phone' => '+2348099030003', 'role' => 'manager'],
        ];
        foreach ($demoCounter as $email => $dc) {
            $u = User::updateOrCreate(['email' => $email], [
                'name' => $dc['name'], 'phone' => $dc['phone'], 'user_type' => 'vendor_employee', 'status' => 'active', 'is_demo' => true, 'password' => $demoPasswordHash,
            ]);
            VendorEmployeeProfile::updateOrCreate(['user_id' => $u->id], [
                'vendor_id' => $baseVendor->id, 'shop_id' => $baseShop->id, 'role' => $dc['role'],
            ]);
        }

        // D. Logistics Fleet (3 Demo Personas)
        $demoLogistics = [
            'demo.logistics1@victoriousmarket.com.ng' => ['name' => 'Akwa Express Logistics Ltd', 'phone' => '+2348099040001', 'fleet' => 15, 'lga' => $uyo ? $uyo->id : 1],
            'demo.logistics2@victoriousmarket.com.ng' => ['name' => 'Cross River FastWay Cargo', 'phone' => '+2348099040002', 'fleet' => 28, 'lga' => $eket ? $eket->id : 2],
            'demo.logistics3@victoriousmarket.com.ng' => ['name' => 'Urban Swift Couriers Ltd', 'phone' => '+2348099040003', 'fleet' => 10, 'lga' => $ikotEkpene ? $ikotEkpene->id : 3],
        ];
        foreach ($demoLogistics as $email => $dl) {
            $u = User::updateOrCreate(['email' => $email], [
                'name' => $dl['name'], 'phone' => $dl['phone'], 'user_type' => 'logistics_company', 'status' => 'active', 'is_demo' => true, 'password' => $demoPasswordHash,
            ]);
            LogisticsCompanyProfile::updateOrCreate(['user_id' => $u->id], [
                'company_name' => $dl['name'], 'contact_email' => $email, 'contact_phone' => $dl['phone'], 'lga_id' => $dl['lga'], 'fleet_size' => $dl['fleet'], 'status' => 'active',
            ]);
        }

        // E. Delivery Riders (3 Demo Personas)
        $demoRiders = [
            'demo.rider1@victoriousmarket.com.ng' => ['name' => 'Edidiong Udoh', 'phone' => '+2348099050001', 'plate' => 'UYO-123-AK', 'vehicle' => 'motorcycle', 'lga' => $uyo ? $uyo->id : 1],
            'demo.rider2@victoriousmarket.com.ng' => ['name' => 'Aniefiok Ekpo', 'phone' => '+2348099050002', 'plate' => 'EKT-456-AK', 'vehicle' => 'motorcycle', 'lga' => $eket ? $eket->id : 2],
            'demo.rider3@victoriousmarket.com.ng' => ['name' => 'Ubong Etim', 'phone' => '+2348099050003', 'plate' => 'IKT-789-AK', 'vehicle' => 'van', 'lga' => $ikotEkpene ? $ikotEkpene->id : 3],
        ];
        foreach ($demoRiders as $email => $dr) {
            $u = User::updateOrCreate(['email' => $email], [
                'name' => $dr['name'], 'phone' => $dr['phone'], 'user_type' => 'rider', 'status' => 'active', 'is_demo' => true, 'password' => $demoPasswordHash,
            ]);
            RiderProfile::updateOrCreate(['user_id' => $u->id], [
                'logistics_company_id' => $baseLogistics->id, 'assigned_lga_id' => $dr['lga'], 'vehicle_type' => $dr['vehicle'], 'license_plate' => $dr['plate'], 'is_active' => true, 'is_online' => true,
            ]);
        }

        // F. Customers (3 Demo Personas)
        $demoCustomers = [
            'demo.customer1@victoriousmarket.com.ng' => ['name' => 'Emeka Okonkwo', 'phone' => '+2348099060001', 'lga' => $uyo ? $uyo->id : 1, 'address' => '42 Oron Road, Uyo Urban Center'],
            'demo.customer2@victoriousmarket.com.ng' => ['name' => 'Amina Bello', 'phone' => '+2348099060002', 'lga' => $eket ? $eket->id : 2, 'address' => '15 Marina Road, Eket'],
            'demo.customer3@victoriousmarket.com.ng' => ['name' => 'Chinedu Eze', 'phone' => '+2348099060003', 'lga' => $ikotEkpene ? $ikotEkpene->id : 3, 'address' => '8 Commercial Avenue, Ikot Ekpene'],
        ];
        foreach ($demoCustomers as $email => $dc) {
            $u = User::updateOrCreate(['email' => $email], [
                'name' => $dc['name'], 'phone' => $dc['phone'], 'user_type' => 'customer', 'status' => 'active', 'is_demo' => true, 'password' => $demoPasswordHash,
            ]);
            CustomerAddress::updateOrCreate(['user_id' => $u->id], [
                'contact_name' => $dc['name'], 'phone' => $dc['phone'], 'address_line' => $dc['address'], 'country_id' => $nigeria ? $nigeria->id : 1, 'state_id' => $state ? $state->id : 1, 'lga_id' => $dc['lga'], 'is_default' => true,
            ]);
        }

        // =========================================================================
        // 4. CATALOG CATEGORIES & PRODUCTS
        // =========================================================================
        $catElectronics = Category::updateOrCreate(['slug' => 'electronics-gadgets'], [
            'name' => 'Electronics & Gadgets', 'priority' => 1, 'is_active' => true,
        ]);
        $catGroceries = Category::updateOrCreate(['slug' => 'groceries-farm-produce'], [
            'name' => 'Groceries & Farm Produce', 'priority' => 2, 'is_active' => true,
        ]);

        $prod1 = Product::updateOrCreate(['slug' => 'samsung-galaxy-s24-ultra-512gb'], [
            'vendor_id' => $baseVendor->id,
            'shop_id' => $baseShop->id,
            'category_id' => $catElectronics->id,
            'name' => 'Samsung Galaxy S24 Ultra (512GB Platinum)',
            'unit_price' => '1450000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 12,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subHours(2),
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        $prod2 = Product::updateOrCreate(['slug' => 'fresh-ibom-palm-oil-5l'], [
            'vendor_id' => $baseVendor->id,
            'shop_id' => $baseShop->id,
            'category_id' => $catGroceries->id,
            'name' => 'Fresh Ibom Grade-A Palm Oil (5L Sealed)',
            'unit_price' => '9500.00',
            'is_in_stock' => true,
            'inventory_quantity' => 45,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subHours(4),
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        // =========================================================================
        // 5. SAMPLE ORDER & CASHBACK REWARDS LEDGER
        // =========================================================================
        $order = Order::updateOrCreate(['order_number' => 'VM-ORD-DEMO-001'], [
            'customer_id' => $baseCustomer->id,
            'vendor_id' => $baseVendor->id,
            'shop_id' => $baseShop->id,
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'payment_reference' => 'PAY-DEMO-001',
            'fulfillment_type' => 'doorstep_delivery',
            'origin_lga_id' => $uyo ? $uyo->id : 1,
            'destination_lga_id' => $uyo ? $uyo->id : 1,
            'merchandise_subtotal' => '1450000.00',
            'delivery_fee' => '2500.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => '1452500.00',
            'vendor_net_90' => '1305000.00',
            'customer_cashback_5' => '72500.00',
            'platform_margin_5' => '72500.00',
            'logistics_fee_retained' => '2500.00',
            'verification_code' => '482910',
            'vendor_settlement_status' => 'eligible',
        ]);

        OrderItem::updateOrCreate([
            'order_id' => $order->id,
            'product_id' => $prod1->id,
        ], [
            'product_name' => $prod1->name,
            'unit_price' => $prod1->unit_price,
            'quantity' => 1,
            'line_total' => '1450000.00',
        ]);

        CustomerCashbackLedger::updateOrCreate(['order_id' => $order->id], [
            'customer_id' => $baseCustomer->id,
            'amount' => '72500.00',
            'status' => 'available',
            'matures_at' => Carbon::now()->subDays(2),
            'expires_at' => Carbon::now()->addDays(363),
        ]);

        $this->command->info('✅ Seeded Super Admin strictly from ENV + Base standard accounts + 18 Demo Personas across all 6 roles.');
    }
}