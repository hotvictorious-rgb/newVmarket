<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CustomerApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Cache::flush();
    }

    public function test_banners_endpoint_returns_hero_and_promo_banners(): void
    {
        Banner::create([
            'banner_type' => 'hero_slider',
            'template_style' => 'flash_urgency',
            'title' => 'Mobile Flash Deal 2026',
            'subtitle' => 'Live verified deals in Uyo',
            'badge_text' => 'HOT',
            'discount_tag' => '50% OFF',
            'feature_tags' => 'Express, Warranty',
            'is_active' => true,
        ]);

        Banner::create([
            'banner_type' => 'promo_strip',
            'template_style' => 'fresh_market',
            'title' => 'Mid Page Strip Promo',
            'badge_text' => 'FRESH',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/banners');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
        ]);

        $this->assertNotEmpty($response->json('data.hero_banners'));
        $this->assertNotEmpty($response->json('data.promo_banners'));
        $this->assertEquals('flash_urgency', $response->json('data.hero_banners.0.template_style'));
        $this->assertEquals('Mobile Flash Deal 2026', $response->json('data.hero_banners.0.title'));
    }

    public function test_customer_can_retrieve_order_history_and_details(): void
    {
        $customer = User::where('user_type', 'customer')->first();
        $this->assertNotNull($customer);

        $shop = Shop::first();
        $this->assertNotNull($shop);

        $order = Order::create([
            'order_number' => 'VM-TEST-ORDER-101',
            'customer_id' => $customer->id,
            'vendor_id' => $shop->vendor_id,
            'shop_id' => $shop->id,
            'origin_lga_id' => $shop->lga_id,
            'destination_lga_id' => $shop->lga_id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'merchandise_subtotal' => 15000.00,
            'delivery_fee' => 1200.00,
            'grand_total' => 16200.00,
            'vendor_net_90' => 13500.00,
            'customer_cashback_5' => 750.00,
            'platform_margin_5' => 750.00,
            'logistics_fee_retained' => 1080.00,
            'platform_delivery_fee' => 120.00,
            'pickup_verification_code' => Order::generateUniquePickupOtp(),
            'verification_code' => Order::generateUniqueDeliveryOtp(),
        ]);

        // 1. Fetch Orders List
        $listRes = $this->actingAs($customer, 'sanctum')->getJson('/api/v1/customer/orders');
        $listRes->assertStatus(200);
        $listRes->assertJson(['status' => true]);
        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));

        // 2. Fetch Order Full Details
        $detailRes = $this->actingAs($customer, 'sanctum')->getJson("/api/v1/customer/orders/{$order->order_number}");
        $detailRes->assertStatus(200);
        $detailRes->assertJson([
            'status' => true,
            'data' => [
                'order_number' => 'VM-TEST-ORDER-101',
                'tracking_stage' => 1,
                'delivery_otp' => $order->verification_code,
            ],
        ]);
    }

    public function test_customer_can_confirm_delivery_handshake(): void
    {
        $customer = User::where('user_type', 'customer')->first();
        $shop = Shop::first();

        $order = Order::create([
            'order_number' => 'VM-HANDSHAKE-202',
            'customer_id' => $customer->id,
            'vendor_id' => $shop->vendor_id,
            'shop_id' => $shop->id,
            'origin_lga_id' => $shop->lga_id,
            'destination_lga_id' => $shop->lga_id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'merchandise_subtotal' => 20000.00,
            'delivery_fee' => 1500.00,
            'grand_total' => 21500.00,
            'vendor_net_90' => 18000.00,
            'customer_cashback_5' => 1000.00,
            'platform_margin_5' => 1000.00,
            'logistics_fee_retained' => 1350.00,
            'platform_delivery_fee' => 150.00,
            'pickup_verification_code' => Order::generateUniquePickupOtp(),
            'verification_code' => '987654',
        ]);

        // Wrong OTP fails
        $failRes = $this->actingAs($customer, 'sanctum')->postJson("/api/v1/customer/orders/{$order->order_number}/confirm-delivery", [
            'delivery_otp' => '000000',
        ]);
        $failRes->assertStatus(422);

        // Correct OTP succeeds
        $passRes = $this->actingAs($customer, 'sanctum')->postJson("/api/v1/customer/orders/{$order->order_number}/confirm-delivery", [
            'delivery_otp' => '987654',
        ]);
        $passRes->assertStatus(200);
        $passRes->assertJson([
            'status' => true,
            'data' => [
                'order_status' => 'delivered',
                'tracking_stage' => 5,
            ],
        ]);

        $order->refresh();
        $this->assertEquals('delivered', $order->order_status);
        $this->assertTrue($order->delivery_confirmed_by_customer);
    }

    public function test_customer_can_execute_checkout_and_get_paystack_info(): void
    {
        $customer = User::where('user_type', 'customer')->first();
        $shop = Shop::first();
        $product = Product::where('shop_id', $shop->id)->first();
        $this->assertNotNull($product);

        // Ensure product is marketplace eligible and in stock
        $product->update([
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => now(),
            'inventory_quantity' => 10, 'is_in_stock' => true,
        ]);

        $address = CustomerAddress::create([
            'user_id' => $customer->id,
            'contact_name' => $customer->name,
            'phone' => $customer->phone ?? '08012345678',
            'address_line' => '14 Wellington Bassey Way, Uyo',
            'country_id' => 1,
            'state_id' => $shop->state_id,
            'lga_id' => $shop->lga_id,
        ]);

        $postData = [
            'shop_id' => $shop->id,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_address_id' => $address->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            'payment_method' => 'paystack',
        ];

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/customer/orders/checkout', $postData);
        $response->assertStatus(201);
        $response->assertJson([
            'status' => true,
            'message' => 'Order created successfully. Proceed to payment.',
        ]);

        $this->assertNotEmpty($response->json('data.order_number'));
        $this->assertNotEmpty($response->json('data.paystack'));
        $this->assertDatabaseHas('orders', [
            'order_number' => $response->json('data.order_number'),
            'customer_id' => $customer->id,
            'payment_method' => 'paystack',
        ]);
    }

    public function test_remote_config_endpoint_returns_dynamic_system_and_corridor_controls(): void
    {
        $response = $this->getJson('/api/v1/config');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => [
                'currency_symbol' => '₦',
                'currency_code' => 'NGN',
                'cashback_percentage' => 5.0,
                'version_control' => [
                    'min_version' => '1.0.0',
                    'force_update' => false,
                ],
                'maintenance_mode' => [
                    'is_active' => false,
                ],
            ],
        ]);

        $this->assertNotEmpty($response->json('data.app_name'));
        $this->assertNotEmpty($response->json('data.support_phone'));
        $this->assertNotEmpty($response->json('data.support_email'));
        $this->assertIsArray($response->json('data.active_corridors'));
        $this->assertNotEmpty($response->json('data.active_corridors'));
    }
}
