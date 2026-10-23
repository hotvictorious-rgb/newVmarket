<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\AdminAuditLog;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\DeliveryLane;
use App\Models\Lga;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSupplierAssignment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\SystemSetting;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\FulfillmentAvailabilityService;
use App\Services\SourcingAllocationService;
use App\Services\SupplierOfferSelectionService;
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
            'kyc_status' => 'verified',
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

        DeliveryLane::create([
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'base_fee' => '500.00',
            'bulky_surcharge' => '1000.00',
            'logistics_company_base_payable' => '450.00',
            'logistics_company_bulky_surcharge' => '800.00',
            'estimated_delivery_hours_min' => 2,
            'estimated_delivery_hours_max' => 6,
            'is_active' => true,
        ]);
    }

    public function test_supplier_recovery_journal_idempotency_keys_are_order_scoped_and_bounded(): void
    {
        $service = app(\App\Services\SupplierReassignmentRecoveryService::class);
        $method = new \ReflectionMethod($service, 'journalIdempotencyKey');
        $method->setAccessible(true);
        $callerKey = str_repeat('same-caller-key-', 12);

        $firstOrderKey = $method->invoke($service, 101, $callerKey);
        $sameOrderRetryKey = $method->invoke($service, 101, $callerKey);
        $secondOrderKey = $method->invoke($service, 202, $callerKey);

        $this->assertSame($firstOrderKey, $sameOrderRetryKey);
        $this->assertNotSame($firstOrderKey, $secondOrderKey);
        $this->assertLessThanOrEqual(100, strlen($firstOrderKey));
        $this->assertLessThanOrEqual(100, strlen($secondOrderKey));
    }

    public function test_vendor_terminal_denies_non_active_supplier_accounts(): void
    {
        Sanctum::actingAs($this->vendorUser, ['*']);

        $this->vendor->update(['marketplace_status' => 'probation']);

        $this->getJson('/api/v1/vendor/orders')
            ->assertForbidden();

        $this->vendor->update(['marketplace_status' => 'active']);
        $this->getJson('/api/v1/vendor/orders')->assertOk();

        $this->vendor->update(['marketplace_status' => 'suspended']);
        $this->getJson('/api/v1/vendor/orders')->assertForbidden();
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

    public function test_multi_item_order_adds_bulky_surcharge_for_each_bulky_unit(): void
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

        // Product listings are retailer-facing catalogue records; private sourcing is
        // resolved independently for each item. The bulky product is supplied by a
        // different Uyo shop than the bike product.
        $supplierUserB = User::create([
            'name' => 'Uyo Supplier B',
            'email' => 'supplier-b@test.com',
            'phone' => '+2348099887767',
            'password' => bcrypt('SupplierPass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $supplierB = VendorProfile::create([
            'user_id' => $supplierUserB->id,
            'business_name' => 'Uyo Supplier B Ltd',
            'contact_phone' => '+2348099887767',
            'marketplace_status' => 'active',
            'kyc_status' => 'verified',
            'commission_rate' => 10.00,
            'bank_name' => 'Access Bank',
            'account_number' => '0223456789',
            'account_name' => 'UYO SUPPLIER B',
        ]);
        $shopB = Shop::create([
            'vendor_id' => $supplierB->id,
            'name' => 'Supplier B Uyo Shop',
            'slug' => 'supplier-b-uyo-shop',
            'address' => 'Aka Road, Uyo',
            'country_id' => $this->shop->country_id,
            'state_id' => $this->shop->state_id,
            'lga_id' => $this->uyoLga->id,
            'is_active' => true,
            'pickup_enabled' => false,
        ]);
        $secondSupplierProduct = Product::create([
            'vendor_id' => $supplierB->id,
            'shop_id' => $shopB->id,
            'category_id' => $this->category->id,
            'name' => 'Supplier B Bluetooth Speaker',
            'slug' => 'supplier-b-bluetooth-speaker',
            'unit_price' => '12000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 8,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        foreach ([
            [$bikeProduct, $this->vendor, $this->shop, '1000.00', $this->vendorUser],
            [$kekeProduct, $supplierB, $shopB, '20000.00', $supplierUserB],
            [$secondSupplierProduct, $supplierB, $shopB, '3000.00', $supplierUserB],
        ] as [$product, $supplier, $supplierShop, $cost, $confirmer]) {
            SupplierOffer::create([
                'product_id' => $product->id,
                'vendor_id' => $supplier->id,
                'shop_id' => $supplierShop->id,
                'wholesale_cost' => $cost,
                'is_available' => true,
                'availability_confirmed_at' => Carbon::now(),
                'availability_confirmed_by' => $confirmer->id,
                'availability_expires_at' => Carbon::now()->addDay(),
                'notes' => 'Test-confirmed Uyo sourcing offer',
            ]);
        }

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
        $this->assertEquals('500.00', $orderBike->delivery_base_fee_snapshot);
        $this->assertEquals('1000.00', $orderBike->delivery_bulky_surcharge_per_unit_snapshot);
        $this->assertEquals(0, $orderBike->delivery_bulky_quantity_snapshot);

        // Case B: One bike unit plus one bulky unit -> one base fee + one bulky surcharge (1500.00)
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
        $this->assertEquals('500.00', $orderKeke->delivery_base_fee_snapshot);
        $this->assertEquals('1000.00', $orderKeke->delivery_bulky_surcharge_per_unit_snapshot);
        $this->assertEquals(1, $orderKeke->delivery_bulky_quantity_snapshot);

        // Case C: One bike unit plus two physical bulky units -> base once + surcharge twice (2500.00).
        $orderResTwoBulky = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $bikeProduct->id, 'quantity' => 1],
                ['product_id' => $kekeProduct->id, 'quantity' => 2],
                ['product_id' => $secondSupplierProduct->id, 'quantity' => 1],
            ],
        ]);

        $orderResTwoBulky->assertStatus(201)
            ->assertJsonPath('data.order.shop.name', 'Victorious MARKET')
            ->assertJsonMissingPath('data.order.shop.address');
        $orderTwoBulky = Order::where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($orderTwoBulky);
        $this->assertEquals('2500.00', $orderTwoBulky->delivery_fee);
        $this->assertEquals('500.00', $orderTwoBulky->delivery_base_fee_snapshot);
        $this->assertEquals('1000.00', $orderTwoBulky->delivery_bulky_surcharge_per_unit_snapshot);
        $this->assertEquals(2, $orderTwoBulky->delivery_bulky_quantity_snapshot);

        // Before verified payment, the order contains customer-facing lines only:
        // supplier identities/costs and assignment rows must not be bound yet.
        $prePaymentItems = OrderItem::where('order_id', $orderTwoBulky->id)->orderBy('id')->get();
        $this->assertCount(3, $prePaymentItems);
        $this->assertTrue($prePaymentItems->every(fn ($item) =>
            $item->supplier_vendor_id === null
            && $item->supplier_shop_id === null
            && $item->supplier_cost_snapshot === null
        ));
        $this->assertSame(0, OrderSupplierAssignment::where('order_id', $orderTwoBulky->id)->count());
        $this->assertNull($orderTwoBulky->supplier_cost_total_snapshot);
        $this->assertNull($orderTwoBulky->sourced_at);

        // Authoritative payment settlement now selects eligible Uyo suppliers,
        // records immutable supplier/cost snapshots, and creates assignment groups.
        $settlement = app(\App\Services\OrderSettlementService::class)->settlePaystackOrder($orderTwoBulky, [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => (int) bcmul((string) $orderTwoBulky->grand_total, '100', 0),
            'reference' => (string) $orderTwoBulky->payment_reference,
            'id' => 'VM-TEST-TX-' . $orderTwoBulky->id,
            'channel' => 'bank',
            'customer' => ['email' => $customer->email],
        ]);
        $this->assertTrue($settlement['success'] ?? false, $settlement['message'] ?? 'Verified payment settlement failed.');
        $orderTwoBulky->refresh();

        $orderItems = OrderItem::where('order_id', $orderTwoBulky->id)->orderBy('id')->get();
        $this->assertCount(3, $orderItems);
        $this->assertEquals($this->vendor->id, $orderItems[0]->supplier_vendor_id);
        $this->assertEquals($supplierB->id, $orderItems[1]->supplier_vendor_id);
        $this->assertEquals($supplierB->id, $orderItems[2]->supplier_vendor_id);
        $this->assertEquals($this->shop->id, $orderItems[0]->supplier_shop_id);
        $this->assertEquals($shopB->id, $orderItems[1]->supplier_shop_id);
        $this->assertEquals($shopB->id, $orderItems[2]->supplier_shop_id);
        $this->assertNotNull($orderItems[0]->supplier_cost_snapshot);
        $this->assertNotNull($orderItems[1]->supplier_cost_snapshot);
        $this->assertNotNull($orderItems[2]->supplier_cost_snapshot);
        $this->assertNull($orderTwoBulky->selected_supplier_id);
        $this->assertNull($orderTwoBulky->selected_shop_id);
        $this->assertSame('paid', $orderTwoBulky->payment_status);
        $this->assertSame('awaiting_supplier_confirmation', $orderTwoBulky->sourcing_status);

        $assignments = OrderSupplierAssignment::where('order_id', $orderTwoBulky->id)->orderBy('supplier_vendor_id')->get();
        $this->assertCount(2, $assignments);
        $this->assertSame(['awaiting_supplier_confirmation', 'awaiting_supplier_confirmation'], $assignments->pluck('status')->all());

        // Each supplier can confirm only its own assignment; the order unlocks
        // only after both assigned supplier groups respond.
        Sanctum::actingAs($supplierUserB, ['*']);
        $responseB = $this->postJson("/api/v1/vendor/orders/{$orderTwoBulky->id}/confirm-availability", [
            'notes' => 'Confirmed the fan stock.',
        ]);
        $responseB->assertOk();
        $this->assertEquals('partially_supplier_confirmed', $orderTwoBulky->fresh()->sourcing_status);

        Sanctum::actingAs($this->vendorUser, ['*']);
        $responseA = $this->postJson("/api/v1/vendor/orders/{$orderTwoBulky->id}/confirm-availability", [
            'notes' => 'Confirmed the screen protector stock.',
        ]);
        $responseA->assertOk();
        $this->assertEquals('supplier_confirmed', $orderTwoBulky->fresh()->sourcing_status);

        // Each supplier independently marks its own package ready and receives a
        // separate collection OTP. The whole order becomes ready only after both.
        Sanctum::actingAs($supplierUserB, ['*']);
        $readyB = $this->postJson("/api/v1/vendor/orders/{$orderTwoBulky->id}/ready-for-handover");
        $readyB->assertOk()->assertJsonPath('all_suppliers_ready', false);
        $otpB = $readyB->json('pickup_otp');
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', (string) $otpB);
        $this->assertEquals('confirmed', $orderTwoBulky->fresh()->order_status);

        Sanctum::actingAs($this->vendorUser, ['*']);
        $readyA = $this->postJson("/api/v1/vendor/orders/{$orderTwoBulky->id}/ready-for-handover");
        $readyA->assertOk()->assertJsonPath('all_suppliers_ready', true);
        $otpA = $readyA->json('pickup_otp');
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', (string) $otpA);
        $this->assertNotSame($otpA, $otpB);
        $this->assertEquals('ready_for_pickup', $orderTwoBulky->fresh()->order_status);

        // Supplier-facing order data is limited to its assigned item group and
        // excludes customer identity, address IDs, and central logistics contacts.
        Sanctum::actingAs($supplierUserB, ['*']);
        $supplierOrderResponse = $this->getJson('/api/v1/vendor/orders');
        $supplierOrderResponse->assertOk();
        $supplierOrder = collect($supplierOrderResponse->json('data'))
            ->firstWhere('id', $orderTwoBulky->id);
        $this->assertNotNull($supplierOrder);
        $this->assertArrayNotHasKey('customer_id', $supplierOrder);
        $this->assertArrayNotHasKey('customer_address_id', $supplierOrder);
        $this->assertArrayNotHasKey('rider_id', $supplierOrder);
        $this->assertArrayNotHasKey('logistics_company_id', $supplierOrder);
        $this->assertCount(2, $supplierOrder['items']);
        $this->assertNotContains(
            $bikeProduct->id,
            collect($supplierOrder['items'])->pluck('product_id')->all(),
            'A supplier must not receive another supplier\'s item lines.'
        );

        // A supplier can report a stock failure after confirming and preparing
        // its package, as long as that package has not yet been collected.
        Sanctum::actingAs($supplierUserB, ['*']);
        $rejectPreparedPackage = $this->postJson("/api/v1/vendor/orders/{$orderTwoBulky->id}/reject-availability", [
            'reason' => 'Final stock check found the item was damaged.',
        ]);
        $rejectPreparedPackage->assertOk()
            ->assertJsonPath('sourcing_status', 'supplier_rejected');
        $rejectedPreparedAssignment = OrderSupplierAssignment::where('order_id', $orderTwoBulky->id)
            ->where('supplier_vendor_id', $supplierB->id)
            ->firstOrFail();
        $this->assertEquals('supplier_rejected', $rejectedPreparedAssignment->status);
        $this->assertNull($rejectedPreparedAssignment->supplier_pickup_code);
        $this->assertNull($rejectedPreparedAssignment->handover_ready_at);
        $this->assertEquals('confirmed', $orderTwoBulky->fresh()->order_status);
        $this->assertEquals('paid', $orderTwoBulky->fresh()->payment_status);

        // Recovery changes only operational supplier assignment snapshots. The
        // capture-time supplier identity/cost and original journal remain immutable.
        $recoveryAdmin = User::create([
            'name' => 'Supplier Recovery Admin',
            'email' => 'supplier-recovery-admin@example.test',
            'password' => bcrypt('SecurePass123!'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);
        $recoverySupplierUser = User::create([
            'name' => 'Replacement Supplier',
            'email' => 'replacement-supplier@example.test',
            'phone' => '+2348011223399',
            'password' => bcrypt('SecurePass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $recoverySupplier = VendorProfile::create([
            'user_id' => $recoverySupplierUser->id,
            'business_name' => 'Replacement Supplier Ltd',
            'marketplace_status' => 'active',
            'kyc_status' => 'verified',
            'bank_name' => 'Access Bank',
            'account_number' => '0123456799',
            'account_name' => 'REPLACEMENT SUPPLIER LTD',
        ]);
        $recoveryShop = Shop::create([
            'vendor_id' => $recoverySupplier->id,
            'name' => 'Replacement Supplier Uyo Shop',
            'slug' => 'replacement-supplier-uyo-shop',
            'address' => 'Aka Road, Uyo',
            'country_id' => $this->shop->country_id,
            'state_id' => $this->shop->state_id,
            'lga_id' => $this->uyoLga->id,
            'is_active' => true,
            'pickup_enabled' => false,
        ]);
        $replacementOffers = [];
        foreach ([[$secondSupplierProduct, '2500.00'], [$kekeProduct, '25000.00']] as [$recoveryProduct, $replacementCost]) {
            $replacementOffers[$recoveryProduct->id] = SupplierOffer::create([
                'product_id' => $recoveryProduct->id,
                'vendor_id' => $recoverySupplier->id,
                'shop_id' => $recoveryShop->id,
                'wholesale_cost' => $replacementCost,
                'is_available' => true,
                'availability_confirmed_at' => Carbon::now(),
                'availability_confirmed_by' => $recoveryAdmin->id,
                'availability_expires_at' => Carbon::now()->addDay(),
                'notes' => 'Fresh admin-confirmed replacement offer',
            ]);
        }

        $rejectedAssignment = OrderSupplierAssignment::where('order_id', $orderTwoBulky->id)
            ->where('supplier_vendor_id', $supplierB->id)
            ->firstOrFail();
        $rejectedItems = OrderItem::where('order_id', $orderTwoBulky->id)
            ->where('fulfillment_supplier_vendor_id', $supplierB->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $rejectedItems);
        $captureSupplierIds = $rejectedItems->pluck('supplier_vendor_id')->all();
        $captureCostTotal = $orderTwoBulky->fresh()->supplier_cost_total_snapshot;
        $customerTotalBeforeRecovery = $orderTwoBulky->fresh()->grand_total;
        $cashbackBeforeRecovery = $orderTwoBulky->fresh()->customer_cashback_5;

        // Verified settlement above already posted the supplier payable and
        // escrow journals. Do not seed a second synthetic capture; duplicate
        // payable credits would correctly fail the recovery reconciliation guard.

        $recoveryService = app(\App\Services\SupplierReassignmentRecoveryService::class);
        $journalCountBeforeStaleOffer = \App\Models\FinancialJournalEntry::where('order_id', $orderTwoBulky->id)->count();
        $replacementOffers[$kekeProduct->id]->availability_expires_at = Carbon::now()->subMinute();
        $replacementOffers[$kekeProduct->id]->save();
        try {
            $recoveryService->reassignRejectedItems(
                $orderTwoBulky->id,
                $rejectedAssignment->id,
                $recoverySupplier->id,
                $recoveryShop->id,
                $rejectedItems->pluck('id')->all(),
                $recoveryAdmin,
                'Reject stale offer recovery attempt.',
                'supplier-recovery-stale-001'
            );
            $this->fail('Expired replacement availability evidence must block recovery.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('fresh confirmed offer', $exception->getMessage());
        }
        $this->assertSame($journalCountBeforeStaleOffer, \App\Models\FinancialJournalEntry::where('order_id', $orderTwoBulky->id)->count());
        $this->assertEquals('supplier_rejected', $orderTwoBulky->fresh()->sourcing_status);
        $replacementOffers[$kekeProduct->id]->availability_expires_at = Carbon::now()->addDay();
        $replacementOffers[$kekeProduct->id]->save();

        $firstItem = $rejectedItems->first();
        $secondItem = $rejectedItems->last();
        $firstRecoveryArgs = [
            $orderTwoBulky->id,
            $rejectedAssignment->id,
            $recoverySupplier->id,
            $recoveryShop->id,
            [$firstItem->id],
            $recoveryAdmin,
            'Rejected supplier could not fulfil the accepted order.',
            'supplier-recovery-test-001',
        ];
        $firstRecovery = $recoveryService->reassignRejectedItems(...$firstRecoveryArgs);
        $this->assertTrue($firstRecovery['success']);
        $this->assertFalse($firstRecovery['replayed']);
        $this->assertEquals('supplier_rejected', $rejectedAssignment->fresh()->status);
        $this->assertEquals('supplier_rejected', $orderTwoBulky->fresh()->sourcing_status);

        $journalCountAfterFirstRecovery = \App\Models\FinancialJournalEntry::where('order_id', $orderTwoBulky->id)->count();
        $replay = $recoveryService->reassignRejectedItems(...$firstRecoveryArgs);
        $this->assertTrue($replay['replayed']);
        $this->assertSame($journalCountAfterFirstRecovery, \App\Models\FinancialJournalEntry::where('order_id', $orderTwoBulky->id)->count());

        $conflictingArgs = $firstRecoveryArgs;
        $conflictingArgs[6] = 'Different instruction with the same idempotency key.';
        try {
            $recoveryService->reassignRejectedItems(...$conflictingArgs);
            $this->fail('Reusing a recovery idempotency key with different instructions must fail.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('different supplier recovery instruction', $exception->getMessage());
        }

        $secondRecovery = $recoveryService->reassignRejectedItems(
            $orderTwoBulky->id,
            $rejectedAssignment->id,
            $recoverySupplier->id,
            $recoveryShop->id,
            [$secondItem->id],
            $recoveryAdmin,
            'Recover the remaining rejected supplier item.',
            'supplier-recovery-test-002'
        );
        $this->assertTrue($secondRecovery['success']);
        $this->assertFalse($secondRecovery['replayed']);
        $this->assertEquals('admin_reassigned', $rejectedAssignment->fresh()->status);
        $this->assertEquals('partially_supplier_confirmed', $orderTwoBulky->fresh()->sourcing_status);
        $this->assertEquals($captureCostTotal, $orderTwoBulky->fresh()->supplier_cost_total_snapshot);
        $this->assertEquals($customerTotalBeforeRecovery, $orderTwoBulky->fresh()->grand_total);
        $this->assertEquals($cashbackBeforeRecovery, $orderTwoBulky->fresh()->customer_cashback_5);
        $this->assertEquals($captureSupplierIds, $rejectedItems->fresh()->pluck('supplier_vendor_id')->all());
        $this->assertEquals(
            [$recoverySupplier->id, $recoverySupplier->id],
            OrderItem::whereIn('id', $rejectedItems->pluck('id'))->orderBy('id')->pluck('fulfillment_supplier_vendor_id')->all()
        );
        $this->assertTrue(\App\Models\FinancialJournalEntry::assertOrderBalanced($orderTwoBulky->id));

        Sanctum::actingAs($recoverySupplierUser, ['*']);
        $replacementConfirmation = $this->postJson("/api/v1/vendor/orders/{$orderTwoBulky->id}/confirm-availability", [
            'notes' => 'Replacement items confirmed in stock.',
        ]);
        $replacementConfirmation->assertOk();
        $this->assertEquals('supplier_confirmed', $orderTwoBulky->fresh()->sourcing_status);
        $replacementReady = $this->postJson("/api/v1/vendor/orders/{$orderTwoBulky->id}/ready-for-handover");
        $replacementReady->assertOk()->assertJsonPath('all_suppliers_ready', true);
        $this->assertEquals('ready_for_pickup', $orderTwoBulky->fresh()->order_status);

        // A replacement can also fail its final stock check before collection.
        // The second rejection must reinstate the order-wide hold and invalidate
        // the replacement package OTP rather than dispatching stale stock.
        Sanctum::actingAs($recoverySupplierUser, ['*']);
        $secondRejection = $this->postJson("/api/v1/vendor/orders/{$orderTwoBulky->id}/reject-availability", [
            'reason' => 'Replacement stock failed final condition inspection.',
        ]);
        $secondRejection->assertOk()->assertJsonPath('sourcing_status', 'supplier_rejected');
        $replacementAssignment = OrderSupplierAssignment::where('order_id', $orderTwoBulky->id)
            ->where('supplier_vendor_id', $recoverySupplier->id)
            ->firstOrFail();
        $this->assertEquals('supplier_rejected', $replacementAssignment->status);
        $this->assertNull($replacementAssignment->supplier_pickup_code);
        $this->assertEquals('confirmed', $orderTwoBulky->fresh()->order_status);
        $this->assertEquals('paid', $orderTwoBulky->fresh()->payment_status);

        // The recurring finance audit must detect a mismatch without attempting
        // to repair or release money, then return clean after the evidence is restored.
        $supplierAudit = app(\App\Services\FinancialReconciliationEngine::class)
            ->reconcileRetailerSupplierPayables($orderTwoBulky->id);
        $this->assertSame(1, $supplierAudit['orders_checked']);
        $this->assertSame(0, $supplierAudit['mismatches'], json_encode($supplierAudit['errors'], JSON_UNESCAPED_SLASHES));

        $journalCountBeforeAudit = \App\Models\FinancialJournalEntry::where('order_id', $orderTwoBulky->id)->count();
        $auditedItem = OrderItem::where('order_id', $orderTwoBulky->id)->firstOrFail();
        $originalOperationalCost = (string) $auditedItem->fulfillment_supplier_cost_snapshot;
        $auditedItem->fulfillment_supplier_cost_snapshot = \App\Services\BcMathService::add($originalOperationalCost, '1.00');
        $auditedItem->save();
        $mismatchAudit = app(\App\Services\FinancialReconciliationEngine::class)
            ->reconcileRetailerSupplierPayables($orderTwoBulky->id);
        $this->assertSame(1, $mismatchAudit['mismatches']);
        $this->assertNotEmpty($mismatchAudit['errors']);
        $this->assertSame($journalCountBeforeAudit, \App\Models\FinancialJournalEntry::where('order_id', $orderTwoBulky->id)->count());

        $auditedItem->fulfillment_supplier_cost_snapshot = $originalOperationalCost;
        $auditedItem->save();
        $cleanAudit = app(\App\Services\FinancialReconciliationEngine::class)
            ->reconcileRetailerSupplierPayables($orderTwoBulky->id);
        $this->assertSame(0, $cleanAudit['mismatches']);

        // Exercise the real lifecycle: supplier assignment must be created by
        // authoritative verified settlement, not by manually fabricating assignment
        // rows or flipping the order to paid inside a test.
        $bikeSettlement = app(\\App\\Services\\OrderSettlementService::class)->settlePaystackOrder($orderBike, [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => (int) bcmul((string) $orderBike->grand_total, '100', 0),
            'reference' => (string) $orderBike->payment_reference,
            'id' => 'VM-TEST-BIKE-TX-' . $orderBike->id,
            'channel' => 'bank',
            'customer' => ['email' => $customer->email],
        ]);
        $this->assertTrue($bikeSettlement['success'] ?? false, $bikeSettlement['message'] ?? 'Verified bike-order settlement failed.');
        $orderBike->refresh();
        $this->assertSame('paid', $orderBike->payment_status);
        $this->assertGreaterThan(0, OrderSupplierAssignment::where('order_id', $orderBike->id)->count());
        $this->assertSame('awaiting_supplier_confirmation', $orderBike->sourcing_status);
        $bikeAssignment = OrderSupplierAssignment::where('order_id', $orderBike->id)->firstOrFail();
        $this->assertSame(
            (int) $this->vendor->id,
            (int) $bikeAssignment->supplier_vendor_id,
            'The single-bike order should be allocated to its only eligible supplier.'
        );
        $this->assertSame((int) $this->shop->id, (int) $bikeAssignment->supplier_shop_id);
        Sanctum::actingAs($this->vendorUser, ['*']);
        $reject = $this->postJson("/api/v1/vendor/orders/{$orderBike->id}/reject-availability", [
            'reason' => 'The listed item is no longer physically available.',
        ]);
        $reject->assertOk($reject->getContent())
            ->assertJsonPath('status', true)
            ->assertJsonPath('sourcing_status', 'supplier_rejected');
        $this->assertEquals('paid', $orderBike->fresh()->payment_status);
        $this->assertEquals('confirmed', $orderBike->fresh()->order_status);
        $this->assertEquals('supplier_rejected', $orderBike->fresh()->sourcing_status);
        $this->assertEquals('supplier_rejected', OrderSupplierAssignment::where('order_id', $orderBike->id)->firstOrFail()->status);
        $this->assertDatabaseHas('admin_audit_logs', [
            'target_type' => Order::class,
            'target_id' => $orderBike->id,
            'action' => 'supplier_rejected_paid_retailer_order',
        ]);

        // The order-wide hold must survive later supplier actions. Neither another
        // confirmation nor collection preparation may reopen this rejected order.
        $lateConfirmation = $this->postJson("/api/v1/vendor/orders/{$orderBike->id}/confirm-availability", [
            'notes' => 'Late confirmation must not clear the administrative hold.',
        ]);
        $lateConfirmation->assertStatus(409)
            ->assertJsonPath('status', false);
        $blockedPreparation = $this->postJson("/api/v1/vendor/orders/{$orderBike->id}/ready-for-handover");
        $blockedPreparation->assertStatus(422)
            ->assertJsonPath('status', false);
        $this->assertEquals('supplier_rejected', $orderBike->fresh()->sourcing_status);
        $this->assertEquals('paid', $orderBike->fresh()->payment_status);

        // Exercise the service-level custody boundary directly, not only the API.
        // The hold must win before rider eligibility or OTP processing can mutate state.
        $orderBike->forceFill([
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'confirmed',
        ])->save();
        $pickupBlocked = app(\App\Services\OrderSettlementService::class)->verifyRiderPickupOtp(
            $orderBike->fresh(),
            '123456',
            new \App\Models\RiderProfile()
        );
        $this->assertFalse($pickupBlocked['success']);
        $this->assertStringContainsString('supplier-rejection hold', $pickupBlocked['message']);
        $this->assertEquals('confirmed', $orderBike->fresh()->order_status);
        $this->assertEquals('supplier_rejected', $orderBike->fresh()->sourcing_status);

        // The legacy order-level helper must not bypass the retailer assignment workflow.
        $blocked = false;
        try {
            app('App\\Services\SourcingAllocationService')->confirmSupplierAvailability($orderBike->fresh(), (int) $this->vendor->id, 'late legacy confirmation');
        } catch (\InvalidArgumentException $exception) {
            $blocked = str_contains($exception->getMessage(), 'supplier-assignment confirmation workflow');
        }
        $this->assertTrue($blocked, 'Legacy order-level supplier confirmation must reject retailer orders.');
        $this->assertEquals('supplier_rejected', $orderBike->fresh()->sourcing_status);
        $this->assertEquals('paid', $orderBike->fresh()->payment_status);

        // Even a manually/stale-marked eligible status must not let a retailer
        // order use the legacy single-vendor payout path or create payout journals.
        $orderBike->forceFill([
            'vendor_settlement_status' => 'eligible',
            'order_status' => 'delivered',
        ])->save();
        // A retailer order's primary vendor_id is only a compatibility anchor,
        // not a legacy vendor earning. It must not inflate supplier withdrawable funds.
        $this->assertEquals('0.00', $this->vendor->fresh()->getWithdrawableBalance());
        $orderBike->forceFill(['order_status' => 'confirmed'])->save();
        $adminUser = User::create([
            'name' => 'Finance Super Admin',
            'email' => 'finance-super-admin@victoriousmarket.ng',
            'phone' => '+2348099990011',
            'password' => bcrypt('SecurePass123!'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);
        $journalCountBefore = \App\Models\FinancialJournalEntry::where('order_id', $orderBike->id)->count();
        $payout = app(\App\Services\OrderSettlementService::class)
            ->disburseVendorSettlement($orderBike->fresh(), $adminUser, 'proofs/legacy-payout-must-not-exist.jpg');
        $this->assertFalse($payout['success']);
        $this->assertStringContainsString('cannot use the legacy order-level vendor payout', $payout['message']);
        $this->assertEquals('eligible', $orderBike->fresh()->vendor_settlement_status);
        $this->assertSame($journalCountBefore, \App\Models\FinancialJournalEntry::where('order_id', $orderBike->id)->count());
    }

    public function test_public_product_api_filters_by_condition_and_package_tier(): void
    {
        $productNew = Product::create([
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

        $productUsed = Product::create([
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

        foreach ([$productNew, $productUsed] as $prod) {
            SupplierOffer::create([
                'product_id' => $prod->id,
                'vendor_id' => $this->vendor->id,
                'shop_id' => $this->shop->id,
                'wholesale_cost' => bcmul((string) $prod->unit_price, '0.85', 2),
                'is_available' => true,
                'availability_confirmed_at' => Carbon::now(),
                'availability_confirmed_by' => $this->vendorUser->id,
                'availability_expires_at' => Carbon::now()->addDays(7),
                'notes' => 'Storefront verified test offer',
            ]);
        }

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
     * Cart availability uses one order-level base route fee plus a bulky surcharge for every
     * physical unit that does not fit on a bike. Supplier groups are checked for route
     * eligibility, but their base fees are not added together.
     */
    public function test_cart_fulfillment_charges_one_base_fee_plus_each_bulky_unit_across_suppliers(): void
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
            'kyc_status' => 'verified',
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

        foreach ([
            [$productA1, $this->vendor, $this->shop, '3000.00', $this->vendorUser],
            [$productA2, $this->vendor, $this->shop, '4000.00', $this->vendorUser],
            [$productB_Bike, $vendorB, $shopB, '7000.00', $vendorUserB],
            [$productB_Keke, $vendorB, $shopB, '200000.00', $vendorUserB],
        ] as [$product, $supplier, $supplierShop, $cost, $confirmer]) {
            SupplierOffer::create([
                'product_id' => $product->id,
                'vendor_id' => $supplier->id,
                'shop_id' => $supplierShop->id,
                'wholesale_cost' => $cost,
                'is_available' => true,
                'availability_confirmed_at' => Carbon::now(),
                'availability_confirmed_by' => $confirmer->id,
                'availability_expires_at' => Carbon::now()->addDay(),
                'notes' => 'Test-confirmed supplier offer',
            ]);
        }

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

        // Customer-facing cart availability must not expose supplier/shop identities.
        $this->assertArrayNotHasKey('vendor_lanes', $deliveryOptions);

        // Total is one base fee plus one bulky surcharge, not one fee per supplier.
        $this->assertEquals('500.00', $deliveryOptions['base_route_fee']);
        $this->assertEquals(1, $deliveryOptions['bulky_quantity']);
        $this->assertEquals('1000.00', $deliveryOptions['bulky_surcharge_total']);
        $this->assertEquals('1500.00', $deliveryOptions['total_shipping_fee']);
    }

    /**
     * Deep Verification:
     * A SINGLE vendor can own multiple shop branches in DIFFERENT locations (e.g. Uyo and Eket).
     * Even though it is the same merchant/vendor, each shop branch is evaluated independently:
     * - Branch A (Uyo -> Uyo, Intra-LGA, Bike) = ₦500.00
     * - Branch B (Eket -> Uyo, Inter-LGA, Keke Bulky) = ₦1,500 + ₦1,000 = ₦2,500.00
     */
    public function test_cart_rejects_non_uyo_supplier_origin_even_when_other_source_is_uyo(): void
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

        \App\Models\ShopDeliveryCoverage::firstOrCreate(
            ['shop_id' => $shopEket->id, 'state_id' => $this->shop->state_id, 'lga_id' => $this->uyoLga->id],
            ['is_active' => true]
        );

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

        foreach ([
            [$productUyoBranch, $this->shop, '1500.00'],
            [$productEketBranch, $shopEket, '180000.00'],
        ] as [$product, $supplierShop, $cost]) {
            SupplierOffer::create([
                'product_id' => $product->id,
                'vendor_id' => $this->vendor->id,
                'shop_id' => $supplierShop->id,
                'wholesale_cost' => $cost,
                'is_available' => true,
                'availability_confirmed_at' => Carbon::now(),
                'availability_confirmed_by' => $this->vendorUser->id,
                'availability_expires_at' => Carbon::now()->addDay(),
                'notes' => 'Test offer origin guard',
            ]);
        }

        $service = new FulfillmentAvailabilityService();

        // Customer in Uyo orders from BOTH branches of the SAME vendor
        $cartItems = [
            ['product_id' => $productUyoBranch->id, 'quantity' => 1],
            ['product_id' => $productEketBranch->id, 'quantity' => 1],
        ];

        $availability = $service->checkCartFulfillmentAvailability($this->uyoLga->id, $cartItems);

        $this->assertFalse($availability['fulfillment_options']['delivery']['available']);
        $deliveryOptions = $availability['fulfillment_options']['delivery'];

        // The Eket supplier offer is ineligible; fail the complete cart closed and
        // expose no supplier names, shop IDs, or address details to the customer.
        $this->assertArrayNotHasKey('vendor_lanes', $deliveryOptions);
        $this->assertEquals('0.00', $deliveryOptions['total_shipping_fee']);
    }


    public function test_legacy_public_pickup_reservation_endpoints_are_disabled(): void
    {
        $this->getJson('/api/v1/pickup-reservations/RES-LEGACY-01')
            ->assertStatus(410)
            ->assertJsonPath('code', 'SUPPORT_MANAGED_PICKUP_ONLY')
            ->assertJsonMissingPath('data.shop');

        $this->post('/reservation/create', [
            'product_id' => 1,
            'shop_id' => $this->shop->id,
            'quantity' => 1,
        ])->assertRedirect(route('storefront'));

        $this->assertDatabaseCount('pickup_reservations', 0);
    }

    public function test_public_shop_directory_is_retired_to_protect_supplier_identity(): void
    {
        $this->getJson('/api/v1/shops')
            ->assertStatus(410)
            ->assertJsonPath('data', [])
            ->assertJsonMissingPath('data.0.address')
            ->assertJsonMissingPath('data.0.name');
    }

    public function test_public_product_api_does_not_expose_supplier_shop_or_vendor_ids(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Public Privacy Test Product',
            'slug' => 'public-privacy-test-product',
            'unit_price' => '10000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);
        SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'wholesale_cost' => '5000.00',
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now(),
            'availability_confirmed_by' => $this->vendorUser->id,
            'availability_expires_at' => Carbon::now()->addDay(),
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonMissingPath('data.data.0.vendor_id')
            ->assertJsonMissingPath('data.data.0.shop_id');

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.vendor_id')
            ->assertJsonMissingPath('data.shop_id');
    }

    public function test_admin_cannot_onboard_supplier_outside_uyo(): void
    {
        $admin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin-uyo-scope@example.com',
            'phone' => '+2348099000011',
            'password' => bcrypt('SecurePass123!'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post('/admin/merchants', [
                'business_name' => 'Out of Zone Supplier',
                'contact_name' => 'Supplier Contact',
                'phone' => '+2348099000012',
                'email' => 'outside-supplier@example.com',
                'password' => 'SecurePass123!',
                'shop_name' => 'Eket Shop',
                'shop_address' => 'Eket',
                'lga_id' => $this->eketLga->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('lga_id');

        $this->assertDatabaseMissing('vendor_profiles', ['business_name' => 'Out of Zone Supplier']);
    }

    public function test_supplier_cannot_register_a_second_shop_or_pickup_point(): void
    {
        $this->actingAs($this->vendorUser)
            ->post('/vendor/shops', [
                'name' => 'Second Plaza Pickup',
                'address' => 'Another Uyo address',
                'lga_id' => $this->uyoLga->id,
                'phone' => '+2348011223344',
                'pickup_enabled' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(1, Shop::where('vendor_id', $this->vendor->id)->whereNull('deleted_at')->count());
    }

    public function test_supplier_cannot_move_shop_outside_uyo(): void
    {
        $this->actingAs($this->vendorUser)
            ->post("/vendor/shops/{$this->shop->id}/update", [
                'name' => $this->shop->name,
                'address' => $this->shop->address,
                'lga_id' => $this->eketLga->id,
                'phone' => '+2348011223344',
                'pickup_enabled' => '1',
            ])
            ->assertRedirect();

        $this->assertSame($this->uyoLga->id, $this->shop->fresh()->lga_id);
    }

    public function test_supplier_cannot_configure_delivery_coverage(): void
    {
        $this->actingAs($this->vendorUser)
            ->post("/vendor/shops/{$this->shop->id}/coverage", [
                'lga_ids' => [$this->eketLga->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('shop_delivery_coverages', [
            'shop_id' => $this->shop->id,
            'lga_id' => $this->eketLga->id,
        ]);
    }

    public function test_delivery_rejects_outside_uyo_even_if_legacy_lane_is_active(): void
    {
        DeliveryLane::create([
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->eketLga->id,
            'base_fee' => '1500.00',
            'bulky_surcharge' => '1000.00',
            'estimated_delivery_hours_min' => 2,
            'estimated_delivery_hours_max' => 6,
            'is_active' => true,
        ]);

        $result = (new FulfillmentAvailabilityService())->checkDeliveryAvailability(
            $this->shop->id,
            $this->eketLga->id
        );

        $this->assertFalse($result['is_available']);
        $this->assertSame('0.00', $result['fee']);
        $this->assertSame('outside_uyo_launch_boundary', $result['rate_tier']);
        $this->assertSame([], $result['collection_points']);
    }

    public function test_public_catalog_excludes_unverified_supplier_offers(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Unverified Supplier Catalog Guard',
            'slug' => 'unverified-supplier-catalog-guard',
            'unit_price' => '10000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'wholesale_cost' => '5000.00',
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now(),
            'availability_confirmed_by' => $this->vendorUser->id,
            'availability_expires_at' => Carbon::now()->addDay(),
        ]);

        $this->vendor->update(['kyc_status' => 'submitted']);

        $this->assertNull(Product::marketplaceEligible()->find($product->id));
        $this->assertNull(
            app(SupplierOfferSelectionService::class)->selectLowestCost($product->fresh(), 'doorstep_delivery')
        );
    }

    public function test_supplier_offer_with_future_confirmation_timestamp_is_not_selectable(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Future Availability Timestamp Guard',
            'slug' => 'future-availability-timestamp-guard',
            'unit_price' => '10000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'wholesale_cost' => '7000.00',
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now()->addHour(),
            'availability_confirmed_by' => $this->vendorUser->id,
            'availability_expires_at' => Carbon::now()->addDay(),
            'notes' => 'Future confirmation timestamp must fail closed',
        ]);

        $selected = app(SupplierOfferSelectionService::class)
            ->selectLowestCost($product, 'doorstep_delivery');

        $this->assertNull($selected);
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

    public function test_supplier_offer_without_confirming_actor_is_not_selectable(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Availability Evidence Guard Product',
            'slug' => 'availability-evidence-guard-product',
            'unit_price' => '10000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'wholesale_cost' => '7000.00',
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now(),
            'availability_confirmed_by' => null,
            'availability_expires_at' => Carbon::now()->addDay(),
            'notes' => 'Missing confirming actor must fail closed',
        ]);

        $selected = app(SupplierOfferSelectionService::class)
            ->selectLowestCost($product, 'doorstep_delivery');

        $this->assertNull($selected);
    }

    public function test_catalog_owner_is_not_a_supplier_without_an_explicit_confirmed_offer(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'No Supplier Offer Must Fail Closed',
            'slug' => 'no-supplier-offer-must-fail-closed',
            'unit_price' => '10000.00',
            'cost_price' => '7000.00',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $resolved = app(SourcingAllocationService::class)
            ->resolveCheapestOffer($product, 1, $this->uyoLga->id, 'doorstep_delivery');

        $this->assertNull($resolved);
    }

}
