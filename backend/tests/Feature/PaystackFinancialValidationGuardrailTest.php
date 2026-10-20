<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\Lga;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaystackFinancialValidationGuardrailTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected Shop $shop;
    protected Category $category;
    protected User $customerUser;
    protected Lga $uyoLga;

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

        $this->vendorUser = User::create([
            'name' => 'Plaza Vendor',
            'email' => 'vendor@victoriousmarket.ng',
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

        $this->customerUser = User::create([
            'name' => 'Precious Customer',
            'email' => 'customer@victoriousmarket.ng',
            'phone' => '+2348099887766',
            'password' => bcrypt('SecurePass123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);
    }

    public function test_vendor_cannot_create_product_with_astronomical_or_invalid_price(): void
    {
        Sanctum::actingAs($this->vendorUser, ['vendor']);

        // 1. Attempt 42-digit astronomical price
        $response = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Astronomical Gold Bar',
            'category_id' => $this->category->id,
            'unit_price' => '999999999999999999999999999999999999999999',
            'inventory_quantity' => 10,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['unit_price']);

        // 2. Attempt price exceeding ₦50,000,000 cap
        $responseExceed = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Mega Yacht',
            'category_id' => $this->category->id,
            'unit_price' => '50000001.00',
            'inventory_quantity' => 1,
        ]);

        $responseExceed->assertStatus(422);
        $responseExceed->assertJsonValidationErrors(['unit_price']);

        // 3. Attempt price below Paystack minimum of ₦1.00 (e.g. ₦0.50)
        $responseBelow = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Half Kobo Candy',
            'category_id' => $this->category->id,
            'unit_price' => '0.50',
            'inventory_quantity' => 100,
        ]);

        $responseBelow->assertStatus(422);
        $responseBelow->assertJsonValidationErrors(['unit_price']);

        // 4. Attempt 3 decimal places (Paystack/banking only allows 2 decimal kobo)
        $responseDecimals = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Fractional Candy',
            'category_id' => $this->category->id,
            'unit_price' => '100.555',
            'inventory_quantity' => 100,
        ]);

        $responseDecimals->assertStatus(422);
        $responseDecimals->assertJsonValidationErrors(['unit_price']);
    }

    public function test_vendor_can_create_product_with_valid_paystack_standard_price(): void
    {
        Sanctum::actingAs($this->vendorUser, ['vendor']);

        $response = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Quality Smartphone',
            'category_id' => $this->category->id,
            'unit_price' => '150000.50',
            'slash_price' => '175000.00',
            'inventory_quantity' => 25,
            'condition' => 'brand_new',
            'package_tier' => 'bike',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'vendor_id' => $this->vendor->id,
            'name' => 'Quality Smartphone',
            'unit_price' => '150000.50',
            'slash_price' => '175000.00',
        ]);
    }

    public function test_paystack_service_rejects_below_minimum_or_exceeding_maximum_amounts(): void
    {
        /** @var PaystackService $service */
        $service = app(PaystackService::class);

        // Order below ₦1.00 minimum
        $cheapOrder = Order::create([
            'order_number' => 'VM-TEST-MIN',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'paystack',
            'payment_reference' => 'VM-REF-MIN-' . uniqid(),
            'guest_access_token' => bin2hex(random_bytes(16)),
            'merchandise_subtotal' => '0.50',
            'delivery_fee' => '0.00',
            'grand_total' => '0.50',
            'vendor_net_90' => '0.45',
            'customer_cashback_5' => '0.03',
            'platform_margin_5' => '0.02',
        ]);

        $minResult = $service->initializeTransaction($cheapOrder, 'https://example.com/callback');
        $this->assertFalse($minResult['success']);
        $this->assertEquals('AMOUNT_BELOW_MINIMUM', $minResult['code']);

        // Order exceeding ₦100,000,000 maximum single transaction
        $hugeOrder = Order::create([
            'order_number' => 'VM-TEST-MAX',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'paystack',
            'payment_reference' => 'VM-REF-MAX-' . uniqid(),
            'guest_access_token' => bin2hex(random_bytes(16)),
            'merchandise_subtotal' => '100000001.00',
            'delivery_fee' => '0.00',
            'grand_total' => '100000001.00',
            'vendor_net_90' => '90000000.90',
            'customer_cashback_5' => '5000000.05',
            'platform_margin_5' => '5000000.05',
        ]);

        $maxResult = $service->initializeTransaction($hugeOrder, 'https://example.com/callback');
        $this->assertFalse($maxResult['success']);
        $this->assertEquals('AMOUNT_EXCEEDS_MAXIMUM', $maxResult['code']);
    }

    public function test_checkout_rejects_exorbitant_quantity_causing_overflow(): void
    {
        Sanctum::actingAs($this->customerUser, ['customer']);

        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Valid Product',
            'slug' => 'valid-product-' . uniqid(),
            'unit_price' => '1000.00',
            'inventory_quantity' => 50000,
            'is_in_stock' => true,
        ]);

        $response = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'in_shop_pickup',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 99999999, // Exceeds 1000 max quantity guardrail
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_paystack_initialization_connection_failure_is_not_retried_blindly(): void
    {
        config([
            'services.paystack.secret_key' => 'sk_test_guardrail',
            'services.paystack.simulate_in_tests' => false,
        ]);

        $order = Order::create([
            'order_number' => 'VM-INIT-UNCERTAIN',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'paystack',
            'payment_reference' => 'VM-INIT-UNCERTAIN-REF',
            'guest_access_token' => bin2hex(random_bytes(16)),
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '0.00',
            'grand_total' => '10000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
        ]);

        Http::fake(function () {
            throw new ConnectionException('Simulated timeout after request dispatch.');
        });

        $result = app(PaystackService::class)->initializeTransaction(
            $order,
            'https://example.com/callback',
            $this->customerUser->email
        );

        $this->assertFalse($result['success']);
        $this->assertSame('NETWORK_ERROR', $result['code']);
        Http::assertSentCount(1);
    }

}