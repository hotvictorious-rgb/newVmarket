<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\AdminEmployeeProfile;
use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\CustomerCashbackLedger;
use App\Models\Lga;
use App\Models\LogisticsCollectionPoint;
use App\Models\LogisticsCompanyProfile;
use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupReservation;
use App\Models\Product;
use App\Models\Review;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\SupplierOffer;
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
        $oron = Lga::where('name', 'Oron')->first();

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
        // 2. ADMIN EMPLOYEES (All 4 DB Enum Roles: finance, compliance, support, dispatch)
        // =========================================================================
        $adminStaffList = [
            'finance@victoriousmarket.com.ng' => [
                'name' => 'Finance Controller (Treasury)',
                'phone' => '+2348011110002',
                'role' => 'finance',
                'perms' => ['view_settlements', 'disburse_settlements', 'view_ledgers', 'reverse_transactions', 'manage_treasury'],
            ],
            'demo.staff1@victoriousmarket.com.ng' => [
                'name' => 'Blessing Akpan',
                'phone' => '+2348099010001',
                'role' => 'compliance',
                'perms' => ['moderate_catalog', 'verify_merchants', 'view_products', 'approve_products', 'reject_products'],
            ],
            'demo.staff2@victoriousmarket.com.ng' => [
                'name' => 'Iniobong Effiong',
                'phone' => '+2348099010002',
                'role' => 'support',
                'perms' => ['resolve_disputes', 'view_settlements', 'mediate_disputes', 'authorize_refunds', 'view_orders'],
            ],
            'demo.staff3@victoriousmarket.com.ng' => [
                'name' => 'Kufre Asuquo',
                'phone' => '+2348099010003',
                'role' => 'dispatch',
                'perms' => ['manage_lanes', 'assign_riders', 'view_dispatch', 'assign_corridors', 'monitor_lanes'],
            ],
            'demo.staff.compliance@victoriousmarket.com.ng' => [
                'name' => 'Bassey Ntuk (Compliance Auditor)',
                'phone' => '+2348099010004',
                'role' => 'compliance',
                'perms' => ['audit_merchants', 'verify_kyc', 'suspend_vendors', 'enforce_penalties', 'view_legal_audit'],
            ],
        ];

        foreach ($adminStaffList as $email => $data) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'user_type' => 'admin_employee',
                'status' => 'active',
                'is_demo' => true,
                'password' => $demoPasswordHash,
            ]);
            AdminEmployeeProfile::updateOrCreate(['user_id' => $user->id], [
                'role' => $data['role'],
                'permissions' => $data['perms'],
            ]);
        }

        // =========================================================================
        // 3. VENDOR MERCHANTS & SHOPS (4 Distinct Verticals, is_demo = true)
        // =========================================================================
        $vendorList = [
            'vendor@victoriousmarket.com.ng' => [
                'name' => 'Victor Tech Solutions (Base)',
                'business' => 'Victor Tech Solutions',
                'phone' => '+2348022220001',
                'shop' => 'Victor Tech Uyo Flagship Plaza',
                'slug' => 'victor-tech-uyo-plaza',
                'address' => 'Shop 12, Ibom Plaza Commercial Center, Uyo',
                'lga_id' => $uyo ? $uyo->id : 1,
                'bank_name' => 'Zenith Bank PLC',
                'account_num' => '1012345678',
                'balance' => '450000.00',
            ],
            'demo.vendor1@victoriousmarket.com.ng' => [
                'name' => 'Victor Bassey (Victor Tech)',
                'business' => 'Victor Tech Electronics Ltd',
                'phone' => '+2348099020001',
                'shop' => 'Victor Tech Uyo Flagship Plaza',
                'slug' => 'demo-victor-tech',
                'address' => 'Shop 12, Ibom Plaza Commercial Center, Uyo',
                'lga_id' => $uyo ? $uyo->id : 1,
                'bank_name' => 'Zenith Bank PLC',
                'account_num' => '1019876543',
                'balance' => '1800000.00',
            ],
            'vendor@example.com' => [
                'name' => 'Victor Tech Solutions (Mobile Terminal)',
                'business' => 'Victor Tech Mobile Store',
                'phone' => '+2348022220099',
                'shop' => 'Victor Tech Mobile Terminal',
                'slug' => 'vendor-example-tech',
                'address' => 'Suite 4, Commercial Plaza, Uyo',
                'lga_id' => $uyo ? $uyo->id : 1,
                'bank_name' => 'Zenith Bank PLC',
                'account_num' => '1015556677',
                'balance' => '320000.00',
            ],
            'demo.vendor2@victoriousmarket.com.ng' => [
                'name' => 'Mercy Etim (Ibom Fresh)',
                'business' => 'Ibom Fresh Organics Hub',
                'phone' => '+2348099020002',
                'shop' => 'Ibom Fresh Eket Coldroom & Depot',
                'slug' => 'demo-ibom-fresh',
                'address' => '24 Marina Road, Eket Waterfront Market',
                'lga_id' => $eket ? $eket->id : 2,
                'bank_name' => 'Access Bank PLC',
                'account_num' => '0087654321',
                'balance' => '850000.00',
            ],
            'demo.vendor3@victoriousmarket.com.ng' => [
                'name' => 'David Okon (Eket Style)',
                'business' => 'Eket Style & Apparel House',
                'phone' => '+2348099020003',
                'shop' => 'Eket Style Fashion Boutique',
                'slug' => 'demo-eket-style',
                'address' => '15 Ikot Ekpene Road, Commercial Hub',
                'lga_id' => $ikotEkpene ? $ikotEkpene->id : 3,
                'bank_name' => 'First Bank of Nigeria',
                'account_num' => '2034567890',
                'balance' => '620000.00',
            ],
            'demo.vendor4@victoriousmarket.com.ng' => [
                'name' => 'Anietie Archibong (Calabar Living)',
                'business' => 'Calabar Living Depot',
                'phone' => '+2348099020004',
                'shop' => 'Calabar Living & Kitchen Depot',
                'slug' => 'demo-calabar-living',
                'address' => '3 Oron Commercial Highway, Oron',
                'lga_id' => $oron ? $oron->id : 4,
                'bank_name' => 'United Bank for Africa (UBA)',
                'account_num' => '1098765432',
                'balance' => '540000.00',
            ],
        ];

        $seededShops = [];
        $seededVendors = [];

        foreach ($vendorList as $email => $vData) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $vData['name'],
                'phone' => $vData['phone'],
                'user_type' => 'vendor',
                'status' => 'active',
                'is_demo' => true,
                'password' => $demoPasswordHash,
            ]);

            $vendorProfile = VendorProfile::updateOrCreate(['user_id' => $user->id], [
                'business_name' => $vData['business'],
                'bank_name' => $vData['bank_name'],
                'account_number' => $vData['account_num'],
                'account_name' => $vData['business'],
                'kyc_status' => 'verified',
                'marketplace_status' => 'active',
                'bank_updated_at' => Carbon::now()->subDays(5),
            ]);

            $shop = Shop::updateOrCreate(['slug' => $vData['slug']], [
                'vendor_id' => $vendorProfile->id,
                'name' => $vData['shop'],
                'address' => $vData['address'],
                'country_id' => $nigeria ? $nigeria->id : 1,
                'state_id' => $state ? $state->id : 1,
                'lga_id' => $vData['lga_id'],
                'phone' => $vData['phone'],
                'email' => $email,
                'is_primary_branch' => true,
                'pickup_enabled' => true,
                'is_active' => true,
            ]);

            // DatabaseSeeder intentionally suppresses model events, so seeders must
            // explicitly establish the same safe local coverage as runtime shop creation.
            $shopLga = Lga::query()->find($shop->lga_id);
            if ($shopLga) {
                \App\Models\ShopDeliveryCoverage::firstOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'state_id' => $shopLga->state_id,
                        'lga_id' => $shopLga->id,
                    ],
                    ['is_active' => true]
                );
            }

            $seededVendors[$email] = $vendorProfile;
            $seededShops[$email] = $shop;
        }

        // =========================================================================
        // 4. IN-SHOP BRANCH COUNTER / INSPECTION STAFF (Roles: inspection_staff, cashier, manager)
        // =========================================================================
        $counterStaffList = [
            'staff@victoriousmarket.com.ng' => [
                'name' => 'Counter Inspection Clerk (Base)',
                'phone' => '+2348022220002',
                'role' => 'inspection_staff',
                'vendor_email' => 'vendor@victoriousmarket.com.ng',
            ],
            'demo.counter1@victoriousmarket.com.ng' => [
                'name' => 'Idara Bassey',
                'phone' => '+2348099030001',
                'role' => 'inspection_staff',
                'vendor_email' => 'demo.vendor1@victoriousmarket.com.ng',
            ],
            'demo.counter2@victoriousmarket.com.ng' => [
                'name' => 'Samuel Udoh',
                'phone' => '+2348099030002',
                'role' => 'cashier',
                'vendor_email' => 'demo.vendor2@victoriousmarket.com.ng',
            ],
            'demo.counter3@victoriousmarket.com.ng' => [
                'name' => 'Grace Sunday',
                'phone' => '+2348099030003',
                'role' => 'manager',
                'vendor_email' => 'demo.vendor3@victoriousmarket.com.ng',
            ],
        ];

        foreach ($counterStaffList as $email => $csData) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $csData['name'],
                'phone' => $csData['phone'],
                'user_type' => 'vendor_employee',
                'status' => 'active',
                'is_demo' => true,
                'password' => $demoPasswordHash,
            ]);

            $vendor = $seededVendors[$csData['vendor_email']] ?? $seededVendors['vendor@victoriousmarket.com.ng'];
            $shop = $seededShops[$csData['vendor_email']] ?? $seededShops['vendor@victoriousmarket.com.ng'];

            VendorEmployeeProfile::updateOrCreate(['user_id' => $user->id], [
                'vendor_id' => $vendor->id,
                'shop_id' => $shop->id,
                'role' => $csData['role'],
                'is_active' => true,
            ]);
        }

        // =========================================================================
        // 5. LOGISTICS FLEET CARRIERS (3 Carriers, is_demo = true)
        // =========================================================================
        $logisticsList = [
            'logistics@victoriousmarket.com.ng' => [
                'name' => 'Akwa Express Logistics Ltd (Base)',
                'phone' => '+2348033330001',
                'fleet' => 18,
                'lga_id' => $uyo ? $uyo->id : 1,
                'balance' => '54000.00',
            ],
            'demo.logistics1@victoriousmarket.com.ng' => [
                'name' => 'Akwa Express Logistics Ltd',
                'phone' => '+2348099040001',
                'fleet' => 18,
                'lga_id' => $uyo ? $uyo->id : 1,
                'balance' => '98000.00',
            ],
            'demo.logistics2@victoriousmarket.com.ng' => [
                'name' => 'Cross River FastWay Cargo Ltd',
                'phone' => '+2348099040002',
                'fleet' => 12,
                'lga_id' => $eket ? $eket->id : 2,
                'balance' => '142000.00',
            ],
            'demo.logistics3@victoriousmarket.com.ng' => [
                'name' => 'Urban Swift Couriers Ltd (Speedy Express)',
                'phone' => '+2348099040003',
                'fleet' => 10,
                'lga_id' => $ikotEkpene ? $ikotEkpene->id : 3,
                'balance' => '67000.00',
            ],
        ];

        $seededLogistics = [];

        foreach ($logisticsList as $email => $lData) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $lData['name'],
                'phone' => $lData['phone'],
                'user_type' => 'logistics_company',
                'status' => 'active',
                'is_demo' => true,
                'password' => $demoPasswordHash,
            ]);

            $logProfile = LogisticsCompanyProfile::updateOrCreate(['user_id' => $user->id], [
                'company_name' => $lData['name'],
                'contact_email' => $email,
                'contact_phone' => $lData['phone'],
                'lga_id' => $lData['lga_id'],
                'fleet_size' => $lData['fleet'],
                'wallet_balance' => $lData['balance'],
                'bank_name' => 'Zenith Bank PLC',
                'bank_code' => '057',
                'account_number' => '1019283746',
                'account_name' => $lData['name'],
                'bank_updated_at' => Carbon::now()->subDays(3),
                'status' => 'active',
            ]);

            $seededLogistics[$email] = $logProfile;
        }

        // Hub Collection Points
        $primaryLogistics = $seededLogistics['logistics@victoriousmarket.com.ng'];
        if ($uyo) {
            LogisticsCollectionPoint::updateOrCreate(['code' => 'CP-UYO-001'], [
                'logistics_company_id' => $primaryLogistics->id,
                'state_id' => $uyo->state_id,
                'lga_id' => $uyo->id,
                'name' => 'Akwa Express Central Hub — Uyo Plaza',
                'address' => '58 Aka Road, Uyo',
                'landmark' => 'Near Ibom Plaza Complex',
                'contact_phone' => '+2348033330001',
                'contact_person' => 'Aniefiok Akpan',
                'operating_hours' => '07:00 AM - 07:00 PM',
                'is_active' => true,
            ]);
        }
        if ($eket) {
            LogisticsCollectionPoint::updateOrCreate(['code' => 'CP-EKE-001'], [
                'logistics_company_id' => $primaryLogistics->id,
                'state_id' => $eket->state_id,
                'lga_id' => $eket->id,
                'name' => 'Akwa Express Station & Eket Transit Hub',
                'address' => '22 Marina Road, Eket Urban Plaza',
                'landmark' => 'Opposite Eket Central Park',
                'contact_phone' => '+2348033330002',
                'contact_person' => 'Bassey Effiong',
                'operating_hours' => '07:30 AM - 06:30 PM',
                'is_active' => true,
            ]);
        }
        if ($ikotEkpene) {
            LogisticsCollectionPoint::updateOrCreate(['code' => 'CP-IKO-001'], [
                'logistics_company_id' => $primaryLogistics->id,
                'state_id' => $ikotEkpene->state_id,
                'lga_id' => $ikotEkpene->id,
                'name' => 'Peace Mass Transit & Akwa Express Depo',
                'address' => '14 Aba Road, Ikot Ekpene',
                'landmark' => 'Beside Zenith Bank',
                'contact_phone' => '+2348033330003',
                'contact_person' => 'Iniobong Udoh',
                'operating_hours' => '08:00 AM - 06:00 PM',
                'is_active' => true,
            ]);
        }

        // =========================================================================
        // 6. DELIVERY RIDERS (Linked to Carriers, is_demo = true)
        // =========================================================================
        $riderList = [
            'rider@victoriousmarket.com.ng' => [
                'name' => 'Edidiong Udoh (Base)',
                'phone' => '+2348044440001',
                'plate' => 'UYO-123-AK',
                'vehicle' => 'motorcycle',
                'lga_id' => $uyo ? $uyo->id : 1,
                'carrier' => 'logistics@victoriousmarket.com.ng',
            ],
            'demo.rider1@victoriousmarket.com.ng' => [
                'name' => 'Edidiong Udoh',
                'phone' => '+2348099050001',
                'plate' => 'UYO-401-AKW',
                'vehicle' => 'motorcycle',
                'lga_id' => $uyo ? $uyo->id : 1,
                'carrier' => 'demo.logistics1@victoriousmarket.com.ng',
            ],
            'musa@speedyfleet.ng' => [
                'name' => 'Musa Danjuma (Speedy Fleet)',
                'phone' => '+2348099050004',
                'plate' => 'SPF-109-MC',
                'vehicle' => 'motorcycle',
                'lga_id' => $uyo ? $uyo->id : 1,
                'carrier' => 'demo.logistics3@victoriousmarket.com.ng',
            ],
            'demo.rider2@victoriousmarket.com.ng' => [
                'name' => 'Aniefiok Ekpo',
                'phone' => '+2348099050002',
                'plate' => 'EKT-218-KE',
                'vehicle' => 'motorcycle',
                'lga_id' => $eket ? $eket->id : 2,
                'carrier' => 'demo.logistics1@victoriousmarket.com.ng',
            ],
            'demo.rider3@victoriousmarket.com.ng' => [
                'name' => 'Ubong Etim',
                'phone' => '+2348099050003',
                'plate' => 'IKT-778-VAN',
                'vehicle' => 'van',
                'lga_id' => $ikotEkpene ? $ikotEkpene->id : 3,
                'carrier' => 'demo.logistics2@victoriousmarket.com.ng',
            ],
            'ubong.rider@vanlogistics.ng' => [
                'name' => 'Ubong Etim (Van Logistics)',
                'phone' => '+2348099050005',
                'plate' => 'IKT-992-VAN',
                'vehicle' => 'van',
                'lga_id' => $ikotEkpene ? $ikotEkpene->id : 3,
                'carrier' => 'demo.logistics2@victoriousmarket.com.ng',
            ],
        ];

        $seededRiders = [];

        foreach ($riderList as $email => $rData) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $rData['name'],
                'phone' => $rData['phone'],
                'user_type' => 'rider',
                'status' => 'active',
                'is_demo' => true,
                'password' => $demoPasswordHash,
            ]);

            $carrier = $seededLogistics[$rData['carrier']] ?? $primaryLogistics;

            $profile = RiderProfile::updateOrCreate(['user_id' => $user->id], [
                'logistics_company_id' => $carrier->id,
                'assigned_lga_id' => $rData['lga_id'],
                'vehicle_type' => $rData['vehicle'],
                'license_plate' => $rData['plate'],
                'is_active' => true,
                'is_online' => true,
            ]);

            $seededRiders[$email] = ['user' => $user, 'profile' => $profile];
        }

        // =========================================================================
        // 7. CUSTOMER BUYERS (Addresses, Orders, Cashback, is_demo = true)
        // =========================================================================
        $customerList = [
            'customer@victoriousmarket.com.ng' => [
                'name' => 'Emeka Okonkwo (Base)',
                'phone' => '+2348055550001',
                'address' => '42 Oron Road, Uyo Urban Center',
                'lga_id' => $uyo ? $uyo->id : 1,
            ],
            'demo.customer1@victoriousmarket.com.ng' => [
                'name' => 'Emeka Okonkwo',
                'phone' => '+2348099060001',
                'address' => '42 Oron Road, Uyo Urban Center',
                'lga_id' => $uyo ? $uyo->id : 1,
            ],
            'demo.customer2@victoriousmarket.com.ng' => [
                'name' => 'Amina Bello',
                'phone' => '+2348099060002',
                'address' => '15 Marina Highway, Eket Coastal Lane',
                'lga_id' => $eket ? $eket->id : 2,
            ],
            'demo.customer3@victoriousmarket.com.ng' => [
                'name' => 'Chinedu Eze',
                'phone' => '+2348099060003',
                'address' => '8 Commercial Avenue, Ikot Ekpene',
                'lga_id' => $ikotEkpene ? $ikotEkpene->id : 3,
            ],
        ];

        $seededCustomers = [];

        foreach ($customerList as $email => $cData) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $cData['name'],
                'phone' => $cData['phone'],
                'user_type' => 'customer',
                'status' => 'active',
                'is_demo' => true,
                'password' => $demoPasswordHash,
            ]);

            CustomerAddress::updateOrCreate(['user_id' => $user->id], [
                'contact_name' => $cData['name'],
                'phone' => $cData['phone'],
                'address_line' => $cData['address'],
                'country_id' => $nigeria ? $nigeria->id : 1,
                'state_id' => $state ? $state->id : 1,
                'lga_id' => $cData['lga_id'],
                'is_default' => true,
            ]);

            $seededCustomers[$email] = $user;
        }

        // =========================================================================
        // 8. PRODUCT CATALOG CATEGORIES & PRODUCTS ACROSS ALL VERTICALS
        // =========================================================================
        $catElectronics = Category::updateOrCreate(['slug' => 'electronics-gadgets'], [
            'name' => 'Electronics & Gadgets', 'priority' => 1, 'is_active' => true,
        ]);
        $catGroceries = Category::updateOrCreate(['slug' => 'groceries-farm-produce'], [
            'name' => 'Groceries & Farm Produce', 'priority' => 2, 'is_active' => true,
        ]);
        $catFashion = Category::updateOrCreate(['slug' => 'fashion-apparel'], [
            'name' => 'Fashion & Apparel', 'priority' => 3, 'is_active' => true,
        ]);
        $catHome = Category::updateOrCreate(['slug' => 'home-kitchen-living'], [
            'name' => 'Home, Kitchen & Living', 'priority' => 4, 'is_active' => true,
        ]);

        $vendorTech = $seededVendors['demo.vendor1@victoriousmarket.com.ng'];
        $shopTech = $seededShops['demo.vendor1@victoriousmarket.com.ng'];

        $vendorFresh = $seededVendors['demo.vendor2@victoriousmarket.com.ng'];
        $shopFresh = $seededShops['demo.vendor2@victoriousmarket.com.ng'];

        $vendorStyle = $seededVendors['demo.vendor3@victoriousmarket.com.ng'];
        $shopStyle = $seededShops['demo.vendor3@victoriousmarket.com.ng'];

        $vendorHome = $seededVendors['demo.vendor4@victoriousmarket.com.ng'];
        $shopHome = $seededShops['demo.vendor4@victoriousmarket.com.ng'];

        // Products for Victor Tech
        $prodTech1 = Product::updateOrCreate(['slug' => 'samsung-galaxy-s24-ultra-512gb'], [
            'vendor_id' => $vendorTech->id,
            'shop_id' => $shopTech->id,
            'category_id' => $catElectronics->id,
            'name' => 'Samsung Galaxy S24 Ultra (512GB Platinum)',
            'unit_price' => '1450000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 15,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subHours(2),
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        $prodTech2 = Product::updateOrCreate(['slug' => 'apple-macbook-air-m3-512gb'], [
            'vendor_id' => $vendorTech->id,
            'shop_id' => $shopTech->id,
            'category_id' => $catElectronics->id,
            'name' => 'Apple MacBook Air M3 (16GB RAM, 512GB SSD)',
            'unit_price' => '1850000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 8,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subHours(5),
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        $prodTech3 = Product::updateOrCreate(['slug' => 'sony-wh-1000xm5-wireless-anc'], [
            'vendor_id' => $vendorTech->id,
            'shop_id' => $shopTech->id,
            'category_id' => $catElectronics->id,
            'name' => 'Sony WH-1000XM5 Wireless Noise-Cancelling Headphones',
            'unit_price' => '380000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 20,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subDay(),
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        // Products for Ibom Fresh
        $prodFresh1 = Product::updateOrCreate(['slug' => 'fresh-ibom-palm-oil-5l'], [
            'vendor_id' => $vendorFresh->id,
            'shop_id' => $shopFresh->id,
            'category_id' => $catGroceries->id,
            'name' => 'Fresh Ibom Grade-A Palm Oil (5L Sealed)',
            'unit_price' => '9500.00',
            'is_in_stock' => true,
            'inventory_quantity' => 50,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subHours(4),
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        $prodFresh2 = Product::updateOrCreate(['slug' => 'premium-oron-crayfish-1kg'], [
            'vendor_id' => $vendorFresh->id,
            'shop_id' => $shopFresh->id,
            'category_id' => $catGroceries->id,
            'name' => 'Premium Oron Smoked Crayfish (1kg Export Pack)',
            'unit_price' => '18500.00',
            'is_in_stock' => true,
            'inventory_quantity' => 35,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subHours(6),
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        // Products for Eket Style
        $prodStyle1 = Product::updateOrCreate(['slug' => 'handwoven-akwa-ibom-attire'], [
            'vendor_id' => $vendorStyle->id,
            'shop_id' => $shopStyle->id,
            'category_id' => $catFashion->id,
            'name' => 'Handwoven Akwa Ibom Traditional Attire (Men 2-Piece)',
            'unit_price' => '65000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 12,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subHours(8),
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        // Products for Calabar Living
        $prodHome1 = Product::updateOrCreate(['slug' => 'master-chef-3-in-1-blender'], [
            'vendor_id' => $vendorHome->id,
            'shop_id' => $shopHome->id,
            'category_id' => $catHome->id,
            'name' => 'Master Chef 3-in-1 Heavy Duty Blender & Grinder',
            'unit_price' => '45000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 25,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subDay(),
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        // Also associate products with base vendor for backward compatibility
        $baseVendor = $seededVendors['vendor@victoriousmarket.com.ng'];
        $baseShop = $seededShops['vendor@victoriousmarket.com.ng'];
        $prodTech1->update(['vendor_id' => $baseVendor->id, 'shop_id' => $baseShop->id]);
        $prodFresh1->update(['vendor_id' => $baseVendor->id, 'shop_id' => $baseShop->id]);

        // Seed active, confirmed supplier offers for all demo products (retailer sourcing model)
        foreach (Product::all() as $prod) {
            if ($prod->vendor_id && $prod->shop_id) {
                SupplierOffer::updateOrCreate([
                    'product_id' => $prod->id,
                    'vendor_id' => $prod->vendor_id,
                    'shop_id' => $prod->shop_id,
                    'wholesale_cost' => ($prod->cost_price && bccomp((string) $prod->cost_price, '0.00', 2) > 0)
                        ? (string) $prod->cost_price
                        : bcmul((string) $prod->unit_price, '0.85', 2),
                    'is_available' => true,
                    'availability_confirmed_at' => Carbon::now()->subHours(2),
                    'availability_confirmed_by' => $superAdmin->id,
                    'availability_expires_at' => Carbon::now()->addDays(7),
                ]);
            }
        }

        // =========================================================================
        // 9. ORDERS & REWARDS ACROSS ALL WORKFLOW STAGES
        // =========================================================================
        $customer1 = $seededCustomers['demo.customer1@victoriousmarket.com.ng'];
        $customer2 = $seededCustomers['demo.customer2@victoriousmarket.com.ng'];
        $customer3 = $seededCustomers['demo.customer3@victoriousmarket.com.ng'];

        // Order 1: Confirmed / Paid, Doorstep Delivery In-Transit
        $order1 = Order::updateOrCreate(['order_number' => 'VM-ORD-DEMO-001'], [
            'customer_id' => $customer1->id,
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
            'logistics_fee_retained' => '2250.00',
            'platform_delivery_fee' => '250.00',
            'verification_code' => '482910',
            'vendor_settlement_status' => 'eligible',
        ]);

        OrderItem::updateOrCreate([
            'order_id' => $order1->id,
            'product_id' => $prodTech1->id,
        ], [
            'product_name' => $prodTech1->name,
            'quantity' => 1,
            'unit_price' => $prodTech1->unit_price,
            'line_total' => $prodTech1->unit_price,
        ]);

        CustomerCashbackLedger::updateOrCreate(['order_id' => $order1->id], [
            'customer_id' => $customer1->id,
            'amount' => '72500.00',
            'status' => 'available',
            'matures_at' => Carbon::now()->subDays(2),
            'expires_at' => Carbon::now()->addDays(90),
        ]);

        // Order 2: In-Shop Pickup Inspection Pass (Fresh Farm Produce)
        $order2 = Order::updateOrCreate(['order_number' => 'VM-ORD-DEMO-002'], [
            'customer_id' => $customer3->id,
            'vendor_id' => $vendorFresh->id,
            'shop_id' => $shopFresh->id,
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'payment_reference' => 'PAY-DEMO-002',
            'fulfillment_type' => 'in_shop_pickup',
            'origin_lga_id' => $eket ? $eket->id : 2,
            'destination_lga_id' => $eket ? $eket->id : 2,
            'merchandise_subtotal' => '28000.00',
            'delivery_fee' => '0.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => '28000.00',
            'vendor_net_90' => '25200.00',
            'customer_cashback_5' => '1400.00',
            'platform_margin_5' => '1400.00',
            'logistics_fee_retained' => '0.00',
            'platform_delivery_fee' => '0.00',
            'verification_code' => '619340',
            'vendor_settlement_status' => 'held',
        ]);

        OrderItem::updateOrCreate([
            'order_id' => $order2->id,
            'product_id' => $prodFresh1->id,
        ], [
            'product_name' => $prodFresh1->name,
            'quantity' => 1,
            'unit_price' => '9500.00',
            'line_total' => '9500.00',
        ]);

        OrderItem::updateOrCreate([
            'order_id' => $order2->id,
            'product_id' => $prodFresh2->id,
        ], [
            'product_name' => $prodFresh2->name,
            'quantity' => 1,
            'unit_price' => '18500.00',
            'line_total' => '18500.00',
        ]);

        PickupReservation::updateOrCreate(['reservation_code' => 'RES-IBOM-002'], [
            'customer_id' => $customer3->id,
            'vendor_id' => $vendorFresh->id,
            'shop_id' => $shopFresh->id,
            'product_id' => $prodFresh1->id,
            'quantity' => 1,
            'unit_price' => '9500.00',
            'estimated_total' => '28000.00',
            'status' => 'reserved',
            'order_id' => $order2->id,
            'expires_at' => Carbon::now()->addDays(3),
        ]);

        // Order 3: Delivered & Reviewed Order
        $order3 = Order::updateOrCreate(['order_number' => 'VM-ORD-DEMO-003'], [
            'customer_id' => $customer2->id,
            'vendor_id' => $vendorStyle->id,
            'shop_id' => $shopStyle->id,
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'wallet',
            'payment_reference' => 'WAL-DEMO-003',
            'fulfillment_type' => 'doorstep_delivery',
            'origin_lga_id' => $ikotEkpene ? $ikotEkpene->id : 3,
            'destination_lga_id' => $eket ? $eket->id : 2,
            'merchandise_subtotal' => '65000.00',
            'delivery_fee' => '3500.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => '68500.00',
            'vendor_net_90' => '58500.00',
            'customer_cashback_5' => '3250.00',
            'platform_margin_5' => '3250.00',
            'logistics_fee_retained' => '3150.00',
            'platform_delivery_fee' => '350.00',
            'verification_code' => '830129',
            'vendor_settlement_status' => 'settled',
        ]);

        OrderItem::updateOrCreate([
            'order_id' => $order3->id,
            'product_id' => $prodStyle1->id,
        ], [
            'product_name' => $prodStyle1->name,
            'quantity' => 1,
            'unit_price' => $prodStyle1->unit_price,
            'line_total' => $prodStyle1->unit_price,
        ]);

        Review::updateOrCreate([
            'order_id' => $order3->id,
            'product_id' => $prodStyle1->id,
        ], [
            'customer_id' => $customer2->id,
            'rating' => 5,
            'headline' => 'Exceptional Authentic Akwa Ibom Craftsmanship!',
            'comment' => 'The handwoven fabric arrived in pristine condition via express courier. In-shop inspection and delivery handshake was seamless!',
            'is_verified_buyer' => true,
            'status' => 'active',
        ]);

        // =========================================================================
        // 10. STOREFRONT BANNERS
        // =========================================================================
        Banner::updateOrCreate(
            ['title' => 'Seamless Shopping, Swift Logistics', 'banner_type' => 'hero_slider'],
            [
                'template_style' => 'brand_prestige',
                'subtitle' => "Akwa Ibom's Official Omnichannel Platform • Physical In-Shop Inspection • Escrow Protected",
                'badge_text' => '👑 OFFICIAL FLAGSHIP MARKETPLACE',
                'discount_tag' => '★ OFFICIAL PLATFORM',
                'feature_tags' => 'Physical In-Shop Inspection, Escrow Protected, Dual-OTP Handshake',
                'bg_gradient' => 'linear-gradient(135deg, #2E0854 0%, #4C1D95 100%)',
                'text_color' => '#FFFFFF',
                'cta_text' => 'Shop Verified Items',
                'cta_url' => '#catalog',
                'is_active' => true,
                'sort_order' => 1,
                'image_path' => 'assets/img/vm_icon.jpg',
            ]
        );

        Banner::updateOrCreate(
            ['title' => 'Directional LGA Transit Across Akwa Ibom', 'banner_type' => 'hero_slider'],
            [
                'template_style' => 'minimal_bento',
                'subtitle' => 'Same-Day Corridor Shuttles connecting Uyo Plaza, Eket, Ikot Ekpene, and Oron commercial hubs',
                'badge_text' => 'LOGISTICS CORRIDOR SPECIAL',
                'discount_tag' => 'FROM ₦1,500',
                'feature_tags' => 'Same-Day Shuttles, Uyo ↔ Eket Corridor, Ikot Ekpene Hub',
                'bg_gradient' => 'linear-gradient(135deg, #F8FAFC 0%, #EDE9FE 100%)',
                'text_color' => '#0F172A',
                'cta_text' => 'Explore Corridors',
                'cta_url' => '#catalog',
                'is_active' => true,
                'sort_order' => 2,
                'image_path' => 'assets/img/vm_icon.jpg',
            ]
        );

        Banner::updateOrCreate(
            ['title' => 'Certified 7-Day Vendor Stock Freshness', 'banner_type' => 'hero_slider'],
            [
                'template_style' => 'fresh_market',
                'subtitle' => 'Direct from local farmers & merchants with physical inspection sign-off and instant rewards',
                'badge_text' => '🌱 100% FARM FRESH GUARANTEE',
                'discount_tag' => '+5% CASHBACK',
                'feature_tags' => '7-Day Freshness, Local Farm Direct, Verified Akwa Ibom Sellers',
                'bg_gradient' => 'linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%)',
                'text_color' => '#064E3B',
                'cta_text' => 'Browse Fresh Catalog',
                'cta_url' => '#catalog',
                'is_active' => true,
                'sort_order' => 3,
                'image_path' => 'assets/img/vm_icon.jpg',
            ]
        );

        Banner::updateOrCreate(
            ['title' => 'Midnight Tech & Electronics Flash Drop', 'banner_type' => 'hero_slider'],
            [
                'template_style' => 'flash_urgency',
                'subtitle' => 'Save up to 45% on authentic smartphones, audio gear, and laptops with in-shop counter warranty',
                'badge_text' => '🔥 FLASH DEAL • LIVE NOW',
                'discount_tag' => 'SAVE 45%',
                'feature_tags' => 'Limited Stock, Genuine Warranty, Instant Rider Dispatch',
                'bg_gradient' => 'linear-gradient(135deg, #0F172A 0%, #311042 100%)',
                'text_color' => '#FFFFFF',
                'cta_text' => 'Claim Tech Deals',
                'cta_url' => '#catalog',
                'is_active' => true,
                'sort_order' => 4,
                'image_path' => 'assets/img/vm_icon.jpg',
            ]
        );

        Banner::updateOrCreate(
            ['title' => '5% Customer Cashback Reward on Every Prepaid Order', 'banner_type' => 'promo_strip'],
            [
                'template_style' => 'luxury_split',
                'subtitle' => 'Auto-credited to your rewards wallet with 24-hour escrow protection',
                'badge_text' => 'SOVEREIGN 5% CASHBACK',
                'discount_tag' => '₦ REWARD',
                'feature_tags' => 'Auto-Credited, Zero Fee, Instant Redemption',
                'bg_gradient' => 'linear-gradient(135deg, #1E1B4B 0%, #4C1D95 100%)',
                'text_color' => '#FFFFFF',
                'cta_text' => 'Claim Rewards',
                'cta_url' => '#catalog',
                'is_active' => true,
                'sort_order' => 1,
                'image_path' => 'assets/img/vm_icon.jpg',
            ]
        );

        $this->command->info('✅ Seeded Super Admin strictly from ENV + All 7 Roles fully configured with Demo Accounts.');
    }
}