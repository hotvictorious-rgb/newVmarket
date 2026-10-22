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

    public function test_storefront_renders_central_retailer_catalog(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('category=home-appliances');
        $response->assertSee('category=electronics-gadgets');
        $response->assertSee('category=groceries-farm-produce');
        $response->assertSee('search=Blender');
        $response->assertSee('search=Phone');
        $response->assertSee('Trusted shopping, delivered within Uyo');
        $response->assertDontSee('href="/shop/');
    }

    public function test_supplier_shop_storefront_redirects_to_central_retailer(): void
    {
        $shop = Shop::where('pickup_enabled', true)->first();
        $this->assertNotNull($shop, 'A shop should exist from seeder');

        $response = $this->get('/shop/' . $shop->slug);
        $response->assertRedirect(route('storefront'));
        $response->assertSessionHas('info');
        $response->assertDontSee($shop->address);
    }

    public function test_supplier_shop_search_and_category_pages_are_not_public(): void
    {
        $shop = Shop::where('pickup_enabled', true)->first();
        $this->assertNotNull($shop);

        $response = $this->get('/shop/' . $shop->slug . '?search=phone&category=electronics');
        $response->assertRedirect(route('storefront'));
        $response->assertSessionHas('info');
    }

    public function test_unknown_supplier_shop_slug_redirects_to_central_retailer(): void
    {
        $response = $this->get('/shop/nonexistent-store-slug-xyz');
        $response->assertRedirect(route('storefront'));
    }

    public function test_product_detail_does_not_link_to_supplier_storefront(): void
    {
        $uyo = Lga::where('name', 'Uyo')->firstOrFail();
        $product = Product::marketplaceEligible()
            ->whereHas('supplierOffers.shop.lga', function ($query) use ($uyo) {
                $query->whereKey($uyo->id);
            })
            ->first();
        $this->assertNotNull($product, 'A product with a Uyo sourcing offer should exist');

        $response = $this->get('/product/' . $product->slug);
        $response->assertStatus(200);
        $response->assertSee($product->name);
        $response->assertDontSee('/shop/');
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

    public function test_unauthenticated_user_cannot_create_in_shop_reservation_without_account()
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
        $this->assertTrue(str_contains($response->headers->get('Location'), '/customer/login') || str_contains($response->headers->get('Location'), '/login'));
        $response->assertSessionHas('info');
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

    public function test_storefront_never_switches_to_out_of_uyo_catalog(): void
    {
        $response = $this->get('/?city=Eket');
        $response->assertStatus(200);
        $response->assertSee('Uyo Local Delivery');
        $response->assertSee('Uyo');
        $response->assertDontSee('Stores Closer to You in Eket');
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

    public function test_storefront_ajax_location_switch_keeps_uyo_boundary(): void
    {
        $uyo = Lga::where('name', 'Uyo')->firstOrFail();
        $response = $this->getJson('/?ajax=1&city=Eket&state=Akwa+Ibom');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success', 'city', 'state', 'lga_id', 'coverage_status',
            'status_badge', 'preview_status', 'products_count',
            'local_products_count', 'corridor_products_count', 'scope',
            'products_html', 'shops_html',
        ]);
        $response->assertJson([
            'success' => true,
            'city' => 'Uyo',
            'lga_id' => $uyo->id,
        ]);
        $this->assertStringNotContainsString('supplier_shop_name', (string) $response->getContent());
    }

    public function test_set_location_rejects_out_of_uyo_city_even_with_stale_lga_id(): void
    {
        Lga::where('name', 'Eket')->firstOrFail();
        $response = $this->postJson(route('set.location'), [
            'city' => 'Eket',
            'state' => 'Akwa Ibom',
            'lga_id' => 69,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'OUTSIDE_UYO_LAUNCH_BOUNDARY');
    }

    public function test_checkout_template_never_renders_supplier_shop_names(): void
    {
        $template = file_get_contents(resource_path('views/storefront/checkout.blade.php'));

        $this->assertIsString($template);
        $this->assertStringNotContainsString('${item.shop_name}', $template,
            'Supplier shop names must remain private in the customer-facing checkout.');
        $this->assertStringContainsString('<span>Victorious MARKET</span>', $template);
    }

}
