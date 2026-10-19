<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Lga;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductFreshnessLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected Shop $shop;
    protected Category $category;
    protected User $customer;
    protected Lga $lga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->vendor = VendorProfile::where('marketplace_status', 'active')->firstOrFail();
        $this->vendorUser = $this->vendor->user;
        $this->shop = Shop::where('vendor_id', $this->vendor->id)->where('is_active', true)->firstOrFail();
        $this->lga = $this->shop->lga ?? Lga::firstOrFail();
        $this->category = Category::where('is_active', true)->firstOrFail();
        $this->customer = User::where('user_type', 'customer')->where('status', 'active')->firstOrFail();
    }

    protected function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Google Pixel 9 Pro (256GB Obsidian)',
            'slug' => 'google-pixel-9-pro-' . uniqid(),
            'description' => 'Flagship smartphone with Google Tensor G4',
            'unit_price' => '1200000.00',
            'inventory_quantity' => 10,
            'physical_quantity' => 10,
            'allocated_quantity' => 0,
            'held_quantity' => 0,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'package_tier' => 'bike',
            'condition' => 'brand_new',
        ], $overrides));
    }

    public function test_product_confirmed_under_7_days_ago_is_fresh_and_marketplace_eligible(): void
    {
        $now = Carbon::now();
        // 6 days, 23 hours ago
        $p = $this->makeProduct([
            'listing_confirmed_at' => $now->copy()->subDays(6)->subHours(23),
        ]);

        $this->assertTrue($p->isFresh());
        $this->assertTrue(Product::marketplaceEligible()->where('id', $p->id)->exists());
    }

    public function test_product_confirmed_exactly_7_days_ago_or_older_is_not_fresh_and_excluded(): void
    {
        $now = Carbon::now();
        // Exactly 7 days and 1 second ago
        $p7Days = $this->makeProduct([
            'listing_confirmed_at' => $now->copy()->subDays(7)->subSeconds(1),
        ]);

        // 8 days ago
        $p8Days = $this->makeProduct([
            'listing_confirmed_at' => $now->copy()->subDays(8),
        ]);

        $this->assertFalse($p7Days->isFresh());
        $this->assertFalse($p8Days->isFresh());
        $this->assertFalse(Product::marketplaceEligible()->where('id', $p7Days->id)->exists());
        $this->assertFalse(Product::marketplaceEligible()->where('id', $p8Days->id)->exists());
    }

    public function test_product_with_missing_or_future_confirmation_timestamp_is_excluded(): void
    {
        $now = Carbon::now();
        $pNull = $this->makeProduct(['listing_confirmed_at' => null]);
        $pFuture = $this->makeProduct(['listing_confirmed_at' => $now->copy()->addHours(2)]);

        $this->assertFalse($pNull->isFresh());
        $this->assertFalse($pFuture->isFresh());
        $this->assertFalse(Product::marketplaceEligible()->where('id', $pNull->id)->exists());
        $this->assertFalse(Product::marketplaceEligible()->where('id', $pFuture->id)->exists());
    }

    public function test_product_detail_page_returns_404_for_expired_product(): void
    {
        $pExpired = $this->makeProduct([
            'listing_confirmed_at' => Carbon::now()->subDays(8),
        ]);

        $response = $this->get('/product/' . $pExpired->slug);
        $response->assertStatus(404);
    }

    public function test_web_checkout_submission_rejects_expired_product(): void
    {
        $pExpired = $this->makeProduct([
            'listing_confirmed_at' => Carbon::now()->subDays(9),
        ]);

        $postData = [
            'product_id' => $pExpired->id,
            'quantity' => 1,
            'fulfillment_type' => 'in_shop_pickup',
            'shop_id' => $this->shop->id,
            'customer_name' => 'John Doe',
            'customer_phone' => '08012345678',
        ];

        $response = $this->actingAs($this->customer)->post('/checkout/process', $postData);
        $response->assertSessionHasErrors('product_id');
        $this->assertDatabaseMissing('orders', ['customer_id' => $this->customer->id]);
    }

    public function test_api_checkout_strictly_rejects_expired_product(): void
    {
        $pExpired = $this->makeProduct([
            'listing_confirmed_at' => Carbon::now()->subDays(10),
        ]);

        $postData = [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'in_shop_pickup',
            'items' => [
                ['product_id' => $pExpired->id, 'quantity' => 1],
            ],
            'payment_method' => 'paystack',
        ];

        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/customer/orders/checkout', $postData);
        $response->assertStatus(422);
        $response->assertJson(['status' => false]);
    }

    public function test_pickup_reservation_creation_rejects_expired_product(): void
    {
        $pExpired = $this->makeProduct([
            'listing_confirmed_at' => Carbon::now()->subDays(8),
        ]);

        // Web route test
        $response = $this->actingAs($this->customer)->post('/reservation/create', [
            'product_id' => $pExpired->id,
            'shop_id' => $this->shop->id,
            'quantity' => 1,
        ]);
        $response->assertSessionHasErrors('product_id');

        // API route test
        $apiResponse = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/customer/reservations/pickup', [
            'shop_id' => $this->shop->id,
            'product_id' => $pExpired->id,
            'quantity' => 1,
        ]);
        $this->assertContains($apiResponse->status(), [404, 422]);
    }

    public function test_vendor_reconfirmation_restores_expired_product_eligibility(): void
    {
        $pExpired = $this->makeProduct([
            'listing_confirmed_at' => Carbon::now()->subDays(12),
            'listing_status' => 'unlisted',
        ]);

        $this->assertFalse($pExpired->fresh()->isFresh());
        $this->assertFalse(Product::marketplaceEligible()->where('id', $pExpired->id)->exists());

        // Merchant reconfirms via API
        $response = $this->actingAs($this->vendorUser, 'sanctum')
            ->postJson("/api/v1/vendor/products/{$pExpired->id}/freshness");

        $response->assertStatus(200);
        $response->assertJson(['status' => true]);

        $freshProduct = $pExpired->fresh();
        $this->assertTrue($freshProduct->isFresh());
        $this->assertEquals('listed', $freshProduct->listing_status);
        $this->assertTrue(Product::marketplaceEligible()->where('id', $pExpired->id)->exists());
    }

    public function test_scheduled_expiration_command_transitions_stale_listed_products_to_unlisted(): void
    {
        $pFresh = $this->makeProduct([
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subDays(2),
        ]);

        $pStaleOld = $this->makeProduct([
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subDays(8),
        ]);

        $pStaleNull = $this->makeProduct([
            'listing_status' => 'listed',
            'listing_confirmed_at' => null,
        ]);

        $exitCode = Artisan::call('products:expire-stale-listings');
        $this->assertEquals(0, $exitCode);

        $this->assertEquals('listed', $pFresh->fresh()->listing_status);
        $this->assertEquals('unlisted', $pStaleOld->fresh()->listing_status);
        $this->assertEquals('unlisted', $pStaleNull->fresh()->listing_status);
    }
}