<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Lga;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontWiringAndShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_storefront_renders_with_category_and_merchant_links()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        // Verify category filters are wired to query parameters instead of dead anchors
        $response->assertSee('category=home-appliances');
        $response->assertSee('category=electronics-gadgets');
        $response->assertSee('category=groceries-farm-produce');

        // Verify search queries are wired on discovery tiles
        $response->assertSee('search=Blender');
        $response->assertSee('search=Phone');

        // Verify verified merchant cards link to dedicated storefronts
        $response->assertSee('/shop/');
    }

    public function test_dedicated_shop_storefront_redirects_to_central_storefront()
    {
        $shop = Shop::where('pickup_enabled', true)->first();
        $this->assertNotNull($shop, 'A shop should exist from seeder');

        $response = $this->get('/shop/' . $shop->slug);

        // Supplier shop pages expose private sourcing relationships and are retired
        // under the single-retailer Victorious MARKET model.
        $response->assertStatus(302);
        $response->assertRedirect(route('storefront'));
        $response->assertSessionHas('info');
    }

    public function test_shop_storefront_redirects_for_any_slug()
    {
        $response = $this->get('/shop/nonexistent-store-slug-xyz');
        $response->assertStatus(302);
        $response->assertRedirect(route('storefront'));
    }

    public function test_product_detail_page_links_to_vendor_storefront()
    {
        $product = Product::with('shop')->first();
        $this->assertNotNull($product, 'A product should exist');

        $response = $this->get('/product/' . $product->slug);

        $response->assertStatus(200);
        $response->assertSee($product->name);
        $response->assertSee('/shop/' . $product->shop->slug);
        $response->assertSee('ESCROW SECURED');
    }

    public function test_checkout_handles_missing_product_id_gracefully()
    {
        // When authenticated customer visits /checkout without params, it shouldn't 404 or 500
        $customer = User::where('user_type', 'customer')->firstOrFail();
        $response = $this->actingAs($customer)->get('/checkout');

        // Either renders checkout with eligible product or redirects to storefront with message
        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertSee('Order Summary');
        }
    }

    public function test_unauthenticated_user_checkout_redirects_to_login_with_return_url()
    {
        $response = $this->get('/checkout');
        $response->assertStatus(302);
        $response->assertRedirect();
        $this->assertTrue(str_contains($response->headers->get('Location'), '/customer/login') || str_contains($response->headers->get('Location'), '/login'));
        $response->assertSessionHas('info');
    }

    public function test_unauthenticated_user_cannot_process_checkout_without_account()
    {
        $product = Product::firstOrFail();
        $response = $this->post(route('checkout.process'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'fulfillment_type' => 'in_shop_pickup',
            'customer_name' => 'Guest Visitor',
            'customer_phone' => '08012345678',
        ]);

        $response->assertStatus(302);
        $this->assertTrue(str_contains($response->headers->get('Location'), '/customer/login') || str_contains($response->headers->get('Location'), '/login'));
        $response->assertSessionHas('error');
    }

    public function test_in_shop_reservation_creation_is_disabled_under_retailer_model()
    {
        $product = Product::firstOrFail();
        $response = $this->post(route('reservation.create'), [
            'product_id' => $product->id,
            'shop_id' => $product->shop_id,
            'quantity' => 1,
            'customer_name' => 'Guest Visitor',
            'customer_phone' => '08012345678',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('storefront'));
        $response->assertSessionHas('error');
    }

    public function test_authenticated_customer_can_access_checkout_successfully()
    {
        $customer = User::where('user_type', 'customer')->firstOrFail();
        $product = Product::firstOrFail();

        $response = $this->actingAs($customer)->get('/checkout?product_id=' . $product->id);
        $response->assertStatus(200);
        $response->assertSee('Order Summary');
        $response->assertSee($product->name);
        $response->assertSee($customer->name);
    }

    public function test_storefront_contains_auth_checkout_modal_and_grid_ids()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('id="vmAuthCheckoutModal"', false);
        $response->assertSee('id="vmShopsGridContainer"', false);
        $response->assertSee('Customer Account Required');
        $response->assertSee('vmOpenAuthModal');
    }

    public function test_storefront_delivering_to_destination_central_fulfilment()
    {
        // When customer selects delivery destination Eket, storefront confirms delivery destination and Uyo hub dispatch
        $response = $this->get('/?city=Eket');
        $response->assertStatus(200);
        $response->assertSee('Delivering to:');
        $response->assertSee('Eket');
        $response->assertSee('from Uyo Hub');
    }

    public function test_storefront_supports_condition_filtering()
    {
        // Test condition filter parameters
        $response = $this->get('/?condition=brand_new');
        $response->assertStatus(200);
        $response->assertSee('Brand New');

        $responseUk = $this->get('/?condition=uk_used');
        $responseUk->assertStatus(200);
        $responseUk->assertSee('UK Used');
    }

    public function test_storefront_ajax_location_switch_returns_expected_json_and_scope()
    {
        $response = $this->getJson('/?ajax=1&city=Eket&state=Akwa+Ibom');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'city',
            'state',
            'lga_id',
            'coverage_status',
            'status_badge',
            'preview_status',
            'products_count',
            'local_products_count',
            'corridor_products_count',
            'scope',
            'products_html',
            'shops_html',
        ]);
        $response->assertJson([
            'success' => true,
            'city' => 'Eket',
        ]);
    }

    public function test_set_location_prioritizes_city_name_over_mismatched_stale_lga_id()
    {
        // When client sends city "Eket" with old/stale lga_id 69 (Uyo), it must resolve to Eket
        $eketLga = Lga::where('name', 'Eket')->firstOrFail();
        $response = $this->postJson(route('set.location'), [
            'city' => 'Eket',
            'state' => 'Akwa Ibom',
            'lga_id' => 69, // Deliberately passing Uyo's ID to test resilient resolution
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'city' => 'Eket',
            'lga_id' => $eketLga->id,
        ]);

        $this->assertEquals('Eket', session('customer_city'));
        $this->assertEquals($eketLga->id, session('customer_lga_id'));
    }
}
