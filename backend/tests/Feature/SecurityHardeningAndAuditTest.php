<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorEmployeeProfile;
use App\Models\VendorProfile;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Helper to create merchant shop and vendor
     */
    protected function createMerchantWithShop(string $name, string $email, string $phone): array
    {
        $lga = Lga::where('name', 'Uyo')->first();

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
            'business_phone' => $phone,
            'support_email' => $email,
            'verification_status' => 'verified',
        ]);

        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'name' => $name . ' Plaza Shop',
            'slug' => \Illuminate\Support\Str::slug($name . ' Plaza Shop'),
            'address' => 'Plaza Block 4, Uyo',
            'country_id' => $lga->state->country_id ?? 1,
            'state_id' => $lga->state_id,
            'lga_id' => $lga->id,
            'is_active' => true,
        ]);

        return [$user, $vendor, $shop];
    }

    /**
     * 1. Critical Finding Fix: Unassigned rider or rider assigned to another order cannot pick up.
     */
    public function test_rider_assignment_enforcement_blocks_unauthorized_rider_pickup(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Auth Merchant', 'auth@example.com', '08099881122');
        $lga = Lga::where('name', 'Uyo')->first();
        $customer = User::where('user_type', 'customer')->first();

        $order = Order::create([
            'order_number' => 'VM-SEC-RIDER-01',
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'origin_lga_id' => $lga->id,
            'destination_lga_id' => $lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'ready_for_pickup',
            'vendor_handover_ready_at' => Carbon::now(),
            'pickup_verification_code' => '654321',
            'verification_code' => '123456',
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

        // Rider Alpha (Assigned)
        $riderUserA = User::create(['name' => 'Courier A', 'email' => 'courier.a@example.com', 'phone' => '08011112222', 'password' => bcrypt('test'), 'user_type' => 'rider', 'status' => 'active']);
        $riderA = RiderProfile::create(['user_id' => $riderUserA->id, 'logistics_company_id' => 1, 'assigned_lga_id' => $lga->id, 'is_active' => true, 'is_online' => true]);

        // Rider Beta (Attacker / Unassigned)
        $riderUserB = User::create(['name' => 'Courier B', 'email' => 'courier.b@example.com', 'phone' => '08033334444', 'password' => bcrypt('test'), 'user_type' => 'rider', 'status' => 'active']);
        $riderB = RiderProfile::create(['user_id' => $riderUserB->id, 'logistics_company_id' => 1, 'assigned_lga_id' => $lga->id, 'is_active' => true, 'is_online' => true]);

        // Assign to Courier A
        $order->rider_id = $riderA->id;
        $order->save();

        // Courier B tries to input the correct OTP (654321) on mobile
        $service = app(OrderSettlementService::class);
        $result = $service->verifyRiderPickupOtp($order, '654321', $riderB);

        $this->assertFalse($result['success']);
        $this->assertEquals('Unauthorized: This order is assigned to another dispatch courier.', $result['message']);

        // Courier A inputs OTP -> succeeds
        $resultA = $service->verifyRiderPickupOtp($order, '654321', $riderA);
        $this->assertTrue($resultA['success']);
        $order->refresh();
        $this->assertEquals('out_for_delivery', $order->order_status);
        $this->assertEquals($riderA->id, $order->rider_id);
    }

    /**
     * 2. Critical Finding Fix: Counter staff cannot process another shop's order.
     */
    public function test_counter_staff_cannot_process_another_shops_order(): void
    {
        [$vendorUserA, $vendorA, $shopA] = $this->createMerchantWithShop('Plaza Shop A', 'shop.a@example.com', '08011119999');
        [$vendorUserB, $vendorB, $shopB] = $this->createMerchantWithShop('Plaza Shop B', 'shop.b@example.com', '08022229999');
        $lga = Lga::where('name', 'Uyo')->first();

        // Staff assigned to Shop A
        $staffUserA = User::create(['name' => 'Counter Staff A', 'email' => 'counter.a@example.com', 'phone' => '08099001122', 'password' => bcrypt('test'), 'user_type' => 'vendor_employee', 'status' => 'active']);
        VendorEmployeeProfile::create(['user_id' => $staffUserA->id, 'vendor_id' => $vendorA->id, 'shop_id' => $shopA->id, 'role' => 'inspection_staff', 'is_active' => true]);

        // Order belonging to Shop B
        $customer = User::where('user_type', 'customer')->first();
        $orderB = Order::create([
            'order_number' => 'VM-SHOP-B-001',
            'customer_id' => $customer->id,
            'vendor_id' => $vendorB->id,
            'shop_id' => $shopB->id,
            'origin_lga_id' => $lga->id,
            'destination_lga_id' => $lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'ready_for_pickup',
            'vendor_handover_ready_at' => Carbon::now(),
            'pickup_verification_code' => '998877',
            'merchandise_subtotal' => 15000.00,
            'delivery_fee' => 1500.00,
            'cashback_redeemed' => 0.00,
            'grand_total' => 16500.00,
            'vendor_net_90' => 13500.00,
            'customer_cashback_5' => 750.00,
            'platform_margin_5' => 750.00,
            'logistics_fee_retained' => 1350.00,
            'platform_delivery_fee' => 150.00,
            'payment_status' => 'paid',
        ]);

        // Staff A tries to verify handover for Shop B's order
        $response = $this->actingAs($staffUserA)->post(route('counter.verify.handover.otp'), [
            'order_id' => $orderB->id,
            'pickup_otp' => '998877',
        ]);

        // Strict 404 ModelNotFound because order query is strictly scoped to shop_id: shopA
        $response->assertStatus(404);
    }

    /**
     * 3. Vendor Multi-Tenant IDOR Guard: Vendor cannot store products for another vendor's shop.
     */
    public function test_vendor_cannot_store_product_for_another_vendors_shop(): void
    {
        [$vendorUserA, $vendorA, $shopA] = $this->createMerchantWithShop('Merchant Alpha', 'alpha.merchant@example.com', '08077112233');
        [$vendorUserB, $vendorB, $shopB] = $this->createMerchantWithShop('Merchant Beta', 'beta.merchant@example.com', '08088112233');

        $category = Category::first();

        // Vendor A attempts to publish a product targeting Shop B's ID
        $response = $this->actingAs($vendorUserA)->post(route('vendor.products.store'), [
            'name' => 'Malicious Hijacked Product',
            'category_id' => $category->id,
            'shop_id' => $shopB->id, // Shop B does NOT belong to Vendor A
            'unit_price' => 5000.00,
            'inventory_quantity' => 10,
            'package_tier' => 'standard',
            'description' => 'Attempting cross-vendor product injection',
        ]);

        // ModelNotFound (404) due to Shop::where('vendor_id', $vendor->id)->findOrFail($shopId)
        $response->assertStatus(404);

        $this->assertDatabaseMissing('products', [
            'name' => 'Malicious Hijacked Product',
        ]);
    }

    /**
     * 4. Vendor Multi-Tenant IDOR Guard: Vendor cannot refresh freshness for another vendor's product.
     */
    public function test_vendor_cannot_refresh_another_vendors_product_freshness(): void
    {
        [$vendorUserA, $vendorA, $shopA] = $this->createMerchantWithShop('Alpha Store', 'alpha.fresh@example.com', '08066112233');
        [$vendorUserB, $vendorB, $shopB] = $this->createMerchantWithShop('Beta Store', 'beta.fresh@example.com', '08055112233');

        $category = Category::first();
        $productB = Product::create([
            'vendor_id' => $vendorB->id,
            'shop_id' => $shopB->id,
            'category_id' => $category->id,
            'name' => 'Beta Organic Honey',
            'slug' => 'beta-organic-honey',
            'unit_price' => 3500.00,
            'is_in_stock' => true,
            'inventory_quantity' => 20,
            'listing_status' => 'unlisted',
            'listing_confirmed_at' => Carbon::now()->subDays(10),
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        // Vendor A attempts to refresh Vendor B's product
        $response = $this->actingAs($vendorUserA)->post(route('vendor.products.refresh', $productB->id));

        // Strict 404 ModelNotFound because product query is scoped to vendor_id
        $response->assertStatus(404);

        $productB->refresh();
        $this->assertEquals('unlisted', $productB->listing_status);
    }

    /**
     * 5. Stock Correctness: Checkout rejects purchase when inventory quantity is insufficient.
     */
    public function test_checkout_strictly_rejects_insufficient_stock(): void
    {
        $customer = User::where('user_type', 'customer')->first();
        $product = Product::first();
        $product->inventory_quantity = 2;
        $product->save();

        // Customer attempts to purchase 5 units (when only 2 in stock)
        $response = $this->actingAs($customer)->post(route('checkout.process'), [
            'product_id' => $product->id,
            'quantity' => 5, // Exceeds available stock
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => 'John Doe',
            'customer_phone' => '08099887766',
            'destination_lga_id' => $product->shop->lga_id,
            'delivery_address' => '54 Abak Road, Uyo',
        ]);

        $response->assertSessionHasErrors(['quantity']);
        $product->refresh();
        $this->assertEquals(2, $product->inventory_quantity); // Stock remains un-decremented
    }

    /**
     * 6. Account & Order Privacy: Unauthorized user cannot view another customer's order tracking.
     */
    public function test_order_status_strictly_blocks_unauthorized_user(): void
    {
        $customerA = User::create(['name' => 'Customer A', 'email' => 'cust.a@test.com', 'phone' => '08011113333', 'password' => bcrypt('test'), 'user_type' => 'customer', 'status' => 'active']);
        $customerB = User::create(['name' => 'Customer B', 'email' => 'cust.b@test.com', 'phone' => '08022223333', 'password' => bcrypt('test'), 'user_type' => 'customer', 'status' => 'active']);

        $product = Product::first();
        $orderA = Order::create([
            'order_number' => 'ORD-PRIVACY-001',
            'customer_id' => $customerA->id,
            'vendor_id' => $product->vendor_id,
            'shop_id' => $product->shop_id,
            'origin_lga_id' => $product->shop->lga_id,
            'destination_lga_id' => $product->shop->lga_id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'guest_access_token' => bin2hex(random_bytes(32)),
            'verification_code' => '334455',
            'pickup_verification_code' => '667788',
            'merchandise_subtotal' => 10000.00,
            'delivery_fee' => 1000.00,
            'cashback_redeemed' => 0.00,
            'grand_total' => 11000.00,
            'vendor_net_90' => 9000.00,
            'customer_cashback_5' => 500.00,
            'platform_margin_5' => 500.00,
            'logistics_fee_retained' => 900.00,
            'platform_delivery_fee' => 100.00,
        ]);

        // Customer B attempts to spy on Customer A's order status page
        $response = $this->actingAs($customerB)->get(route('order.status', $orderA->order_number));

        // Blocked with 403 Forbidden
        $response->assertStatus(403);

        // Customer A views their own order -> 200 OK
        $responseA = $this->actingAs($customerA)->get(route('order.status', $orderA->order_number));
        $responseA->assertStatus(200);

        // Guest with valid token in URL -> 200 OK
        $responseGuest = $this->get(route('order.status', ['order_number' => $orderA->order_number, 'token' => $orderA->guest_access_token]));
        $responseGuest->assertStatus(200);
    }

    /**
     * 7. Banking Hardening: Vendor bank update validates 10-digit NUBAN, sets 48h cooldown, and audits.
     */
    public function test_vendor_bank_update_activates_48h_cooldown_and_validates_10_digit_nuban(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Bank Vendor', 'bank.vendor@example.com', '08033221144');

        // Invalid 8-digit NUBAN
        $responseInvalid = $this->actingAs($vendorUser)->post(route('vendor.bank.update'), [
            'bank_name' => 'Access Bank',
            'account_number' => '12345678', // Invalid
            'account_name' => 'Bank Vendor Enterprises',
        ]);
        $responseInvalid->assertSessionHasErrors(['account_number']);

        // Valid 10-digit NUBAN
        $responseValid = $this->actingAs($vendorUser)->post(route('vendor.bank.update'), [
            'bank_name' => 'Access Bank PLC',
            'account_number' => '0123456789', // Valid 10 digits
            'account_name' => 'Bank Vendor Enterprises',
        ]);
        $responseValid->assertSessionHasNoErrors();
        $responseValid->assertRedirect();

        $vendor->refresh();
        $this->assertEquals('0123456789', $vendor->account_number);
        $this->assertEquals('Access Bank PLC', $vendor->bank_name);
        $this->assertNotNull($vendor->bank_updated_at);
        $this->assertTrue($vendor->isBankCooldownActive());

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'vendor_bank_account_updated',
        ]);
    }

    /**
     * 8. OTP Anti-Brute-Force Shield: 5 consecutive incorrect delivery OTPs lock verification for 15 minutes.
     */
    public function test_customer_delivery_otp_brute_force_shield_locks_after_5_failures(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Delivery Vendor', 'deliv.vendor@example.com', '08055443322');
        $customer = User::where('user_type', 'customer')->first();
        $lga = Lga::first();

        $order = Order::create([
            'order_number' => 'VM-LOCKOUT-001',
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'origin_lga_id' => $lga->id,
            'destination_lga_id' => $lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'verification_code' => '888999',
            'delivery_failed_attempts' => 0,
            'merchandise_subtotal' => 10000.00,
            'delivery_fee' => 1000.00,
            'grand_total' => 11000.00,
            'vendor_net_90' => 9000.00,
            'customer_cashback_5' => 500.00,
            'platform_margin_5' => 500.00,
            'logistics_fee_retained' => 900.00,
            'platform_delivery_fee' => 100.00,
            'payment_status' => 'paid',
        ]);

        $service = app(OrderSettlementService::class);

        // 4 failed attempts
        for ($i = 1; $i <= 4; $i++) {
            $res = $service->verifyCustomerDeliveryOtp($order, '000000');
            $this->assertFalse($res['success']);
            $order->refresh();
            $this->assertEquals($i, $order->delivery_failed_attempts);
            $this->assertFalse($order->isDeliveryLocked());
        }

        // 5th failed attempt triggers 15-minute lock
        $res5 = $service->verifyCustomerDeliveryOtp($order, '000000');
        $this->assertFalse($res5['success']);
        $this->assertStringContainsString('locked for 15 minutes', $res5['message']);

        $order->refresh();
        $this->assertTrue($order->isDeliveryLocked());
        $this->assertNotNull($order->delivery_locked_until);

        // 6th attempt with CORRECT OTP is blocked due to active lock
        $resLocked = $service->verifyCustomerDeliveryOtp($order, '888999');
        $this->assertFalse($resLocked['success']);
        $this->assertStringContainsString('Delivery verification locked', $resLocked['message']);
        $this->assertEquals('out_for_delivery', $order->order_status);
    }

    /**
     * 9. Multi-Tenant Shop Isolation: Checkout process strictly rejects mismatch between product vendor and shop.
     */
    public function test_checkout_strictly_rejects_cross_vendor_shop_mismatch(): void
    {
        [$vendorUserA, $vendorA, $shopA] = $this->createMerchantWithShop('Store Alpha', 'store.a@example.com', '08012345671');
        [$vendorUserB, $vendorB, $shopB] = $this->createMerchantWithShop('Store Beta', 'store.b@example.com', '08012345672');

        $category = Category::first();
        $productA = Product::create([
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'category_id' => $category->id,
            'name' => 'Alpha Special Bread',
            'slug' => 'alpha-special-bread',
            'unit_price' => 2000.00,
            'is_in_stock' => true,
            'inventory_quantity' => 15,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        $customer = User::where('user_type', 'customer')->first();

        // Customer attempts checkout with Product from Vendor A, but specifies Shop from Vendor B
        $response = $this->actingAs($customer)->post(route('checkout.process'), [
            'product_id' => $productA->id,
            'shop_id' => $shopB->id, // Mismatched shop belonging to Vendor B
            'quantity' => 1,
            'fulfillment_type' => 'in_shop_pickup',
            'customer_name' => 'Test Customer',
            'customer_phone' => '08099887766',
        ]);

        // ModelNotFound (404) because Shop::where('vendor_id', $product->vendor_id)->findOrFail(shopB) fails
        $response->assertStatus(404);
    }

    /**
     * 10. Public Registration Hardening: API register forces customer role even if vendor/rider is passed.
     */
    public function test_api_registration_strictly_blocks_privilege_escalation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Injected Vendor Attempt',
            'email' => 'escalate@example.com',
            'phone' => '08019998877',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'user_type' => 'super_admin', // Malicious attempt to escalate to super_admin
        ]);

        $response->assertStatus(201);
        $user = User::where('email', 'escalate@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('customer', $user->user_type);
        $this->assertNotEquals('super_admin', $user->user_type);
    }

    /**
     * 11. Vendor Update IDOR Guard: Vendor cannot update another vendor's product or shop.
     */
    public function test_vendor_cannot_update_another_vendors_product_or_shop(): void
    {
        [$vendorUserA, $vendorA, $shopA] = $this->createMerchantWithShop('Merchant One', 'one@example.com', '08066778811');
        [$vendorUserB, $vendorB, $shopB] = $this->createMerchantWithShop('Merchant Two', 'two@example.com', '08066778822');

        $category = Category::first();
        $productB = Product::create([
            'vendor_id' => $vendorB->id,
            'shop_id' => $shopB->id,
            'category_id' => $category->id,
            'name' => 'Merchant Two Product',
            'slug' => 'merchant-two-product',
            'unit_price' => 3000.00,
            'is_in_stock' => true,
            'inventory_quantity' => 10,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'is_approved' => true,
            'package_tier' => 'standard',
        ]);

        // Vendor A attempts to update Vendor B's product
        $responseProd = $this->actingAs($vendorUserA)->post(route('vendor.products.update', $productB->id), [
            'name' => 'Hijacked Title',
            'category_id' => $category->id,
            'shop_id' => $shopA->id,
            'unit_price' => 1000.00,
            'inventory_quantity' => 5,
            'package_tier' => 'standard',
        ]);
        $responseProd->assertStatus(404);

        // Vendor A attempts to update Vendor B's shop branch
        $responseShop = $this->actingAs($vendorUserA)->post(route('vendor.shops.update', $shopB->id), [
            'name' => 'Hijacked Shop Name',
            'address' => 'Fake Address',
            'lga_id' => $shopA->lga_id,
            'phone' => '08012345678',
        ]);
        $responseShop->assertStatus(404);
    }

    /**
     * 12. Super Admin Hardening: Settlement disbursement blocked when merchant bank cooldown is active.
     */
    public function test_super_admin_settlement_disbursement_blocked_when_vendor_bank_cooldown_active(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Cooldown Merchant', 'cooldown@example.com', '08099887711');
        $vendor->bank_updated_at = Carbon::now()->subHours(2); // In 48h cooldown
        $vendor->save();

        $admin = User::where('user_type', 'super_admin')->first();
        $customer = User::where('user_type', 'customer')->first();
        $lga = Lga::where('name', 'Uyo')->first();

        $order = Order::create([
            'order_number' => 'VM-SEC-SETTLE-01',
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'origin_lga_id' => $lga->id,
            'destination_lga_id' => $lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'merchandise_subtotal' => 10000.00,
            'delivery_fee' => 1000.00,
            'cashback_redeemed' => 0.00,
            'grand_total' => 11000.00,
            'vendor_net_90' => 9000.00,
            'customer_cashback_5' => 500.00,
            'platform_margin_5' => 500.00,
            'logistics_fee_retained' => 900.00,
            'platform_delivery_fee' => 100.00,
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'eligible',
        ]);

        $service = app(OrderSettlementService::class);
        $result = $service->disburseVendorSettlement($order, $admin, 'storage/payouts/proof.png');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('48-hour security cooldown', $result['message']);

        $order->refresh();
        $this->assertEquals('eligible', $order->vendor_settlement_status);
        $this->assertNull($order->settled_at);
    }

    /**
     * 13. Super Admin Hardening: Non-admin or inactive admin cannot disburse vendor settlements.
     */
    public function test_super_admin_settlement_disbursement_blocked_for_inactive_admin_or_non_admin(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('NonAdmin Merchant', 'nonadmin@example.com', '08099887722');
        $customer = User::where('user_type', 'customer')->first();
        $lga = Lga::where('name', 'Uyo')->first();

        $order = Order::create([
            'order_number' => 'VM-SEC-SETTLE-02',
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'origin_lga_id' => $lga->id,
            'destination_lga_id' => $lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'merchandise_subtotal' => 10000.00,
            'delivery_fee' => 1000.00,
            'cashback_redeemed' => 0.00,
            'grand_total' => 11000.00,
            'vendor_net_90' => 9000.00,
            'customer_cashback_5' => 500.00,
            'platform_margin_5' => 500.00,
            'logistics_fee_retained' => 900.00,
            'platform_delivery_fee' => 100.00,
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'eligible',
        ]);

        $service = app(OrderSettlementService::class);

        // A. Customer attempting disbursement
        $resultCustomer = $service->disburseVendorSettlement($order, $customer, 'storage/payouts/proof.png');
        $this->assertFalse($resultCustomer['success']);
        $this->assertStringContainsString('privileges', $resultCustomer['message']);

        // B. Inactive Admin attempting disbursement
        $admin = User::where('user_type', 'super_admin')->first();
        $admin->status = 'suspended';
        $admin->save();

        $resultInactive = $service->disburseVendorSettlement($order, $admin, 'storage/payouts/proof.png');
        $this->assertFalse($resultInactive['success']);
        $this->assertStringContainsString('inactive', $resultInactive['message']);
    }

    /**
     * 14. Super Admin Hardening: Valid disbursement succeeds and writes immutable AdminAuditLog.
     */
    public function test_super_admin_settlement_disbursement_succeeds_and_creates_audit_log_when_cooldown_expired(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Disburse Merchant', 'disburse@example.com', '08099887733');
        $vendor->bank_updated_at = Carbon::now()->subHours(72); // Cooldown expired
        $vendor->save();

        $admin = User::where('user_type', 'super_admin')->first();
        $customer = User::where('user_type', 'customer')->first();
        $lga = Lga::where('name', 'Uyo')->first();

        $order = Order::create([
            'order_number' => 'VM-SEC-SETTLE-03',
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'origin_lga_id' => $lga->id,
            'destination_lga_id' => $lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'merchandise_subtotal' => 10000.00,
            'delivery_fee' => 1000.00,
            'cashback_redeemed' => 0.00,
            'grand_total' => 11000.00,
            'vendor_net_90' => 9000.00,
            'customer_cashback_5' => 500.00,
            'platform_margin_5' => 500.00,
            'logistics_fee_retained' => 900.00,
            'platform_delivery_fee' => 100.00,
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'eligible',
        ]);

        $service = app(OrderSettlementService::class);
        $result = $service->disburseVendorSettlement($order, $admin, 'storage/payouts/proof_VM-SEC-SETTLE-03.png');

        $this->assertTrue($result['success']);
        $order->refresh();
        $this->assertEquals('settled', $order->vendor_settlement_status);
        $this->assertNotNull($order->settled_at);

        // Verify Immutable Audit Log
        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'vendor_settlement_disbursed',
            'target_type' => Order::class,
            'target_id' => $order->id,
        ]);
    }

    /**
     * 15. Super Admin Hardening: Corporate logistics payout blocked when fleet bank cooldown is active.
     */
    public function test_super_admin_logistics_payout_disbursement_blocked_when_fleet_bank_cooldown_active(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        $company = LogisticsCompanyProfile::first();
        $company->bank_updated_at = Carbon::now()->subHours(10); // Under 48h cooldown
        $company->save();

        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $company->id,
            'amount' => 15000.00,
            'status' => 'pending',
            'bank_name' => $company->bank_name ?? 'First Bank',
            'account_number' => $company->account_number ?? '0123456789',
            'account_name' => $company->account_name ?? 'Fleet Corp',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.disburse.logistics_payout'), [
            'payout_id' => $payout->id,
            'notes' => 'Corporate disbursement attempt during cooldown',
        ]);

        $response->assertSessionHas('error');
        $payout->refresh();
        $this->assertEquals('pending', $payout->status);
        $this->assertNull($payout->disbursed_at);
    }

    /**
     * 16. Super Admin Hardening: Vendor KYC accreditation audits action in AdminAuditLog.
     */
    public function test_super_admin_vendor_accreditation_creates_immutable_audit_log(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Audit Merchant', 'auditmerch@example.com', '08099887744');

        $response = $this->actingAs($admin)->post(route('admin.vendors.accredit', $vendor->id), [
            'action' => 'suspend',
        ]);

        $response->assertRedirect(route('admin.merchants.index'));
        $vendor->refresh();
        $this->assertEquals('suspended', $vendor->marketplace_status);

        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'vendor_kyc_accreditation',
            'target_type' => VendorProfile::class,
            'target_id' => $vendor->id,
        ]);
    }

    /**
     * 17. Super Admin Hardening: Storefront banner creation sanitizes XSS tags and audits action.
     */
    public function test_super_admin_banner_creation_sanitizes_xss_and_creates_audit_log(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();

        $response = $this->actingAs($admin)->post(route('admin.banners.store'), [
            'banner_type' => 'hero_slider',
            'template_style' => 'luxury_split',
            'title' => '<script>alert("xss")</script>Akwa Ibom Heritage Festival',
            'subtitle' => '<b>Verified Authentic Local Crafts</b>',
            'badge_text' => '<img src=x onerror=alert(1)>HERITAGE 2026',
            'sort_order' => 1,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.banners.index'));

        $banner = Banner::where('banner_type', 'hero_slider')->latest('id')->first();
        $this->assertNotNull($banner);
        $this->assertEquals('Akwa Ibom Heritage Festival', $banner->title);
        $this->assertEquals('Verified Authentic Local Crafts', $banner->subtitle);
        $this->assertEquals('HERITAGE 2026', $banner->badge_text);

        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'admin_banner_created',
            'target_type' => Banner::class,
            'target_id' => $banner->id,
        ]);
    }

    /**
     * 18. Super Admin Hardening: Command Gate login writes audit trail to AdminAuditLog.
     */
    public function test_super_admin_command_gate_login_records_audit_trail(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();

        $response = $this->post(route('admin.login.post'), [
            'login' => $admin->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'super_admin_login_success',
            'target_type' => User::class,
            'target_id' => $admin->id,
        ]);
    }

    /**
     * 19. Role Hardening (admin_employee): Admin staff login records audit log and grants staff portal access.
     */
    public function test_admin_staff_login_records_audit_trail(): void
    {
        $staff = User::where('user_type', 'admin_employee')->first();
        if (!$staff) {
            $staff = User::create([
                'name' => 'Compliance Staff Member',
                'email' => 'compliance@vmarket.ng',
                'phone' => '08022334455',
                'password' => bcrypt('Password123!'),
                'user_type' => 'admin_employee',
                'status' => 'active',
            ]);
        }

        $response = $this->post(route('admin.staff.login.post'), [
            'login' => $staff->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('admin.staff.dashboard'));

        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $staff->id,
            'action' => 'admin_staff_login_success',
            'target_type' => User::class,
            'target_id' => $staff->id,
        ]);
    }

    /**
     * 20. Role Hardening (vendor): Suspended vendor is blocked from login.
     */
    public function test_suspended_vendor_cannot_login(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Suspended Vendor', 'suspended_vendor@example.com', '08099887711');
        $vendor->marketplace_status = 'suspended';
        $vendor->save();

        $response = $this->post(route('vendor.login.post'), [
            'login' => $vendorUser->email,
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['login']);
        $this->assertGuest();
    }

    /**
     * 21. Role Hardening (vendor_employee): Counter staff is blocked if parent vendor organization is suspended.
     */
    public function test_counter_employee_login_blocked_when_parent_vendor_is_suspended(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Parent Suspended Vendor', 'parent_susp@example.com', '08099887722');
        $vendor->marketplace_status = 'suspended';
        $vendor->save();

        $staffUser = User::create([
            'name' => 'Counter Clerk',
            'email' => 'counter_clerk@example.com',
            'phone' => '08099887733',
            'password' => bcrypt('Password123!'),
            'user_type' => 'vendor_employee',
            'status' => 'active',
        ]);

        VendorEmployeeProfile::create([
            'user_id' => $staffUser->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'is_active' => true,
        ]);

        $response = $this->post(route('counter.login.post'), [
            'login' => $staffUser->email,
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['login']);
        $this->assertGuest();
    }

    /**
     * 22. Role Hardening (vendor_employee): Active counter staff session is terminated if parent vendor is suspended.
     */
    public function test_counter_employee_middleware_terminates_session_when_parent_vendor_suspended(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Parent Suspended Mid', 'parent_mid@example.com', '08099887744');
        $vendor->marketplace_status = 'suspended';
        $vendor->save();

        $staffUser = User::create([
            'name' => 'Counter Clerk Mid',
            'email' => 'counter_mid@example.com',
            'phone' => '08099887755',
            'password' => bcrypt('Password123!'),
            'user_type' => 'vendor_employee',
            'status' => 'active',
        ]);

        VendorEmployeeProfile::create([
            'user_id' => $staffUser->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($staffUser)->get(route('counter.dashboard'));
        $response->assertRedirect(route('counter.login'));
        $this->assertGuest();
    }

    /**
     * 23. Role Hardening (logistics_company): Inactive logistics company cannot log in or request payouts.
     */
    public function test_suspended_logistics_company_cannot_login_and_cannot_request_payout(): void
    {
        $logisticsUser = User::create([
            'name' => 'Suspended Fleet Logistics',
            'email' => 'suspended_fleet@example.com',
            'phone' => '08099887766',
            'password' => bcrypt('Password123!'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $company = LogisticsCompanyProfile::create([
            'user_id' => $logisticsUser->id,
            'company_name' => 'Suspended Fleet Logistics Ltd',
            'hq_lga_id' => 1,
            'is_active' => false,
            'status' => 'suspended',
            'wallet_balance' => '50000.00',
            'bank_name' => 'Access Bank PLC',
            'account_number' => '0123456789',
            'account_name' => 'Suspended Fleet Logistics Ltd',
        ]);

        // Attempt login -> must be rejected
        $response = $this->post(route('logistics.login.post'), [
            'login' => $logisticsUser->email,
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['login']);
        $this->assertGuest();

        // Attempt direct payout request if authenticated -> must be rejected
        $payoutResponse = $this->actingAs($logisticsUser)->post(route('logistics.payout.request'), [
            'amount' => 10000,
        ]);
        $payoutResponse->assertSessionHas('error');
        $this->assertEquals(0, LogisticsPayoutRequest::where('logistics_company_id', $company->id)->count());
    }

    /**
     * 24. Role Hardening (rider): Courier cannot log in or go on duty if parent logistics company is suspended.
     */
    public function test_rider_blocked_when_parent_logistics_company_is_suspended(): void
    {
        $fleetUser = User::create([
            'name' => 'Parent Fleet HQ',
            'email' => 'parent_fleet_hq@example.com',
            'phone' => '08099887777',
            'password' => bcrypt('Password123!'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $company = LogisticsCompanyProfile::create([
            'user_id' => $fleetUser->id,
            'company_name' => 'Parent Fleet HQ Ltd',
            'hq_lga_id' => 1,
            'is_active' => false,
            'status' => 'suspended',
        ]);

        $riderUser = User::create([
            'name' => 'Courier Dan',
            'email' => 'courier_dan@example.com',
            'phone' => '08099887788',
            'password' => bcrypt('Password123!'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $rider = RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => $company->id,
            'assigned_lga_id' => 1,
            'vehicle_type' => 'motorcycle',
            'vehicle_plate_number' => 'UYO-555-AK',
            'is_active' => true,
            'is_online' => false,
        ]);

        // Login blocked
        $response = $this->post(route('rider.login.post'), [
            'login' => $riderUser->email,
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors(['login']);
        $this->assertGuest();

        // Middleware terminates session on request
        $shiftResponse = $this->actingAs($riderUser)->post(route('rider.toggle.shift'));
        $shiftResponse->assertRedirect(route('rider.login'));
        $this->assertGuest();
    }

    /**
     * 25. Role Hardening (customer): Unrelated user cannot view another customer's reservation pass.
     */
    public function test_unrelated_user_cannot_view_another_customer_reservation_pass(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Pass Merchant', 'pass_merchant@example.com', '08099887799');
        $customer = User::where('user_type', 'customer')->first();
        $unrelatedCustomer = User::create([
            'name' => 'Snooping Customer',
            'email' => 'snooper@example.com',
            'phone' => '08011223344',
            'password' => bcrypt('Password123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $category = Category::first();
        $product = Product::create([
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Exclusive Smartphone',
            'slug' => 'exclusive-smartphone',
            'description' => 'Flagship inspection',
            'unit_price' => '150000.00',
            'purchase_price' => '120000.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        $reservation = \App\Models\PickupReservation::create([
            'reservation_code' => 'RES-PASS-PRIV-01',
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '150000.00',
            'estimated_total' => '150000.00',
            'status' => 'reserved',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        // Unrelated customer attempts to view pass -> 403 Forbidden
        $response = $this->actingAs($unrelatedCustomer)->get(route('reservation.pass', ['code' => $reservation->reservation_code]));
        $response->assertStatus(403);

        // Actual owner customer can view pass -> 200 OK
        $ownerResponse = $this->actingAs($customer)->get(route('reservation.pass', ['code' => $reservation->reservation_code]));
        $ownerResponse->assertStatus(200);
    }
}
