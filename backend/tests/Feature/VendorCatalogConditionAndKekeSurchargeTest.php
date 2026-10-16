<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\DeliveryLane;
use App\Models\Lga;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\FulfillmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorCatalogConditionAndKekeSurchargeTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected Shop $shop;
    protected Category $category;
    protected Lga $uyoLga;
    protected Lga $eketLga;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);

        $state = State::create(['country_id' => $country->id, 'name' => 'Akwa Ibom', 'code' => 'AK', 'is_active' => true]);
        $this->uyoLga = Lga::create(['state_id' => $state->id, 'name' => 'Uyo', 'code' => 'UYO', 'is_active' => true]);
        $this->eketLga = Lga::create(['state_id' => $state->id, 'name' => 'Eket', 'code' => 'EKT', 'is_active' => true]);

        SystemSetting::set('zone_intra_lga_fee', '500.00');
        SystemSetting::set('zone_bulky_cargo_surcharge', '1000.00');

        $this->vendorUser = User::create([
            'name' => 'Ibom Plaza Vendor',
            'email' => 'plaza@victoriousmarket.ng',
            'phone' => '+2348011223344',
            'password' => bcrypt('SecurePass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Ibom Tech & Gadgets',
            'contact_phone' => '+2348011223344',
            'marketplace_status' => 'active',
            'commission_rate' => 10.00,
            'bank_name' => 'Access Bank',
            'account_number' => '0123456789',
            'account_name' => 'IBOM TECH & GADGETS',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Plaza Block C Shop 12',
            'slug' => 'plaza-block-c-shop-12',
            'address' => '12 Ibom Plaza Road, Uyo',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Phones & Electronics',
            'slug' => 'phones-electronics',
            'is_active' => true,
            'priority' => 1,
        ]);
    }

    public function test_vendor_can_list_brand_new_item_with_bike_delivery(): void
    {
        Sanctum::actingAs($this->vendorUser, ['*']);

        $response = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Samsung Galaxy A15 128GB',
            'category_id' => $this->category->id,
            'unit_price' => 180000.00,
            'inventory_quantity' => 10,
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'warranty_type' => 'brand_warranty',
            'description' => 'Brand new sealed pack with 1 year manufacturer warranty.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.condition', 'brand_new')
            ->assertJsonPath('data.package_tier', 'bike')
            ->assertJsonPath('data.warranty_type', 'brand_warranty');

        $product = Product::where('name', 'Samsung Galaxy A15 128GB')->first();
        $this->assertNotNull($product);
        $this->assertEquals('brand_new', $product->condition);
        $this->assertEquals('bike', $product->package_tier);
        $this->assertEquals('Brand New (Sealed Pack)', $product->condition_label);
        $this->assertEquals('Bike Dispatch (Standard Parcel Box)', $product->package_tier_label);

        // Verify delivery fee is normal baseline with 0 surcharge
        $service = new FulfillmentAvailabilityService();
        $check = $service->checkDeliveryAvailability($this->shop->id, $this->uyoLga->id, $product->package_tier);
        $this->assertTrue($check['is_available']);
        $this->assertEquals('500.00', $check['fee']); // Baseline intra-LGA without surcharge
    }

    public function test_vendor_can_list_uk_used_item_with_keke_cargo_surcharge(): void
    {
        Sanctum::actingAs($this->vendorUser, ['*']);

        $response = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Dell OptiPlex Core i7 Desktop & Monitor Set',
            'category_id' => $this->category->id,
            'unit_price' => 160000.00,
            'slash_price' => 200000.00,
            'inventory_quantity' => 5,
            'condition' => 'uk_used',
            'package_tier' => 'keke',
            'warranty_type' => '7_days_replacement',
            'description' => 'UK used grade A desktop setup with complete accessories and monitor.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.condition', 'uk_used')
            ->assertJsonPath('data.package_tier', 'keke')
            ->assertJsonPath('data.slash_price', '200000.00');

        $product = Product::where('name', 'Dell OptiPlex Core i7 Desktop & Monitor Set')->first();
        $this->assertNotNull($product);
        $this->assertEquals('uk_used', $product->condition);
        $this->assertEquals('keke', $product->package_tier);
        $this->assertEquals('UK Used (Clean Grade A)', $product->condition_label);
        $this->assertEquals('Keke Cargo (Heavy / Bulky Surcharge)', $product->package_tier_label);
        $this->assertEquals(20, $product->discount_percent); // (200000 - 160000)/200000 = 20%

        // Verify keke automatically applies the bulky cargo surcharge (₦500 baseline + ₦1000 surcharge = ₦1500)
        $service = new FulfillmentAvailabilityService();
        $check = $service->checkDeliveryAvailability($this->shop->id, $this->uyoLga->id, $product->package_tier);
        $this->assertTrue($check['is_available']);
        $this->assertEquals('1500.00', $check['fee']); // 500.00 + 1000.00 cargo surcharge
    }

    public function test_condition_rejects_unauthorized_values(): void
    {
        Sanctum::actingAs($this->vendorUser, ['*']);

        // Explicit test: user specified ONLY brand_new and uk_used are allowed, NOT others
        $response = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Used Generator',
            'category_id' => $this->category->id,
            'unit_price' => 50000.00,
            'inventory_quantity' => 1,
            'condition' => 'others',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['condition']);

        $responseUsed = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Used Generator 2',
            'category_id' => $this->category->id,
            'unit_price' => 50000.00,
            'inventory_quantity' => 1,
            'condition' => 'refurbished',
        ]);

        $responseUsed->assertStatus(422)
            ->assertJsonValidationErrors(['condition']);
    }

    public function test_package_tier_rejects_unauthorized_values(): void
    {
        Sanctum::actingAs($this->vendorUser, ['*']);

        $response = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Heavy Cargo Crate',
            'category_id' => $this->category->id,
            'unit_price' => 50000.00,
            'inventory_quantity' => 1,
            'package_tier' => 'truck_container',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['package_tier']);
    }

    public function test_slash_price_must_be_greater_than_unit_price(): void
    {
        Sanctum::actingAs($this->vendorUser, ['*']);

        $response = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Wireless Earbuds',
            'category_id' => $this->category->id,
            'unit_price' => 20000.00,
            'slash_price' => 15000.00, // Invalid: slash price lower than selling price
            'inventory_quantity' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slash_price']);
    }

    public function test_multi_item_order_applies_keke_surcharge_if_any_item_is_keke(): void
    {
        $customer = User::create([
            'name' => 'Uyo Shopper',
            'email' => 'shopper@test.com',
            'phone' => '+2348099887766',
            'password' => bcrypt('ShopperPass123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $address = CustomerAddress::create([
            'user_id' => $customer->id,
            'contact_name' => 'Uyo Shopper',
            'phone' => '+2348099887766',
            'address_line' => '24 Wellington Bassey Way',
            'country_id' => $this->shop->country_id,
            'state_id' => $this->shop->state_id,
            'lga_id' => $this->uyoLga->id,
            'is_default' => true,
        ]);

        // Item 1: Bike Delivery
        $bikeProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Tempered Glass Screen Protector',
            'slug' => 'tempered-glass-screen-protector',
            'unit_price' => '2000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 50,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        // Item 2: Keke Cargo
        $kekeProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Ox Standing Fan 18 Inch',
            'slug' => 'ox-standing-fan-18-inch',
            'unit_price' => '45000.00',
            'condition' => 'brand_new',
            'package_tier' => 'keke',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        Sanctum::actingAs($customer, ['*']);

        // Case A: Order with ONLY bike item -> Normal delivery fee (500.00)
        $orderResBike = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $bikeProduct->id, 'quantity' => 1],
            ],
        ]);

        $orderResBike->assertStatus(201);
        $orderBike = Order::where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($orderBike);
        $this->assertEquals('500.00', $orderBike->delivery_fee);

        // Case B: Order with both bike item AND keke item -> Keke cargo surcharge applies (1500.00)
        $orderResKeke = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $bikeProduct->id, 'quantity' => 1],
                ['product_id' => $kekeProduct->id, 'quantity' => 1],
            ],
        ]);

        $orderResKeke->assertStatus(201);
        $orderKeke = Order::where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($orderKeke);
        $this->assertEquals('1500.00', $orderKeke->delivery_fee);
    }

    public function test_public_product_api_filters_by_condition_and_package_tier(): void
    {
        Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Brand New iPhone 15 Pro',
            'slug' => 'brand-new-iphone-15-pro',
            'unit_price' => '1200000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'UK Used iPhone 13 128GB',
            'slug' => 'uk-used-iphone-13-128gb',
            'unit_price' => '480000.00',
            'condition' => 'uk_used',
            'package_tier' => 'bike',
            'inventory_quantity' => 3,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        // Filter condition=uk_used
        $resUsed = $this->getJson('/api/v1/products?condition=uk_used');
        $resUsed->assertStatus(200);
        $dataUsed = $resUsed->json('data.data');
        $this->assertNotEmpty($dataUsed);
        foreach ($dataUsed as $item) {
            $this->assertEquals('uk_used', $item['condition']);
        }

        // Filter condition=brand_new
        $resNew = $this->getJson('/api/v1/products?condition=brand_new');
        $resNew->assertStatus(200);
        $dataNew = $resNew->json('data.data');
        $this->assertNotEmpty($dataNew);
        foreach ($dataNew as $item) {
            $this->assertEquals('brand_new', $item['condition']);
        }
    }

    public function test_vendor_web_portal_creates_product_with_nigerian_fields(): void
    {
        $response = $this->actingAs($this->vendorUser)->post('/vendor/products', [
            'name' => 'MacBook Pro M2 UK Used',
            'category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'condition' => 'uk_used',
            'package_tier' => 'bike',
            'warranty_type' => 'in_store_testing',
            'unit_price' => 750000.00,
            'slash_price' => 850000.00,
            'inventory_quantity' => 2,
            'description' => 'Super clean UK used MacBook Pro with original MagSafe charger.',
        ]);

        $response->assertRedirect();
        
        $product = Product::where('name', 'MacBook Pro M2 UK Used')->first();
        $this->assertNotNull($product);
        $this->assertEquals('uk_used', $product->condition);
        $this->assertEquals('bike', $product->package_tier);
        $this->assertEquals('750000.00', $product->unit_price);
        $this->assertEquals('850000.00', $product->slash_price);
        $this->assertEquals('in_store_testing', $product->warranty_type);
    }

    /**
     * Deep Check Verification:
     * Delivery fee is calculated strictly PER VENDOR child group.
     * If Vendor B has even one item requiring keke, Vendor B's delivery fee becomes the bulk delivery charge (₦1,500),
     * while Vendor A (with only bike items) remains at standard baseline delivery fee (₦500).
     * Total shipping fee is the exact sum of both independent vendor child fees (₦2,000).
     */
    public function test_cart_fulfillment_calculates_delivery_per_vendor_child_lane_with_isolated_keke_surcharge(): void
    {
        // Vendor A (Shop A) - Sells only bike items
        $productA1 = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Vendor A Bike Item 1',
            'slug' => 'vendor-a-bike-item-1',
            'unit_price' => '5000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $productA2 = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Vendor A Bike Item 2',
            'slug' => 'vendor-a-bike-item-2',
            'unit_price' => '8000.00',
            'condition' => 'uk_used',
            'package_tier' => 'bike',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        // Vendor B (Shop B) - Different vendor selling one bike item and one keke bulk item
        $vendorUserB = User::create([
            'name' => 'Vendor Beta Owner',
            'email' => 'beta@victoriousmarket.ng',
            'phone' => '+2348099881122',
            'password' => bcrypt('SecurePass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $vendorB = VendorProfile::create([
            'user_id' => $vendorUserB->id,
            'business_name' => 'Beta Home Appliances',
            'contact_phone' => '+2348099881122',
            'marketplace_status' => 'active',
            'commission_rate' => 10.00,
            'bank_name' => 'First Bank',
            'account_number' => '0223456789',
            'account_name' => 'BETA HOME APPLIANCES',
        ]);
        $shopB = Shop::create([
            'vendor_id' => $vendorB->id,
            'name' => 'Beta Appliance Mega Store',
            'slug' => 'beta-appliance-mega-store',
            'address' => '45 Ikot Ekpene Road, Uyo',
            'country_id' => $this->shop->country_id,
            'state_id' => $this->shop->state_id,
            'lga_id' => $this->uyoLga->id,
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $productB_Bike = Product::create([
            'vendor_id' => $vendorB->id,
            'shop_id' => $shopB->id,
            'category_id' => $this->category->id,
            'name' => 'Vendor B Electric Kettle',
            'slug' => 'vendor-b-electric-kettle',
            'unit_price' => '12000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 15,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $productB_Keke = Product::create([
            'vendor_id' => $vendorB->id,
            'shop_id' => $shopB->id,
            'category_id' => $this->category->id,
            'name' => 'Vendor B 50-Inch Smart TV',
            'slug' => 'vendor-b-50-inch-smart-tv',
            'unit_price' => '320000.00',
            'condition' => 'brand_new',
            'package_tier' => 'keke',
            'inventory_quantity' => 4,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $service = new FulfillmentAvailabilityService();

        // Evaluate Cart containing items from BOTH Vendor A and Vendor B
        $cartItems = [
            ['product_id' => $productA1->id, 'quantity' => 1],
            ['product_id' => $productA2->id, 'quantity' => 1],
            ['product_id' => $productB_Bike->id, 'quantity' => 1],
            ['product_id' => $productB_Keke->id, 'quantity' => 1],
        ];

        $availability = $service->checkCartFulfillmentAvailability($this->uyoLga->id, $cartItems);

        $this->assertTrue($availability['available']);
        $deliveryOptions = $availability['fulfillment_options']['delivery'];
        $this->assertTrue($deliveryOptions['available']);

        // Collect lanes by shop_id
        $vendorLanes = collect($deliveryOptions['vendor_lanes'])->keyBy('shop_id');
        $this->assertCount(2, $vendorLanes);

        // Verification 1: Vendor A has only bike items -> Standard baseline delivery fee (₦500.00)
        $laneA = $vendorLanes->get($this->shop->id);
        $this->assertNotNull($laneA);
        $this->assertEquals('500.00', $laneA['delivery_fee'], 'Vendor A with only bike items must have baseline delivery fee without bulky surcharge');

        // Verification 2: Vendor B has a keke item -> Bulky cargo surcharge applied (₦500.00 + ₦1000.00 = ₦1,500.00)
        $laneB = $vendorLanes->get($shopB->id);
        $this->assertNotNull($laneB);
        $this->assertEquals('1500.00', $laneB['delivery_fee'], 'Vendor B with a keke item must have bulk cargo surcharge applied to its child order');

        // Verification 3: Total cart shipping fee is the exact sum of both vendor child delivery fees (₦500 + ₦1500 = ₦2000)
        $this->assertEquals('2000.00', $deliveryOptions['total_shipping_fee']);
    }

    /**
     * Deep Verification:
     * A SINGLE vendor can own multiple shop branches in DIFFERENT locations (e.g. Uyo and Eket).
     * Even though it is the same merchant/vendor, each shop branch is evaluated independently:
     * - Branch A (Uyo -> Uyo, Intra-LGA, Bike) = ₦500.00
     * - Branch B (Eket -> Uyo, Inter-LGA, Keke Bulky) = ₦1,500 + ₦1,000 = ₦2,500.00
     */
    public function test_same_vendor_with_multiple_shops_in_different_locations_charges_different_delivery_fees(): void
    {
        $fleetUser = User::create([
            'name' => 'Fleet Operator',
            'email' => 'fleet@speedlogistics.ng',
            'phone' => '+2348033334444',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);
        $fleet = \App\Models\LogisticsCompanyProfile::create([
            'user_id' => $fleetUser->id,
            'company_name' => 'Speed Logistics Hub Ltd',
            'registration_number' => 'RC-112233',
            'status' => 'active',
        ]);

        // Active collection point in destination LGA (Uyo) for inter-LGA route
        \App\Models\LogisticsCollectionPoint::create([
            'logistics_company_id' => $fleet->id,
            'state_id' => $this->shop->state_id,
            'lga_id' => $this->uyoLga->id,
            'code' => 'UYO-HUB-01',
            'name' => 'Uyo Main Transit Hub',
            'contact_person' => 'Station Manager',
            'contact_phone' => '08033334444',
            'address' => '50 Aka Road, Uyo',
            'is_active' => true,
        ]);

        SystemSetting::set('zone_inter_lga_fee', '1500.00');

        // SAME Vendor ($this->vendor) opens a SECOND shop in a DIFFERENT LGA (Eket)
        $shopEket = Shop::create([
            'vendor_id' => $this->vendor->id, // SAME VENDOR!
            'name' => 'Ibom Tech & Gadgets - Eket Branch',
            'slug' => 'ibom-tech-gadgets-eket-branch',
            'address' => '15 Marina Road, Eket',
            'country_id' => $this->shop->country_id,
            'state_id' => $this->shop->state_id,
            'lga_id' => $this->eketLga->id, // DIFFERENT LGA!
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        // Product at Uyo Branch (Shop 1): Standard Bike Item
        $productUyoBranch = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id, // Uyo Branch
            'category_id' => $this->category->id,
            'name' => 'USB-C Fast Charging Cable',
            'slug' => 'usbc-fast-charging-cable',
            'unit_price' => '3000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 20,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        // Product at Eket Branch (Shop 2): Heavy Keke Item
        $productEketBranch = Product::create([
            'vendor_id' => $this->vendor->id, // SAME VENDOR
            'shop_id' => $shopEket->id,       // Eket Branch
            'category_id' => $this->category->id,
            'name' => 'Inverter Battery 200Ah',
            'slug' => 'inverter-battery-200ah',
            'unit_price' => '250000.00',
            'condition' => 'brand_new',
            'package_tier' => 'keke',         // Heavy cargo!
            'inventory_quantity' => 3,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $service = new FulfillmentAvailabilityService();

        // Customer in Uyo orders from BOTH branches of the SAME vendor
        $cartItems = [
            ['product_id' => $productUyoBranch->id, 'quantity' => 1],
            ['product_id' => $productEketBranch->id, 'quantity' => 1],
        ];

        $availability = $service->checkCartFulfillmentAvailability($this->uyoLga->id, $cartItems);

        $this->assertTrue($availability['available']);
        $deliveryOptions = $availability['fulfillment_options']['delivery'];
        $vendorLanes = collect($deliveryOptions['vendor_lanes'])->keyBy('shop_id');

        // Both shops from the same vendor are present
        $this->assertCount(2, $vendorLanes);

        // Branch 1 (Uyo -> Uyo, Intra-LGA, Bike): Base ₦500.00, 0 Surcharge
        $laneUyo = $vendorLanes->get($this->shop->id);
        $this->assertNotNull($laneUyo);
        $this->assertEquals($this->vendor->id, $laneUyo['seller_id']);
        $this->assertEquals('500.00', $laneUyo['delivery_fee']);

        // Branch 2 (Eket -> Uyo, Inter-LGA, Keke): Base ₦1,500.00 + ₦1,000.00 Keke Surcharge = ₦2,500.00
        $laneEket = $vendorLanes->get($shopEket->id);
        $this->assertNotNull($laneEket);
        $this->assertEquals($this->vendor->id, $laneEket['seller_id']); // Same vendor!
        $this->assertEquals('2500.00', $laneEket['delivery_fee']);

        // Total delivery fee for this multi-branch cart: ₦500 + ₦2,500 = ₦3,000.00
        $this->assertEquals('3000.00', $deliveryOptions['total_shipping_fee']);
    }

    public function test_vendor_portal_creates_product_with_multi_photo_gallery(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $file1 = \Illuminate\Http\UploadedFile::fake()->image('cover.jpg', 600, 600);
        $file2 = \Illuminate\Http\UploadedFile::fake()->image('side_view.jpg', 600, 600);
        $file3 = \Illuminate\Http\UploadedFile::fake()->image('box_seal.jpg', 600, 600);

        $response = $this->actingAs($this->vendorUser)->post('/vendor/products', [
            'name' => 'Sony PlayStation 5 Disc Edition',
            'category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'condition' => 'brand_new',
            'package_tier' => 'keke',
            'warranty_type' => '7_days_replacement',
            'unit_price' => 580000.00,
            'slash_price' => 650000.00,
            'inventory_quantity' => 4,
            'description' => 'Brand new sealed PS5 console with FIFA 25 and dual controllers.',
            'images' => [$file1, $file2, $file3],
        ]);

        $response->assertRedirect();

        $product = Product::where('name', 'Sony PlayStation 5 Disc Edition')->first();
        $this->assertNotNull($product);
        $this->assertNotNull($product->thumbnail);
        $this->assertStringStartsWith('/storage/products/', $product->thumbnail);
        $this->assertIsArray($product->images);
        $this->assertCount(3, $product->images);
        $this->assertEquals('580000.00', $product->unit_price);
        $this->assertEquals('650000.00', $product->slash_price);
    }

    public function test_vendor_portal_rejects_price_slash_when_slash_price_is_less_than_unit_price(): void
    {
        $response = $this->actingAs($this->vendorUser)->from('/vendor/products')->post('/vendor/products', [
            'name' => 'Invalid Discount Item',
            'category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'unit_price' => 60000.00,
            'slash_price' => 45000.00, // Invalid: slash price lower than selling price
            'inventory_quantity' => 1,
        ]);

        $response->assertRedirect('/vendor/products');
        $response->assertSessionHasErrors(['slash_price']);

        $product = Product::where('name', 'Invalid Discount Item')->first();
        $this->assertNull($product);
    }

    public function test_vendor_portal_accepts_valid_price_slash_and_calculates_discount(): void
    {
        $response = $this->actingAs($this->vendorUser)->post('/vendor/products', [
            'name' => 'iPhone 14 Pro Max 256GB',
            'category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'condition' => 'uk_used',
            'package_tier' => 'bike',
            'warranty_type' => 'in_store_testing',
            'unit_price' => 800000.00,
            'slash_price' => 1000000.00, // 20% discount
            'inventory_quantity' => 3,
            'description' => 'Factory unlocked 95% battery health with receipt and warranty.',
        ]);

        $response->assertRedirect();

        $product = Product::where('name', 'iPhone 14 Pro Max 256GB')->first();
        $this->assertNotNull($product);
        $this->assertEquals('800000.00', $product->unit_price);
        $this->assertEquals('1000000.00', $product->slash_price);
        $this->assertEquals('20', $product->discount_percentage);
    }

    public function test_vendor_portal_strictly_enforces_compulsory_photo(): void
    {
        $response = $this->actingAs($this->vendorUser)
            ->withHeaders(['X-Require-Photo' => 'true'])
            ->from('/vendor/products')
            ->post('/vendor/products', [
                'name' => 'Camera Lens Without Photo',
                'category_id' => $this->category->id,
                'shop_id' => $this->shop->id,
                'condition' => 'brand_new',
                'package_tier' => 'bike',
                'unit_price' => 120000.00,
                'inventory_quantity' => 2,
            ]);

        $response->assertRedirect('/vendor/products');
        $response->assertSessionHasErrors(['images', 'image']);

        $product = Product::where('name', 'Camera Lens Without Photo')->first();
        $this->assertNull($product);
    }
}
