<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Country;
use App\Models\Lga;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\SourcingAllocationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AutomatedSupplierSourcingTest extends TestCase
{
    use RefreshDatabase;

    protected State $state;
    protected Country $country;
    protected Lga $uyoLga;
    protected Lga $eketLga;
    protected User $adminUser;
    protected User $customerUser;
    protected Category $category;
    protected SourcingAllocationService $sourcingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = Country::firstOrCreate(
            ['iso_code' => 'NG'],
            ['name' => 'Nigeria', 'currency_code' => 'NGN', 'phone_code' => '+234', 'is_active' => true]
        );

        $this->state = State::create([
            'country_id' => $this->country->id,
            'name' => 'Akwa Ibom',
            'code' => 'AK',
            'is_active' => true,
        ]);

        $this->uyoLga = Lga::create([
            'state_id' => $this->state->id,
            'name' => 'Uyo',
            'is_active' => true,
        ]);

        $this->eketLga = Lga::create([
            'state_id' => $this->state->id,
            'name' => 'Eket',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@victoriousmarket.ng',
            'phone' => '08000000001',
            'user_type' => 'super_admin',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);

        $this->customerUser = User::create([
            'name' => 'Customer Jane',
            'email' => 'jane@example.com',
            'phone' => '08012345678',
            'user_type' => 'customer',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);

        $this->category = Category::create([
            'name' => 'Food & Groceries',
            'slug' => 'food-groceries',
            'is_active' => true,
        ]);

        $this->sourcingService = new SourcingAllocationService();
    }

    protected function createSupplier(string $name, string $email, string $phone, ?Lga $lga = null): array
    {
        $lga = $lga ?? $this->uyoLga;

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'user_type' => 'vendor',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);

        $vendor = VendorProfile::create([
            'user_id' => $user->id,
            'business_name' => $name . ' Enterprises',
            'marketplace_status' => 'active',
        ]);

        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'country_id' => $this->country->id,
            'name' => $name . ' Main Branch',
            'slug' => \Illuminate\Support\Str::slug($name . '-main-branch-' . uniqid()),
            'state_id' => $this->state->id,
            'lga_id' => $lga->id,
            'address' => '123 Market Road, ' . $lga->name,
            'is_active' => true,
            'is_verified' => true,
        ]);

        return [$user, $vendor, $shop];
    }

    public function test_cheapest_available_supplier_offer_is_selected_when_multiple_offers_exist(): void
    {
        [$userA, $vendorA, $shopA] = $this->createSupplier('Supplier Alpha', 'alpha@supplier.ng', '08011111111');
        [$userB, $vendorB, $shopB] = $this->createSupplier('Supplier Beta', 'beta@supplier.ng', '08022222222');

        $product = Product::create([
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'category_id' => $this->category->id,
            'name' => 'Premium Rice 50kg',
            'slug' => 'premium-rice-50kg',
            'unit_price' => '75000.00',
            'cost_price' => '65000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 20,
            'status' => 'approved',
            'freshness_confirmed_at' => Carbon::now(),
        ]);

        // Supplier A offers wholesale at ₦64,000
        SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'wholesale_cost' => '64000.00',
            'is_available' => true,
        ]);

        // Supplier B offers wholesale cheaper at ₦61,500
        $cheaperOffer = SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $vendorB->id,
            'shop_id' => $shopB->id,
            'wholesale_cost' => '61500.00',
            'is_available' => true,
        ]);

        $resolved = $this->sourcingService->resolveCheapestOffer($product);

        $this->assertNotNull($resolved);
        $this->assertEquals($cheaperOffer->id, $resolved['supplier_offer_id']);
        $this->assertEquals($vendorB->id, $resolved['vendor_id']);
        $this->assertEquals($shopB->id, $resolved['shop_id']);
        $this->assertEquals('61500.00', $resolved['wholesale_cost']);
        $this->assertEquals('supplier_offer', $resolved['source_type']);
    }

    public function test_unavailable_or_inactive_supplier_offers_are_excluded(): void
    {
        [$userA, $vendorA, $shopA] = $this->createSupplier('Supplier Alpha', 'alpha2@supplier.ng', '08033333333');
        [$userB, $vendorB, $shopB] = $this->createSupplier('Supplier Beta', 'beta2@supplier.ng', '08044444444');
        [$userC, $vendorC, $shopC] = $this->createSupplier('Supplier Gamma', 'gamma2@supplier.ng', '08055555555');

        $product = Product::create([
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'category_id' => $this->category->id,
            'name' => 'Vegetable Oil 25L',
            'slug' => 'veg-oil-25l',
            'unit_price' => '45000.00',
            'cost_price' => '40000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 15,
            'status' => 'approved',
            'freshness_confirmed_at' => Carbon::now(),
        ]);

        // Supplier A is cheapest (₦35,000) but currently marked unavailable (out of stock)
        SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'wholesale_cost' => '35000.00',
            'is_available' => false,
        ]);

        // Supplier B offers at ₦37,000 but supplier user account is suspended
        $userB->status = 'suspended';
        $userB->save();

        SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $vendorB->id,
            'shop_id' => $shopB->id,
            'wholesale_cost' => '37000.00',
            'is_available' => true,
        ]);

        // Supplier C offers at ₦39,000 and is fully active and available
        $validOffer = SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $vendorC->id,
            'shop_id' => $shopC->id,
            'wholesale_cost' => '39000.00',
            'is_available' => true,
        ]);

        $resolved = $this->sourcingService->resolveCheapestOffer($product);

        $this->assertNotNull($resolved);
        $this->assertEquals($validOffer->id, $resolved['supplier_offer_id']);
        $this->assertEquals($vendorC->id, $resolved['vendor_id']);
        $this->assertEquals('39000.00', $resolved['wholesale_cost']);
    }

    public function test_catalog_fallback_when_no_supplier_offers_exist(): void
    {
        [$userA, $vendorA, $shopA] = $this->createSupplier('Supplier Alpha', 'alpha3@supplier.ng', '08066666666');

        $product = Product::create([
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'category_id' => $this->category->id,
            'name' => 'Cassava Flour 10kg',
            'slug' => 'cassava-flour-10kg',
            'unit_price' => '12000.00',
            'cost_price' => '9500.00',
            'is_in_stock' => true,
            'inventory_quantity' => 10,
            'status' => 'approved',
            'freshness_confirmed_at' => Carbon::now(),
        ]);

        // Zero rows in supplier_offers table for this product
        $resolved = $this->sourcingService->resolveCheapestOffer($product);

        $this->assertNotNull($resolved);
        $this->assertNull($resolved['supplier_offer_id']);
        $this->assertEquals($vendorA->id, $resolved['vendor_id']);
        $this->assertEquals($shopA->id, $resolved['shop_id']);
        $this->assertEquals('9500.00', $resolved['wholesale_cost']);
        $this->assertEquals('catalog_fallback', $resolved['source_type']);
    }

    public function test_tie_breaking_prioritizes_preferred_lga_deterministically(): void
    {
        [$userA, $vendorA, $shopA] = $this->createSupplier('Supplier Uyo', 'uyo@supplier.ng', '08077777771', $this->uyoLga);
        [$userB, $vendorB, $shopB] = $this->createSupplier('Supplier Eket', 'eket@supplier.ng', '08077777772', $this->eketLga);

        $product = Product::create([
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'category_id' => $this->category->id,
            'name' => 'Yam Tubers (Set of 5)',
            'slug' => 'yam-tubers-5',
            'unit_price' => '20000.00',
            'cost_price' => '16000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 50,
            'status' => 'approved',
            'freshness_confirmed_at' => Carbon::now(),
        ]);

        // Both offer identical wholesale price of ₦16,000
        $offerA = SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'wholesale_cost' => '16000.00',
            'is_available' => true,
        ]);

        $offerB = SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $vendorB->id,
            'shop_id' => $shopB->id,
            'wholesale_cost' => '16000.00',
            'is_available' => true,
        ]);

        // Customer in Eket prefers Eket LGA
        $resolvedEket = $this->sourcingService->resolveCheapestOffer($product, 1, $this->eketLga->id);
        $this->assertEquals($offerB->id, $resolvedEket['supplier_offer_id']);
        $this->assertEquals($shopB->id, $resolvedEket['shop_id']);

        // Customer in Uyo prefers Uyo LGA
        $resolvedUyo = $this->sourcingService->resolveCheapestOffer($product, 1, $this->uyoLga->id);
        $this->assertEquals($offerA->id, $resolvedUyo['supplier_offer_id']);
        $this->assertEquals($shopA->id, $resolvedUyo['shop_id']);
    }

    protected function createOrderFixture(array $attributes = []): Order
    {
        $splits = Order::computeSplits($attributes['merchandise_subtotal'] ?? '10000.00', $attributes['delivery_fee'] ?? '0.00');

        return Order::create(array_merge([
            'order_number' => 'VM-TEST-' . uniqid(),
            'customer_id' => $this->customerUser->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'merchandise_subtotal' => '10000.00',
            'grand_total' => '10000.00',
            'delivery_fee' => '0.00',
            'cashback_redeemed' => '0.00',
            'wallet_credit_applied' => '0.00',
            'vendor_net_90' => $splits['vendor_net_90'],
            'customer_cashback_5' => $splits['customer_cashback_5'],
            'platform_margin_5' => $splits['platform_margin_5'],
            'logistics_fee_retained' => $splits['logistics_net_90'],
            'platform_delivery_fee' => $splits['platform_delivery_fee_10'],
            'verification_code' => Order::generateUniqueDeliveryOtp(),
            'pickup_verification_code' => Order::generateUniquePickupOtp(),
            'sourcing_status' => 'assigned_to_supplier',
            'sourced_at' => Carbon::now(),
        ], $attributes));
    }

    public function test_order_item_snapshot_persists_immutable_supplier_cost_and_identity(): void
    {
        [$userA, $vendorA, $shopA] = $this->createSupplier('Supplier Alpha', 'alpha4@supplier.ng', '08088888888');

        $product = Product::create([
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'category_id' => $this->category->id,
            'name' => 'Pure Honey 1L',
            'slug' => 'pure-honey-1l',
            'unit_price' => '8000.00',
            'cost_price' => '6000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 10,
            'status' => 'approved',
            'freshness_confirmed_at' => Carbon::now(),
        ]);

        $offer = SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'wholesale_cost' => '5800.00',
            'is_available' => true,
        ]);

        $order = $this->createOrderFixture([
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'merchandise_subtotal' => '8000.00',
            'grand_total' => '8000.00',
            'selected_supplier_id' => $vendorA->id,
            'selected_shop_id' => $shopA->id,
        ]);

        $resolved = $this->sourcingService->resolveCheapestOffer($product);
        $snapshot = $this->sourcingService->buildOrderItemSnapshot($resolved, $product, 1, '8000.00', '8000.00');
        $snapshot['order_id'] = $order->id;

        $orderItem = OrderItem::create($snapshot);

        $this->assertEquals($offer->id, $orderItem->supplier_offer_id);
        $this->assertEquals('5800.00', $orderItem->supplier_cost_snapshot);
        $this->assertEquals('5800.00', $orderItem->cost_price);
        $this->assertEquals($vendorA->id, $orderItem->supplier_vendor_id);
        $this->assertEquals($shopA->id, $orderItem->supplier_shop_id);

        // Later: supplier raises their wholesale cost on the offer
        $offer->wholesale_cost = '7200.00';
        $offer->save();

        // The historical OrderItem snapshot remains immutable!
        $refreshedItem = $orderItem->fresh();
        $this->assertEquals('5800.00', $refreshedItem->supplier_cost_snapshot);
        $this->assertEquals('5800.00', $refreshedItem->cost_price);
    }

    public function test_admin_override_reassigns_order_supplier_and_creates_audit_log(): void
    {
        [$userA, $vendorA, $shopA] = $this->createSupplier('Supplier Alpha', 'alpha5@supplier.ng', '08099999991');
        [$userB, $vendorB, $shopB] = $this->createSupplier('Supplier Beta', 'beta5@supplier.ng', '08099999992');

        $order = $this->createOrderFixture([
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'selected_supplier_id' => $vendorA->id,
            'selected_shop_id' => $shopA->id,
        ]);

        $this->sourcingService->overrideSupplierAssignment(
            $order,
            $vendorB->id,
            $shopB->id,
            $this->adminUser,
            'Supplier A out of stock at physical warehouse'
        );

        $order->refresh();
        $this->assertEquals($vendorB->id, $order->selected_supplier_id);
        $this->assertEquals($shopB->id, $order->selected_shop_id);
        $this->assertEquals('admin_reassigned', $order->sourcing_status);
        $this->assertStringContainsString('Supplier A out of stock', $order->sourcing_notes);

        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'admin_override_supplier_sourcing',
            'target_type' => Order::class,
            'target_id' => $order->id,
        ]);
    }

    public function test_non_admin_cannot_override_supplier_assignment(): void
    {
        [$userA, $vendorA, $shopA] = $this->createSupplier('Supplier Alpha', 'alpha6@supplier.ng', '08012341234');
        [$userB, $vendorB, $shopB] = $this->createSupplier('Supplier Beta', 'beta6@supplier.ng', '08099991234');

        $order = $this->createOrderFixture([
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'selected_supplier_id' => $vendorA->id,
            'selected_shop_id' => $shopA->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unauthorized: only administrators can override supplier sourcing.');

        $this->sourcingService->overrideSupplierAssignment(
            $order,
            $vendorB->id,
            $shopB->id,
            $this->customerUser,
            'Hacking attempt'
        );
    }

    public function test_assigned_supplier_can_confirm_availability(): void
    {
        [$userA, $vendorA, $shopA] = $this->createSupplier('Supplier Alpha', 'alpha7@supplier.ng', '08098765432');
        [$userB, $vendorB, $shopB] = $this->createSupplier('Supplier Beta', 'beta7@supplier.ng', '08098765433');

        $order = $this->createOrderFixture([
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'selected_supplier_id' => $vendorA->id,
            'selected_shop_id' => $shopA->id,
        ]);

        // Wrong supplier cannot confirm
        try {
            $this->sourcingService->confirmSupplierAvailability($order, $vendorB->id);
            $this->fail('Expected InvalidArgumentException for unassigned supplier');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unauthorized: order is not assigned to this supplier', $e->getMessage());
        }

        // Correct assigned supplier confirms
        $this->sourcingService->confirmSupplierAvailability($order, $vendorA->id, 'Package packed and ready on dispatch table');

        $order->refresh();
        $this->assertEquals('supplier_confirmed', $order->sourcing_status);
        $this->assertStringContainsString('Package packed and ready', $order->sourcing_notes);
    }

    public function test_checkout_execution_populates_order_sourcing_and_order_item_snapshots(): void
    {
        [$userA, $vendorA, $shopA] = $this->createSupplier('Supplier Alpha', 'alpha8@supplier.ng', '08098765438');

        $product = Product::create([
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'category_id' => $this->category->id,
            'name' => 'Locally Harvested Beans 25kg',
            'slug' => 'local-beans-25kg',
            'unit_price' => '32000.00',
            'cost_price' => '27000.00',
            'is_in_stock' => true,
            'inventory_quantity' => 10,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $offer = SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'wholesale_cost' => '26500.00',
            'is_available' => true,
        ]);

        $checkoutService = app(\App\Services\CheckoutExecutionService::class);

        $result = $checkoutService->execute($this->customerUser, [
            'shop_id' => $shopA->id,
            'fulfillment_type' => 'in_shop_pickup',
            'payment_method' => 'in_shop_counter',
            '_checkout_idempotency_key' => 'IDEMP-TEST-' . uniqid(),
            '_checkout_request_hash' => hash('sha256', 'test-payload'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $this->assertTrue($result['success'], $result['message'] ?? json_encode($result));
        $order = $result['order'];
        $this->assertNotNull($order);

        // Assert Order sourcing fields
        $this->assertEquals('assigned_to_supplier', $order->sourcing_status);
        $this->assertEquals($vendorA->id, $order->selected_supplier_id);
        $this->assertEquals($shopA->id, $order->selected_shop_id);
        $this->assertNotNull($order->sourced_at);

        // Assert OrderItem snapshot fields
        $orderItem = $order->items()->first();
        $this->assertNotNull($orderItem);
        $this->assertEquals($offer->id, $orderItem->supplier_offer_id);
        $this->assertEquals($vendorA->id, $orderItem->supplier_vendor_id);
        $this->assertEquals($shopA->id, $orderItem->supplier_shop_id);
        $this->assertEquals('26500.00', $orderItem->supplier_cost_snapshot);
        $this->assertEquals('26500.00', $orderItem->cost_price);
        $this->assertEquals('NGN', $orderItem->supplier_cost_currency);
        $this->assertNotNull($orderItem->supplier_snapshot_at);
        $this->assertNotEmpty($orderItem->supplier_name_snapshot);
        $this->assertNotEmpty($orderItem->supplier_shop_name_snapshot);
    }
}
