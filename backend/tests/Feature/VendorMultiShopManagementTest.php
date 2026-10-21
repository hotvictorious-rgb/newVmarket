<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Lga;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDeliveryCoverage;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorMultiShopManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected State $state;
    protected Lga $uyoLga;
    protected Lga $eketLga;
    protected Lga $ikotEkpeneLga;
    protected Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->country = Country::first() ?? Country::create(['name' => 'Nigeria', 'code' => 'NG', 'phonecode' => '234']);
        $this->state = State::where('name', 'Akwa Ibom')->first() ?? State::create(['country_id' => $this->country->id, 'name' => 'Akwa Ibom', 'code' => 'AK']);
        $this->uyoLga = Lga::where('name', 'Uyo')->first() ?? Lga::create(['state_id' => $this->state->id, 'name' => 'Uyo', 'code' => 'UYO', 'is_active' => true]);
        $this->eketLga = Lga::where('name', 'Eket')->first() ?? Lga::create(['state_id' => $this->state->id, 'name' => 'Eket', 'code' => 'EKT', 'is_active' => true]);
        $this->ikotEkpeneLga = Lga::where('name', 'Ikot Ekpene')->first() ?? Lga::create(['state_id' => $this->state->id, 'name' => 'Ikot Ekpene', 'code' => 'IKP', 'is_active' => true]);

        $this->vendorUser = User::factory()->create([
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Apex Mega Superstores',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
        ]);
    }

    /**
     * TEST: Every shop must be strictly tied to an LGA.
     */
    public function test_every_shop_must_be_tied_to_a_valid_lga(): void
    {
        $this->actingAs($this->vendorUser);

        // Attempting to create a shop without an LGA fails validation
        $response = $this->post(route('vendor.shops.store'), [
            'name' => 'Invalid Shop No LGA',
            'address' => 'Somewhere in town',
            'phone' => '08011112222',
            // lga_id omitted
        ]);

        $response->assertSessionHasErrors(['lga_id']);
        $this->assertDatabaseMissing('shops', ['name' => 'Invalid Shop No LGA']);

        // Successfully creating a shop requires a valid LGA
        $validResponse = $this->post(route('vendor.shops.store'), [
            'name' => 'Apex Ibom Plaza Branch',
            'address' => '14 Ibom Plaza, Uyo',
            'phone' => '08011112222',
            'lga_id' => $this->uyoLga->id,
            'pickup_enabled' => '1',
        ]);

        $validResponse->assertRedirect();
        $this->assertDatabaseHas('shops', [
            'vendor_id' => $this->vendor->id,
            'name' => 'Apex Ibom Plaza Branch',
            'lga_id' => $this->uyoLga->id,
        ]);
    }

    /**
     * TEST: Vendor can own 2 or more shops and manage them independently.
     */
    public function test_vendor_can_have_multiple_shops_with_independent_coverage_and_switching(): void
    {
        $this->actingAs($this->vendorUser);

        // 1. Create Shop A in Uyo LGA
        $shopA = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Apex Ibom Plaza',
            'slug' => 'apex-ibom-plaza',
            'address' => '10 Plaza Way, Uyo',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->uyoLga->id,
            'is_primary_branch' => true,
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        // 2. Create Shop B in Eket LGA
        $shopB = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Apex Eket Center',
            'slug' => 'apex-eket-center',
            'address' => '5 Marina Road, Eket',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->eketLga->id,
            'is_primary_branch' => false,
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $this->assertEquals(2, $this->vendor->shops()->count());
        $this->assertEquals($this->uyoLga->id, $shopA->lga_id);
        $this->assertEquals($this->eketLga->id, $shopB->lga_id);

        // 3. Independent coverage: Shop A covers Uyo; Shop B covers Eket
        ShopDeliveryCoverage::firstOrCreate(
            ['shop_id' => $shopA->id, 'state_id' => $this->state->id, 'lga_id' => $this->uyoLga->id],
            ['is_active' => true]
        );
        ShopDeliveryCoverage::firstOrCreate(
            ['shop_id' => $shopB->id, 'state_id' => $this->state->id, 'lga_id' => $this->eketLga->id],
            ['is_active' => true]
        );

        $this->assertTrue($shopA->coversDestination($this->uyoLga->id, $this->state->id));
        $this->assertFalse($shopA->coversDestination($this->eketLga->id, $this->state->id));

        $this->assertTrue($shopB->coversDestination($this->eketLga->id, $this->state->id));
        $this->assertFalse($shopB->coversDestination($this->uyoLga->id, $this->state->id));

        // 4. Products attached to specific shops
        $category = Category::first() ?? Category::create(['name' => 'Gadgets', 'slug' => 'gadgets', 'is_active' => true]);

        $productA = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $shopA->id,
            'category_id' => $category->id,
            'name' => 'Phone Case Uyo',
            'slug' => 'phone-case-uyo',
            'unit_price' => '2500.00',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
        ]);

        $productB = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $shopB->id,
            'category_id' => $category->id,
            'name' => 'Phone Case Eket',
            'slug' => 'phone-case-eket',
            'unit_price' => '3000.00',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
        ]);

        // 5. Test Switching on Products Page
        // Consolidated (no filter) -> Shows both products
        $resAll = $this->get(route('vendor.products.index'));
        $resAll->assertOk();
        $resAll->assertSee('Phone Case Uyo');
        $resAll->assertSee('Phone Case Eket');

        // Filtered to Shop A -> Shows Phone Case Uyo only
        $resShopA = $this->get(route('vendor.products.index', ['shop_id' => $shopA->id]));
        $resShopA->assertOk();
        $resShopA->assertSee('Phone Case Uyo');
        $resShopA->assertDontSee('Phone Case Eket');

        // Filtered to Shop B -> Shows Phone Case Eket only
        $resShopB = $this->get(route('vendor.products.index', ['shop_id' => $shopB->id]));
        $resShopB->assertOk();
        $resShopB->assertSee('Phone Case Eket');
        $resShopB->assertDontSee('Phone Case Uyo');

        // 6. Test Switching on Orders Page
        $customer = User::factory()->create(['user_type' => 'customer']);

        $orderA = Order::create([
            'order_number' => 'VM-ORD-SHP-A',
            'customer_id' => $customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $shopA->id,
            'origin_lga_id' => $shopA->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'subtotal' => '2500.00',
            'merchandise_subtotal' => '2500.00',
            'vendor_net_90' => '2250.00',
            'customer_cashback_5' => '125.00',
            'platform_margin_5' => '125.00',
            'delivery_fee' => '500.00',
            'grand_total' => '3000.00',
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $orderB = Order::create([
            'order_number' => 'VM-ORD-SHP-B',
            'customer_id' => $customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $shopB->id,
            'origin_lga_id' => $shopB->lga_id,
            'destination_lga_id' => $this->eketLga->id,
            'subtotal' => '3000.00',
            'merchandise_subtotal' => '3000.00',
            'vendor_net_90' => '2700.00',
            'customer_cashback_5' => '150.00',
            'platform_margin_5' => '150.00',
            'delivery_fee' => '600.00',
            'grand_total' => '3600.00',
            'order_status' => 'processing',
            'payment_status' => 'paid',
        ]);

        // Filtered orders to Shop A
        $orderResA = $this->get(route('vendor.orders.index', ['shop_id' => $shopA->id]));
        $orderResA->assertOk();
        $orderResA->assertSee('VM-ORD-SHP-A');
        $orderResA->assertDontSee('VM-ORD-SHP-B');

        // Filtered orders to Shop B
        $orderResB = $this->get(route('vendor.orders.index', ['shop_id' => $shopB->id]));
        $orderResB->assertOk();
        $orderResB->assertSee('VM-ORD-SHP-B');
        $orderResB->assertDontSee('VM-ORD-SHP-A');
    }
}
