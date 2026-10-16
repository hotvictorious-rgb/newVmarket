<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Lga;
use App\Models\Order;
use App\Models\PickupReservation;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorEmployeeProfile;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorApiTerminalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCountryStateLgas();
    }

    private function seedCountryStateLgas(): void
    {
        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);
        $state = State::create(['country_id' => $country->id, 'name' => 'Akwa Ibom', 'code' => 'AK', 'is_active' => true]);
        Lga::create(['state_id' => $state->id, 'name' => 'Uyo', 'code' => 'UYO', 'is_active' => true]);
        Lga::create(['state_id' => $state->id, 'name' => 'Eket', 'code' => 'EKT', 'is_active' => true]);
    }

    private function createVendor(string $name, string $email, string $phone): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => bcrypt('Password123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $vendor = VendorProfile::create([
            'user_id' => $user->id,
            'business_name' => $name . ' Enterprises',
            'contact_phone' => $phone,
            'marketplace_status' => 'active',
            'commission_rate' => 10.00,
            'bank_name' => 'Zenith Bank',
            'account_number' => '1029384756',
            'account_name' => strtoupper($name . ' ENTERPRISES'),
        ]);

        $lga = Lga::first();
        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'name' => $name . ' Main Store',
            'slug' => \Illuminate\Support\Str::slug($name . ' Main Store'),
            'address' => 'Aka Road, Uyo',
            'phone' => $phone,
            'lga_id' => $lga->id,
            'state_id' => $lga->state_id,
            'country_id' => 1,
            'pickup_enabled' => true,
            'is_primary_branch' => true,
            'is_active' => true,
        ]);

        return [$user, $vendor, $shop];
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/vendor/dashboard');
        $response->assertStatus(401);
    }

    public function test_customer_user_is_forbidden_from_vendor_endpoints(): void
    {
        $customer = User::create([
            'name' => 'Customer Joe',
            'email' => 'joe@example.com',
            'phone' => '08011223344',
            'password' => bcrypt('test'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/v1/vendor/dashboard');
        $response->assertStatus(403);
    }

    public function test_vendor_dashboard_returns_fintech_metrics_and_cooldown_status(): void
    {
        [$user, $vendor, $shop] = $this->createVendor('Apex Retail', 'apex@example.com', '08022334455');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/vendor/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.merchant.business_name', 'Apex Retail Enterprises')
            ->assertJsonPath('data.financial_metrics.currency', 'NGN')
            ->assertJsonPath('data.security_cooldown.is_active', false);
    }

    public function test_vendor_can_list_and_filter_orders(): void
    {
        [$user, $vendor, $shop] = $this->createVendor('Mega Shop', 'mega@example.com', '08033445566');
        Sanctum::actingAs($user);

        $customer = User::create([
            'name' => 'Buyer Jane',
            'email' => 'jane@example.com',
            'phone' => '08099881122',
            'password' => bcrypt('test'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $order = Order::create([
            'order_number' => 'VM-TEST-ORD-01',
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'origin_lga_id' => 1,
            'destination_lga_id' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'processing',
            'merchandise_subtotal' => 20000.00,
            'delivery_fee' => 1500.00,
            'cashback_redeemed' => 0.00,
            'grand_total' => 21500.00,
            'vendor_net_90' => 18000.00,
            'customer_cashback_5' => 1000.00,
            'platform_margin_5' => 1000.00,
            'logistics_fee_retained' => 1350.00,
            'platform_delivery_fee' => 150.00,
            'payment_status' => 'paid',
        ]);

        $response = $this->getJson('/api/v1/vendor/orders');
        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('pagination.total', 1);

        // Mark ready for handover
        $readyRes = $this->postJson("/api/v1/vendor/orders/{$order->id}/ready-for-handover");
        $readyRes->assertStatus(200)
            ->assertJsonPath('status', true);

        $order->refresh();
        $this->assertEquals('ready_for_pickup', $order->order_status);
        $this->assertNotNull($order->vendor_handover_ready_at);
    }

    public function test_vendor_bank_update_triggers_48h_cooldown(): void
    {
        [$user, $vendor, $shop] = $this->createVendor('Shield Store', 'shield@example.com', '08044556677');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/vendor/bank/update', [
            'bank_name' => 'Access Bank',
            'account_number' => '0123456789',
            'account_name' => 'SHIELD STORE ENTERPRISES',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.cooldown_active', true);

        $vendor->refresh();
        $this->assertEquals('0123456789', $vendor->account_number);
        $this->assertTrue($vendor->isWithinBankCooldown());
    }

    public function test_vendor_can_publish_product_and_refresh_freshness(): void
    {
        [$user, $vendor, $shop] = $this->createVendor('Organic Foods', 'organic@example.com', '08055667788');
        Sanctum::actingAs($user);

        $category = Category::create([
            'name' => 'Fresh Groceries',
            'slug' => 'fresh-groceries',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Fresh Akwa Ibom Palm Oil (5 Litres)',
            'unit_price' => 12500.00,
            'inventory_quantity' => 25,
            'category_id' => $category->id,
            'description' => '100% pure organic palm oil extracted directly from Ibiono oil mills.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $product = Product::latest('id')->first();
        $this->assertNotNull($product);
        $this->assertEquals(25, $product->inventory_quantity);
        $this->assertNotNull($product->listing_confirmed_at);

        // Refresh freshness lifecycle
        $refreshRes = $this->postJson("/api/v1/vendor/products/{$product->id}/freshness");
        $refreshRes->assertStatus(200)
            ->assertJsonPath('status', true);
    }
}
