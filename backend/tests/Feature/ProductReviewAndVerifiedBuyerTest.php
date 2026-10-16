<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Lga;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\ReviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewAndVerifiedBuyerTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $vendorUser;
    protected VendorProfile $vendorProfile;
    protected Shop $shop;
    protected Category $category;
    protected Product $product;
    protected Country $country;
    protected State $state;
    protected Lga $lga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);
        $this->state = State::create(['country_id' => $this->country->id, 'name' => 'Lagos', 'code' => 'LA', 'is_active' => true]);
        $this->lga = Lga::create(['state_id' => $this->state->id, 'name' => 'Ikeja', 'code' => 'IKJ', 'is_active' => true]);

        $this->customer = User::factory()->create([
            'user_type' => 'customer',
            'email' => 'customer@test.com',
        ]);

        $this->vendorUser = User::factory()->create([
            'user_type' => 'vendor',
            'email' => 'vendor@test.com',
        ]);

        $this->vendorProfile = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Tech Hub Nigeria',
            'marketplace_status' => 'active',
            'account_number' => '0123456789',
            'bank_name' => 'Access Bank',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendorProfile->id,
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'name' => 'Tech Hub Ikeja Store',
            'slug' => 'tech-hub-ikeja',
            'address' => '12 Computer Village, Ikeja',
        ]);

        $this->category = Category::create([
            'name' => 'Laptops & Gadgets',
            'slug' => 'laptops-gadgets',
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'MacBook Pro 14 M3',
            'slug' => 'macbook-pro-14-m3',
            'unit_price' => '1500000.00',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'rating' => 0.00,
            'reviews_count' => 0,
        ]);
    }

    protected function createTestOrder(string $number, string $status, bool $deliveredConfirmed = true): Order
    {
        return Order::create([
            'order_number' => $number,
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'order_status' => $status,
            'delivery_confirmed_by_customer' => $deliveredConfirmed,
            'merchandise_subtotal' => '1500000.00',
            'delivery_fee' => '2500.00',
            'grand_total' => '1502500.00',
            'vendor_net_90' => '1350000.00',
            'customer_cashback_5' => '75000.00',
            'platform_margin_5' => '75000.00',
            'logistics_fee_retained' => '2500.00',
        ]);
    }

    public function test_unauthenticated_user_cannot_submit_review(): void
    {
        $response = $this->postJson('/api/v1/customer/reviews', [
            'order_id' => 1,
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Great product!',
        ]);

        $response->assertStatus(401);
    }

    public function test_customer_cannot_review_product_they_never_purchased(): void
    {
        $order = $this->createTestOrder('ORD-REV-101', 'delivered', true);

        $otherProduct = Product::create([
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'USB Cable',
            'slug' => 'usb-cable',
            'unit_price' => '20000.00',
            'inventory_quantity' => 50,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $otherProduct->id,
            'product_name' => $otherProduct->name,
            'unit_price' => '20000.00',
            'quantity' => 1,
            'line_total' => '20000.00',
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/customer/reviews', [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Fake review attempt',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This product was not purchased in the specified order.');
    }

    public function test_customer_cannot_review_undelivered_order(): void
    {
        $order = $this->createTestOrder('ORD-REV-102', 'in_transit', false);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'unit_price' => '1500000.00',
            'quantity' => 1,
            'line_total' => '1500000.00',
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/customer/reviews', [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Excited to receive it!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only successfully delivered orders are eligible for product reviews.');
    }

    public function test_verified_buyer_can_post_review_and_rating_recalculates(): void
    {
        $order = $this->createTestOrder('ORD-REV-103', 'delivered', true);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'unit_price' => '1500000.00',
            'quantity' => 1,
            'line_total' => '1500000.00',
            'item_delivery_status' => 'delivered',
            'accepted_quantity' => 1,
            'rejected_quantity' => 0,
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/customer/reviews', [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'headline' => 'Super fast laptop!',
            'comment' => 'Tested in store and delivery was swift. Battery life is unbelievable.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.review.is_verified_buyer', true)
            ->assertJsonPath('data.product_rating', '5.00')
            ->assertJsonPath('data.reviews_count', 1);

        $this->product->refresh();
        $this->assertEquals('5.00', (string)$this->product->rating);
        $this->assertEquals(1, $this->product->reviews_count);

        $publicRes = $this->getJson("/api/v1/products/{$this->product->id}/reviews");
        $publicRes->assertStatus(200)
            ->assertJsonPath('data.reviews_count', 1)
            ->assertJsonPath('data.average_rating', 5);
    }

    public function test_review_is_automatically_revoked_if_returned(): void
    {
        $order = $this->createTestOrder('ORD-REV-104', 'delivered', true);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'unit_price' => '1500000.00',
            'quantity' => 1,
            'line_total' => '1500000.00',
            'item_delivery_status' => 'delivered',
            'accepted_quantity' => 1,
            'rejected_quantity' => 0,
        ]);

        $reviewService = app(ReviewService::class);
        $review = $reviewService->submitReview($this->customer, [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'rating' => 4,
            'headline' => 'Good machine',
            'comment' => 'Works well but slightly warm.',
        ]);

        $this->product->refresh();
        $this->assertEquals(1, $this->product->reviews_count);
        $this->assertEquals('4.00', (string)$this->product->rating);

        // Item returned: revoke review
        $revokedCount = $reviewService->revokeReviewsForOrder($order->id, $this->product->id, 'Customer returned defective screen');

        $this->assertEquals(1, $revokedCount);
        $review->refresh();
        $this->assertEquals('revoked_returned', $review->status);
        $this->assertEquals('Customer returned defective screen', $review->revoked_reason);

        $this->product->refresh();
        $this->assertEquals(0, $this->product->reviews_count);
        $this->assertEquals('0.00', (string)$this->product->rating);

        $publicRes = $this->getJson("/api/v1/products/{$this->product->id}/reviews");
        $publicRes->assertStatus(200)
            ->assertJsonPath('data.reviews_count', 0)
            ->assertJsonPath('data.reviews.total', 0);
    }
}