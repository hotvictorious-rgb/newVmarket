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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
            'bank_updated_at' => Carbon::now()->subDays(5),
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

    public function test_vendor_in_shop_handover_requires_reservation_code_and_photo_upload(): void
    {
        [$user, $vendor, $shop] = $this->createVendor('Pickup Security Store', 'pickup-security@example.com', '08022334457');
        $customer = User::create([
            'name' => 'Pickup Customer',
            'email' => 'pickup-customer@example.com',
            'phone' => '08022334458',
            'password' => bcrypt('test'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);
        $order = Order::create([
            'order_number' => 'VM-PICKUP-SEC-01',
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'payment_reference' => 'REF-PICKUP-SEC-01',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '0.00',
            'grand_total' => '10000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'verification_code' => '112233',
            'pickup_verification_code' => '998877',
            'vendor_settlement_status' => 'held',
        ]);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/vendor/orders/verify-handover-otp', [
            'order_id' => $order->id,
            'otp' => '112233',
        ]);

        $response->assertStatus(422);
        $this->assertSame('confirmed', $order->fresh()->order_status);
        $this->assertNull($order->fresh()->customer_pickup_photo_path);
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

    public function test_vendor_handover_ready_rejects_unpaid_and_in_shop_orders(): void
    {
        [$user, $vendor, $shop] = $this->createVendor('Handover Guard Store', 'handover-guard@example.com', '08044556678');
        $customer = User::create([
            'name' => 'Handover Guard Customer',
            'email' => 'handover-guard-customer@example.com',
            'phone' => '08044556679',
            'password' => bcrypt('test'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $cases = [
            [
                'order_number' => 'VM-HANDOVER-UNPAID',
                'fulfillment_type' => 'doorstep_delivery',
                'order_status' => 'confirmed',
                'payment_status' => 'pending',
            ],
            [
                'order_number' => 'VM-HANDOVER-PICKUP',
                'fulfillment_type' => 'in_shop_pickup',
                'order_status' => 'confirmed',
                'payment_status' => 'paid',
            ],
        ];

        Sanctum::actingAs($user);

        foreach ($cases as $case) {
            $order = Order::create(array_merge($case, [
                'customer_id' => $customer->id,
                'vendor_id' => $vendor->id,
                'shop_id' => $shop->id,
                'payment_method' => 'paystack',
                'payment_reference' => 'REF-' . $case['order_number'],
                'merchandise_subtotal' => '10000.00',
                'delivery_fee' => $case['fulfillment_type'] === 'in_shop_pickup' ? '0.00' : '1500.00',
                'grand_total' => $case['fulfillment_type'] === 'in_shop_pickup' ? '10000.00' : '11500.00',
                'vendor_net_90' => '9000.00',
                'customer_cashback_5' => '500.00',
                'platform_margin_5' => '500.00',
                'vendor_settlement_status' => 'held',
            ]));

            $response = $this->postJson("/api/v1/vendor/orders/{$order->id}/ready-for-handover");

            $response->assertStatus(422)->assertJsonPath('status', false);
            $this->assertSame($case['order_status'], $order->fresh()->order_status);
            $this->assertNull($order->fresh()->vendor_handover_ready_at);
        }
    }

    public function test_vendor_bank_update_triggers_48h_cooldown(): void
    {
        [$user, $vendor, $shop] = $this->createVendor('Shield Store', 'shield@example.com', '08044556677');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/vendor/bank/update', [
            'bank_name' => 'Access Bank',
            'bank_code' => '044',
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
    public function test_vendor_employee_is_mathematically_blocked_from_mobile_terminal_endpoints(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createVendor('Mega Store', 'mega@example.com', '08012345678');

        $employeeUser = User::create([
            'name' => 'Counter Worker A',
            'email' => 'counter.worker@example.com',
            'phone' => '08099887766',
            'password' => bcrypt('Password123!'),
            'user_type' => 'vendor_employee',
            'status' => 'active',
        ]);

        VendorEmployeeProfile::create([
            'user_id' => $employeeUser->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'role' => 'inspection_staff',
            'is_active' => true,
        ]);

        Sanctum::actingAs($employeeUser);

        $res = $this->withHeaders([
            'X-Terminal-Client' => 'vendor_mobile_terminal',
            'X-Required-Role' => 'vendor',
        ])->getJson('/api/v1/vendor/dashboard');

        $res->assertStatus(403);
    }
    public function test_vendor_payouts_endpoint_returns_fintech_balances_and_history(): void
    {
        [$user, $vendor, $shop] = $this->createVendor('Pay Store', 'pay@example.com', '08099112233');
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/vendor/payouts');

        $res->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'data' => [
                    'bank_details' => ['bank_name', 'account_number_masked', 'account_name', 'is_configured'],
                    'security_cooldown' => ['is_active'],
                    'balances' => ['withdrawable_balance', 'escrow_held_balance', 'pending_payout_balance', 'total_settled_balance', 'unrecovered_debt'],
                    'payout_requests',
                    'settled_history',
                    'eligible_pending',
                ]
            ]);
    }

    public function test_vendor_api_payout_is_held_when_bank_update_timestamp_is_missing(): void
    {
        [$user, $vendor] = $this->createVendor('Legacy Bank Timestamp', 'legacy-bank-time@example.com', '08099223345');
        $vendor->update(['bank_updated_at' => null]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/vendor/payouts', ['amount' => 5000.00]);

        $response->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 'BANK_VERIFICATION_COOLDOWN');
        $this->assertSame(0, \App\Models\VendorPayoutRequest::where('vendor_id', $vendor->id)->count());
    }

    public function test_vendor_can_request_payout_within_withdrawable_balance(): void
    {
        [$user, $vendor, $shop] = $this->createVendor('Fast Payouts', 'fast@example.com', '08099223344');
        Sanctum::actingAs($user);

        $customer = User::create([
            'name' => 'Buyer Joe',
            'email' => 'joe.buyer@example.com',
            'phone' => '08012344321',
            'password' => bcrypt('test'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        // Seed delivered and eligible order
        Order::create([
            'order_number' => 'VM-TEST-PAY-01',
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'origin_lga_id' => 1,
            'destination_lga_id' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
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
            'vendor_settlement_status' => 'eligible',
            'delivered_at' => Carbon::now()->subHours(25),
        ]);

        $this->assertEquals('18000.00', $vendor->getWithdrawableBalance());

        // Partial allocation of an earning record is rejected before a payout request reserves funds.
        $partial = $this->postJson('/api/v1/vendor/payouts', [
            'amount' => 5000.00,
        ]);
        $partial->assertStatus(422)
            ->assertJsonPath('status', false);
        $this->assertSame(0, \App\Models\VendorPayoutRequest::where('vendor_id', $vendor->id)->count());
        $this->assertEquals('18000.00', $vendor->fresh()->getWithdrawableBalance());

        // Whole earning record in FIFO order is accepted.
        $res = $this->postJson('/api/v1/vendor/payouts', [
            'amount' => 18000.00,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.amount', '18000.00')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('vendor_payout_requests', [
            'vendor_id' => $vendor->id,
            'amount' => 18000.00,
            'status' => 'pending',
        ]);

        $second = $this->postJson('/api/v1/vendor/payouts', ['amount' => 5000.00]);
        $second->assertStatus(409)->assertJsonPath('status', false);
        $this->assertSame(1, \App\Models\VendorPayoutRequest::where('vendor_id', $vendor->id)->count());

        // Financial journal double entry recorded
        $this->assertDatabaseHas('financial_journal_entries', [
            'beneficiary_id' => $vendor->id,
            'account_name' => 'vendor_payable',
            'entry_type' => 'debit',
            'amount' => 18000.00,
        ]);
        $this->assertDatabaseHas('financial_journal_entries', [
            'beneficiary_id' => $vendor->id,
            'account_name' => 'vendor_payout_payable',
            'entry_type' => 'credit',
            'amount' => 18000.00,
        ]);
    }

    public function test_vendor_api_payout_enforces_approved_minimum_and_maximum(): void
    {
        [$user, $vendor] = $this->createVendor('Bounded Payout Store', 'bounds@example.com', '08099334456');
        Sanctum::actingAs($user);

        $belowMinimum = $this->postJson('/api/v1/vendor/payouts', ['amount' => 1000.00]);
        $belowMinimum->assertStatus(422);
        $this->assertDatabaseMissing('vendor_payout_requests', ['vendor_id' => $vendor->id]);

        $aboveMaximum = $this->postJson('/api/v1/vendor/payouts', ['amount' => 500001.00]);
        $aboveMaximum->assertStatus(422);
        $this->assertDatabaseMissing('vendor_payout_requests', ['vendor_id' => $vendor->id]);
    }

    public function test_vendor_payout_rejected_when_amount_exceeds_withdrawable_balance(): void
    {
        [$user, $vendor, $shop] = $this->createVendor('Low Balance Store', 'low@example.com', '08099334455');
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/vendor/payouts', [
            'amount' => 10000.00,
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonFragment(['status' => false]);
    }

    public function test_vendor_payout_rejected_during_48h_cooldown(): void
    {
        [$user, $vendor, $shop] = $this->createVendor('Cooldown Store', 'cool@example.com', '08099445566');
        Sanctum::actingAs($user);

        // Update bank to trigger cooldown
        $this->postJson('/api/v1/vendor/bank/update', [
            'bank_name' => 'GTBank',
            'bank_code' => '058',
            'account_number' => '0987654321',
            'account_name' => 'COOLDOWN ENTERPRISES',
        ]);

        $res = $this->postJson('/api/v1/vendor/payouts', [
            'amount' => 2000.00,
        ]);

        $res->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Security Cooldown Active: Bank account was recently updated. For fraud prevention, withdrawals are locked for 48 hours following bank detail modifications.');
    }

    public function test_vendor_can_upload_product_with_photo_file_and_gallery(): void
    {
        Storage::fake('public');
        [$user, $vendor, $shop] = $this->createVendor('Photo Store', 'photo@example.com', '08099556677');
        Sanctum::actingAs($user);

        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $filePrimary = UploadedFile::fake()->image('tv_front.jpg', 800, 600);
        $fileGallery1 = UploadedFile::fake()->image('tv_side.jpg', 800, 600);

        $res = $this->postJson('/api/v1/vendor/products', [
            'name' => 'Samsung 55 inch OLED 4K TV',
            'unit_price' => 450000.00,
            'inventory_quantity' => 5,
            'category_id' => $category->id,
            'package_tier' => 'keke',
            'condition' => 'brand_new',
            'image' => $filePrimary,
            'images' => [$fileGallery1],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('status', true);

        $product = Product::where('name', 'Samsung 55 inch OLED 4K TV')->first();
        $this->assertNotNull($product);
        $this->assertNotEquals('storage/products/default.png', $product->thumbnail);
        $this->assertNotEmpty($product->images);
    }

    public function test_vendor_can_update_product(): void
    {
        [$user, $vendor, $shop] = $this->createVendor('Update Store', 'update@example.com', '08099667788');
        Sanctum::actingAs($user);

        $category = Category::create([
            'name' => 'Home Appliances',
            'slug' => 'home-appliances',
            'is_active' => true,
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Electric Blender 1000W',
            'slug' => 'electric-blender-1000w',
            'unit_price' => 25000.00,
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'thumbnail' => 'storage/products/default.png',
        ]);

        $res = $this->postJson("/api/v1/vendor/products/{$product->id}", [
            'unit_price' => 28000.00,
            'inventory_quantity' => 15,
            'slash_price' => 35000.00,
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('status', true);

        $product->refresh();
        $this->assertEquals('28000.00', $product->unit_price);
        $this->assertEquals(15, $product->inventory_quantity);
        $this->assertEquals('35000.00', $product->slash_price);
    }
}
