<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class WebCheckoutIdempotencyAndStateTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $vendorUser;
    protected VendorProfile $vendorProfile;
    protected Shop $shop;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        Config::set('services.paystack.secret_key', 'sk_test_mock_paystack_secret_key_1234567890');
        Config::set('services.paystack.public_key', 'pk_test_mock_paystack_public_key');
        Config::set('services.paystack.payment_url', 'https://api.paystack.co');

        $this->customer = User::where('user_type', 'customer')->firstOrFail();
        $this->vendorUser = User::where('user_type', 'vendor')->firstOrFail();
        $this->vendorProfile = $this->vendorUser->vendorProfile ?? VendorProfile::firstOrFail();
        $this->shop = Shop::where('vendor_id', $this->vendorProfile->id)->firstOrFail();
        $this->product = Product::where('shop_id', $this->shop->id)->firstOrFail();

        // Ensure product is freshly listed and in stock
        $this->product->update([
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'is_in_stock' => true,
            'inventory_quantity' => 20,
        ]);
    }

    /**
     * PROOF VM-AUD-067: Legacy Web Checkout POST /checkout/process enforces idempotency,
     * stores checkout_idempotency_key + request hash, and transitions Paystack initialization state.
     */
    public function test_web_checkout_with_idempotency_key_creates_order_with_persisted_hash_and_initialized_state(): void
    {
        $mockPaystack = Mockery::mock(PaystackService::class);
        $mockPaystack->shouldReceive('initializeTransaction')->once()->andReturn([
            'success' => true,
            'authorization_url' => 'https://checkout.paystack.com/auth-web-test-01',
            'access_code' => 'access_code_web_01',
            'reference' => 'VM-REF-WEB-001',
        ]);
        $this->app->instance(PaystackService::class, $mockPaystack);

        $idemKey = 'web-key-' . uniqid();

        $response = $this->actingAs($this->customer)->post(route('checkout.process'), [
            '_checkout_idempotency_key' => $idemKey,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => $this->customer->name,
            'customer_phone' => '08012345678',
            'shop_id' => $this->shop->id,
            'destination_lga_id' => $this->shop->lga_id,
            'delivery_address' => '10 Market Road, Uyo',
        ]);

        $response->assertRedirect('https://checkout.paystack.com/auth-web-test-01');

        $order = Order::where('customer_id', $this->customer->id)
            ->where('checkout_idempotency_key', $idemKey)
            ->first();

        $this->assertNotNull($order, 'Order must be created with the supplied idempotency key.');
        $this->assertNotEmpty($order->checkout_request_hash, 'checkout_request_hash must be computed and persisted.');
        $this->assertEquals('initialized', $order->paystack_initialization_status, 'Order paystack status must transition to initialized.');
        $this->assertEquals('https://checkout.paystack.com/auth-web-test-01', $order->paystack_authorization_url);
        $this->assertEquals('pending', $order->payment_status);
        $this->assertEquals('pending', $order->order_status);
    }

    /**
     * PROOF VM-AUD-067: Browser retry / duplicate submit with same idempotency key
     * returns the existing order redirect without duplicating orders or re-calling Paystack.
     */
    public function test_web_checkout_duplicate_submission_replays_existing_order_and_never_duplicates_order_or_paystack(): void
    {
        $mockPaystack = Mockery::mock(PaystackService::class);
        // Paystack initializeTransaction MUST be called exactly once despite 2 form submissions
        $mockPaystack->shouldReceive('initializeTransaction')->once()->andReturn([
            'success' => true,
            'authorization_url' => 'https://checkout.paystack.com/auth-replay-01',
            'access_code' => 'access_replay_01',
            'reference' => 'VM-REF-REPLAY-001',
        ]);
        $this->app->instance(PaystackService::class, $mockPaystack);

        $idemKey = 'web-replay-' . uniqid();
        $payload = [
            '_checkout_idempotency_key' => $idemKey,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => $this->customer->name,
            'customer_phone' => '08012345678',
            'shop_id' => $this->shop->id,
            'destination_lga_id' => $this->shop->lga_id,
            'delivery_address' => '10 Market Road, Uyo',
        ];

        // First submission
        $res1 = $this->actingAs($this->customer)->post(route('checkout.process'), $payload);
        $res1->assertRedirect('https://checkout.paystack.com/auth-replay-01');

        $initialOrderCount = Order::where('customer_id', $this->customer->id)->count();

        // Second duplicate submission (e.g. browser retry or network double-click)
        $res2 = $this->actingAs($this->customer)->post(route('checkout.process'), $payload);
        $res2->assertRedirect('https://checkout.paystack.com/auth-replay-01');

        $finalOrderCount = Order::where('customer_id', $this->customer->id)->count();
        $this->assertEquals($initialOrderCount, $finalOrderCount, 'Duplicate form submit must NOT create an additional order.');
    }

    /**
     * PROOF VM-AUD-067: Reusing same idempotency key with different payload is rejected with 409 conflict.
     */
    public function test_web_checkout_same_idempotency_key_with_different_payload_is_rejected_as_conflict(): void
    {
        $mockPaystack = Mockery::mock(PaystackService::class);
        $mockPaystack->shouldReceive('initializeTransaction')->once()->andReturn([
            'success' => true,
            'authorization_url' => 'https://checkout.paystack.com/auth-conflict-01',
            'access_code' => 'access_conflict_01',
        ]);
        $this->app->instance(PaystackService::class, $mockPaystack);

        $idemKey = 'web-conflict-' . uniqid();
        $payload1 = [
            '_checkout_idempotency_key' => $idemKey,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => $this->customer->name,
            'customer_phone' => '08012345678',
            'shop_id' => $this->shop->id,
            'destination_lga_id' => $this->shop->lga_id,
            'delivery_address' => '10 Market Road, Uyo',
        ];

        $res1 = $this->actingAs($this->customer)->post(route('checkout.process'), $payload1);
        $res1->assertRedirect('https://checkout.paystack.com/auth-conflict-01');

        // Conflicting second submission with quantity = 2 under the same key
        $payload2 = $payload1;
        $payload2['quantity'] = 2;

        $res2 = $this->actingAs($this->customer)->post(route('checkout.process'), $payload2);
        $res2->assertSessionHasErrors(['_checkout_idempotency_key']);

        $order = Order::where('customer_id', $this->customer->id)->where('checkout_idempotency_key', $idemKey)->first();
        $this->assertEquals(1, $order->items()->first()->quantity, 'Order items must not be altered by conflicting request.');
    }

    /**
     * PROOF VM-AUD-067: Both route aliases (POST /checkout and POST /checkout/process)
     * pass through the same idempotent CheckoutExecutionService boundary.
     */
    public function test_unnamed_post_checkout_alias_is_also_protected_by_idempotency_boundary(): void
    {
        $mockPaystack = Mockery::mock(PaystackService::class);
        $mockPaystack->shouldReceive('initializeTransaction')->once()->andReturn([
            'success' => true,
            'authorization_url' => 'https://checkout.paystack.com/auth-alias-01',
            'access_code' => 'access_alias_01',
        ]);
        $this->app->instance(PaystackService::class, $mockPaystack);

        $idemKey = 'web-alias-' . uniqid();

        $response = $this->actingAs($this->customer)->post('/checkout', [
            '_checkout_idempotency_key' => $idemKey,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => $this->customer->name,
            'customer_phone' => '08012345678',
            'shop_id' => $this->shop->id,
            'destination_lga_id' => $this->shop->lga_id,
            'delivery_address' => '10 Market Road, Uyo',
        ]);

        $response->assertRedirect('https://checkout.paystack.com/auth-alias-01');

        $order = Order::where('customer_id', $this->customer->id)
            ->where('checkout_idempotency_key', $idemKey)
            ->first();

        $this->assertNotNull($order);
        $this->assertEquals('initialized', $order->paystack_initialization_status);
    }

    /**
     * PROOF VM-AUD-067: Web checkout for In-Shop Pickup sets in_shop_counter,
     * skips online Paystack call, and replaying the key returns the existing reservation.
     */
    public function test_web_checkout_in_shop_pickup_does_not_initialize_paystack_and_replays_cleanly(): void
    {
        $mockPaystack = Mockery::mock(PaystackService::class);
        // Paystack must NOT be called for in-shop pickup
        $mockPaystack->shouldNotReceive('initializeTransaction');
        $this->app->instance(PaystackService::class, $mockPaystack);

        $idemKey = 'web-pickup-' . uniqid();
        $payload = [
            '_checkout_idempotency_key' => $idemKey,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'fulfillment_type' => 'in_shop_pickup',
            'customer_name' => $this->customer->name,
            'customer_phone' => '08012345678',
            'shop_id' => $this->shop->id,
        ];

        $res1 = $this->actingAs($this->customer)->post(route('checkout.process'), $payload);
        $res1->assertRedirect();
        $order = Order::where('checkout_idempotency_key', $idemKey)->firstOrFail();
        $this->assertEquals('ready_for_pickup', $order->order_status);
        $this->assertEquals('in_shop_counter', $order->payment_method);
        $this->assertEquals('not_started', $order->paystack_initialization_status);

        // Replay
        $res2 = $this->actingAs($this->customer)->post(route('checkout.process'), $payload);
        $res2->assertRedirect();
        $this->assertEquals(1, Order::where('checkout_idempotency_key', $idemKey)->count());
    }
}
