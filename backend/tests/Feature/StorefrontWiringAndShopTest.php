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

    public function test_dedicated_shop_storefront_loads_with_profile_and_catalog()
    {
        $shop = Shop::where('pickup_enabled', true)->first();
        $this->assertNotNull($shop, 'A shop should exist from seeder');

        $response = $this->get('/shop/' . $shop->slug);

        $response->assertStatus(200);
        $response->assertSee($shop->name);
        $response->assertSee($shop->address);
        $response->assertSee('ACCREDITED MERCHANT');
        $response->assertSee('Store Operating Hours');

        // Strict Platform Anti-Disintermediation Rules: Never expose vendor direct phone or WhatsApp
        if (!empty($shop->phone)) {
            $response->assertDontSee($shop->phone);
        }
        $response->assertDontSee('Call Store');
        $response->assertDontSee('WhatsApp Merchant');

        // Shop's products should appear in the catalog
        $shopProduct = Product::where('shop_id', $shop->id)->first();
        if ($shopProduct) {
            $response->assertSee($shopProduct->name);
        }
    }

    public function test_shop_storefront_supports_in_store_search_and_category_filtering()
    {
        $shop = Shop::where('pickup_enabled', true)->first();
        $product = Product::where('shop_id', $shop->id)->first();

        if ($product) {
            // Search filter
            $searchResponse = $this->get('/shop/' . $shop->slug . '?search=' . urlencode(substr($product->name, 0, 5)));
            $searchResponse->assertStatus(200);
            $searchResponse->assertSee($product->name);

            // Category filter
            if ($product->category) {
                $catResponse = $this->get('/shop/' . $shop->slug . '?category=' . $product->category->slug);
                $catResponse->assertStatus(200);
                $catResponse->assertSee($product->name);
            }
        }
    }

    public function test_shop_storefront_returns_404_for_unknown_slug()
    {
        $response = $this->get('/shop/nonexistent-store-slug-xyz');
        $response->assertStatus(404);
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
        // When user visits /checkout without params, it shouldn't 404 or 500
        $response = $this->get('/checkout');

        // Either renders checkout with eligible product or redirects to storefront with message
        $this->assertTrue(in_array($response->status(), [200, 302]));
        if ($response->status() === 200) {
            $response->assertSee('Order Summary');
        }
    }
}
