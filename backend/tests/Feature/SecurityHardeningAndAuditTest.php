<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
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
}
