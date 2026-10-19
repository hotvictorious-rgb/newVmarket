<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Lga;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\CheckoutExecutionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductFreshnessAndStorefrontIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected Shop $shop;
    protected Category $category;
    protected User $customerUser;
    protected State $state;
    protected Lga $lga;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NGA',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'currency_symbol' => '?',
            'is_active' => true,
        ]);

        $this->state = State::create([
            'country_id' => $country->id,
            'name' => 'Akwa Ibom',
            'state_code' => 'AK',
            'is_active' => true,
        ]);

        $this->lga = Lga::create([
            'state_id' => $this->state->id,
            'name' => 'Uyo',
            'is_active' => true,
        ]);

        $this->vendorUser = User::create([
            'name' => 'Etim Super Stores',
            'email' => 'etim@vendor.ng',
            'password' => bcrypt('Secret123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Etim Super Stores Ltd',
            'business_phone' => '08033334444',
            'marketplace_status' => 'active',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'country_id' => $country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'name' => 'Etim Uyo Mega Plaza',
            'slug' => 'etim-uyo-mega-plaza',
            'address' => '15 Ikot Ekpene Road, Uyo',
            'phone' => '08033334444',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Smartphones & Tech',
            'slug' => 'smartphones-tech',
            'is_active' => true,
            'priority' => 1,
        ]);

        $this->customerUser = User::create([
            'name' => 'Blessing Customer',
            'email' => 'blessing@customer.ng',
            'password' => bcrypt('Secret123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);
    }

    /**
     * Test 1: Confirmed 6 days, 23 hours ago -> Eligible & Fresh
     */
    public function test_product_confirmed_6_days_23_hours_ago_is_eligible(): void
    {
        $confirmedAt = Carbon::now()->subDays(6)->subHours(23);

        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Samsung Galaxy S24 Ultra',
            'slug' => 'samsung-galaxy-s24-ultra',
            'unit_price' => '1200000.00',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => $confirmedAt,
        ]);

        $this->assertTrue($product->isFresh(), 'Product confirmed 6d 23h ago must be fresh.');
        $this->assertTrue($product->is_available_for_sale);
        $this->assertCount(1, Product::marketplaceEligible()->where('id', $product->id)->get());
    }

    /**
     * Test 2: Exactly 7 days old -> Expired
     */
    public function test_product_exactly_7_days_old_is_expired(): void
    {
        $confirmedAt = Carbon::now()->subDays(7);

        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'iPhone 15 Pro Max',
            'slug' => 'iphone-15-pro-max',
            'unit_price' => '1400000.00',
            'inventory_quantity' => 3,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => $confirmedAt,
        ]);

        $this->assertFalse($product->isFresh(), 'Product confirmed exactly 7 days ago must be expired.');
        $this->assertFalse($product->is_available_for_sale);
        $this->assertCount(0, Product::marketplaceEligible()->where('id', $product->id)->get());
    }

    /**
     * Test 3: Older than 7 days -> Hidden and purchase rejected
     */
    public function test_product_older_than_7_days_is_hidden_and_ineligible(): void
    {
        $confirmedAt = Carbon::now()->subDays(8);

        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Google Pixel 8 Pro',
            'slug' => 'google-pixel-8-pro',
            'unit_price' => '850000.00',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => $confirmedAt,
        ]);

        $this->assertFalse($product->isFresh());
        $this->assertCount(0, Product::marketplaceEligible()->where('id', $product->id)->get());
    }

    /**
     * Test 4: Missing confirmation timestamp -> Hidden and purchase rejected
     */
    public function test_product_missing_confirmation_timestamp_is_ineligible(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Xiaomi 14 Ultra',
            'slug' => 'xiaomi-14-ultra',
            'unit_price' => '700000.00',
            'inventory_quantity' => 8,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => null,
        ]);

        $this->assertFalse($product->isFresh());
        $this->assertCount(0, Product::marketplaceEligible()->where('id', $product->id)->get());
    }

    /**
     * Test 5: Future confirmation timestamp -> Rejected as invalid
     */
    public function test_product_with_future_confirmation_timestamp_is_invalid(): void
    {
        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'OnePlus 12',
            'slug' => 'oneplus-12',
            'unit_price' => '650000.00',
            'inventory_quantity' => 4,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->addHours(2), // Future!
        ]);

        $this->assertFalse($product->isFresh(), 'Future confirmation timestamp must evaluate to not fresh.');
        $this->assertCount(0, Product::marketplaceEligible()->where('id', $product->id)->get());
    }

    /**
     * Test 6: Open an expired product's old URL -> 404 Not Found
     */
    public function test_product_detail_page_rejects_expired_product_with_404(): void
    {
        $expiredProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'MacBook Pro M3 Max',
            'slug' => 'macbook-pro-m3-max',
            'unit_price' => '3200000.00',
            'inventory_quantity' => 2,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subDays(10), // Expired!
        ]);

        $response = $this->get(route('product.detail', ['slug' => $expiredProduct->slug]));
        $response->assertStatus(404);
    }

    /**
     * Test 7: Submit web checkout for expired product -> Rejected
     */
    public function test_web_checkout_submission_rejects_expired_product(): void
    {
        $expiredProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Sony PlayStation 5 Pro',
            'slug' => 'sony-ps5-pro',
            'unit_price' => '950000.00',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subDays(9), // Expired!
        ]);

        $response = $this->actingAs($this->customerUser)->post(route('checkout.process'), [
            'product_id' => $expiredProduct->id,
            'quantity' => 1,
            'fulfillment_type' => 'in_shop_pickup',
            'customer_name' => 'Blessing Customer',
            'customer_phone' => '08011223344',
            'shop_id' => $this->shop->id,
        ]);

        $response->assertSessionHasErrors('product_id');
        $this->assertEquals(0, Order::where('customer_id', $this->customerUser->id)->count());
    }

    /**
     * Test 8: Submit API checkout for expired product -> Rejected
     */
    public function test_api_checkout_rejects_expired_product(): void
    {
        $expiredProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Asus ROG Ally X',
            'slug' => 'asus-rog-ally-x',
            'unit_price' => '800000.00',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subDays(12),
        ]);

        $service = app(CheckoutExecutionService::class);
        $payload = [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'in_shop_pickup',
            'items' => [
                ['product_id' => $expiredProduct->id, 'quantity' => 1],
            ],
        ];

        $result = $service->execute($this->customerUser, $payload);
        $this->assertFalse($result['success']);
        $this->assertEquals(422, $result['status_code'] ?? 422);
        $this->assertStringContainsString('expired', strtolower($result['message']));
    }

    /**
     * Test 9: Create pickup reservation for expired product -> Rejected
     */
    public function test_pickup_reservation_rejects_expired_product(): void
    {
        $expiredProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'DJI Mini 4 Pro Drone',
            'slug' => 'dji-mini-4-pro',
            'unit_price' => '1100000.00',
            'inventory_quantity' => 2,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subDays(14),
        ]);

        Sanctum::actingAs($this->customerUser);
        $response = $this->postJson('/api/v1/customer/pickup-reservations', [
            'shop_id' => $this->shop->id,
            'product_id' => $expiredProduct->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $this->assertStringContainsString('expired', strtolower($response->json('message')));
    }

    /**
     * Test 10: Vendor reconfirms expired product -> Eligible again
     */
    public function test_vendor_reconfirmation_restores_expired_product(): void
    {
        $expiredProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Bose QuietComfort Ultra',
            'slug' => 'bose-quietcomfort-ultra',
            'unit_price' => '450000.00',
            'inventory_quantity' => 6,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'unlisted', // Expired and marked unlisted
            'listing_confirmed_at' => Carbon::now()->subDays(10),
        ]);

        $this->assertFalse($expiredProduct->isFresh());
        $this->assertCount(0, Product::marketplaceEligible()->where('id', $expiredProduct->id)->get());

        // Vendor reconfirms freshness via web portal route
        $response = $this->actingAs($this->vendorUser)->post(
            route('vendor.products.refresh', ['id' => $expiredProduct->id])
        );

        $response->assertRedirect();
        $expiredProduct->refresh();

        $this->assertTrue($expiredProduct->isFresh(), 'Reconfirmed product must be fresh.');
        $this->assertEquals('listed', $expiredProduct->listing_status, 'Reconfirmation must restore listed status.');
        $this->assertCount(1, Product::marketplaceEligible()->where('id', $expiredProduct->id)->get());
    }

    /**
     * Test 11: Scheduled Artisan command transitions stale listings to unlisted
     */
    public function test_scheduled_command_expires_stale_listings_to_unlisted(): void
    {
        // Fresh product
        $freshProduct = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Fresh Product',
            'slug' => 'fresh-product',
            'unit_price' => '50000.00',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subDays(2),
        ]);

        // Stale product 1: confirmed 8 days ago
        $staleProduct1 = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Stale Product 1',
            'slug' => 'stale-product-1',
            'unit_price' => '50000.00',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subDays(8),
        ]);

        // Stale product 2: null confirmation
        $staleProduct2 = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Stale Product 2',
            'slug' => 'stale-product-2',
            'unit_price' => '50000.00',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => null,
        ]);

        $exitCode = Artisan::call('products:expire-stale-listings');
        $this->assertEquals(0, $exitCode);

        $freshProduct->refresh();
        $staleProduct1->refresh();
        $staleProduct2->refresh();

        $this->assertEquals('listed', $freshProduct->listing_status);
        $this->assertEquals('unlisted', $staleProduct1->listing_status);
        $this->assertEquals('unlisted', $staleProduct2->listing_status);
    }
}