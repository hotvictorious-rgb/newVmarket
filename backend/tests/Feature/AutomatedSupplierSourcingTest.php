<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\DeliveryLane;
use App\Models\Country;
use App\Models\Lga;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSupplierAssignment;
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
            'kyc_status' => 'verified',
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

    protected function createConfirmedOffer(array $attributes): SupplierOffer
    {
        return SupplierOffer::create(array_merge([
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now(),
            'availability_confirmed_by' => $this->adminUser->id,
            'availability_expires_at' => Carbon::now()->addHours(24),
        ], $attributes));
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
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'freshness_confirmed_at' => Carbon::now(),
        ]);

        // Supplier A offers wholesale at ₦64,000
        $this->createConfirmedOffer([
            'product_id' => $product->id,
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'wholesale_cost' => '64000.00',
        ]);

        // Supplier B offers wholesale cheaper at ₦61,500
        $cheaperOffer = $this->createConfirmedOffer([
            'product_id' => $product->id,
            'vendor_id' => $vendorB->id,
            'shop_id' => $shopB->id,
            'wholesale_cost' => '61500.00',
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
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'freshness_confirmed_at' => Carbon::now(),
        ]);

        // Supplier A is cheapest (₦35,000) but currently marked unavailable (out of stock)
        $this->createConfirmedOffer([
            'product_id' => $product->id,
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'wholesale_cost' => '35000.00',
            'is_available' => false,
        ]);

        // Supplier B offers at ₦37,000 but supplier user account is suspended
        $userB->status = 'suspended';
        $userB->save();

        $this->createConfirmedOffer([
            'product_id' => $product->id,
            'vendor_id' => $vendorB->id,
            'shop_id' => $shopB->id,
            'wholesale_cost' => '37000.00',
        ]);

        // Supplier C offers at ₦39,000 and is fully active and available
        $validOffer = $this->createConfirmedOffer([
            'product_id' => $product->id,
            'vendor_id' => $vendorC->id,
            'shop_id' => $shopC->id,
            'wholesale_cost' => '39000.00',
        ]);

        $resolved = $this->sourcingService->resolveCheapestOffer($product);

        $this->assertNotNull($resolved);
        $this->assertEquals($validOffer->id, $resolved['supplier_offer_id']);
        $this->assertEquals($vendorC->id, $resolved['vendor_id']);
        $this->assertEquals('39000.00', $resolved['wholesale_cost']);
    }

    public function test_catalog_owner_without_explicit_confirmed_offer_is_not_selectable(): void
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
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'freshness_confirmed_at' => Carbon::now(),
        ]);

        // Catalog ownership and a cost_price are not evidence that a supplier
        // currently has stock or has accepted this particular sourcing offer.
        $resolved = $this->sourcingService->resolveCheapestOffer($product);

        $this->assertNull($resolved);
    }

    public function test_tie_breaking_prioritizes_preferred_lga_deterministically(): void
    {
        [$userA, $vendorA, $shopA] = $this->createSupplier('Supplier Uyo A', 'uyo-a@supplier.ng', '08077777771', $this->uyoLga);
        [$userB, $vendorB, $shopB] = $this->createSupplier('Supplier Uyo B', 'uyo-b@supplier.ng', '08077777772', $this->uyoLga);
        [$userEket, $vendorEket, $shopEket] = $this->createSupplier('Supplier Eket', 'eket@supplier.ng', '08077777773', $this->eketLga);

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
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'freshness_confirmed_at' => Carbon::now(),
        ]);

        // Offers A and B in Uyo offer identical wholesale price of ₦16,000
        $offerA = $this->createConfirmedOffer([
            'product_id' => $product->id,
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'wholesale_cost' => '16000.00',
        ]);

        $offerB = $this->createConfirmedOffer([
            'product_id' => $product->id,
            'vendor_id' => $vendorB->id,
            'shop_id' => $shopB->id,
            'wholesale_cost' => '16000.00',
        ]);

        // Offer from shop outside Uyo is also confirmed
        $offerEket = $this->createConfirmedOffer([
            'product_id' => $product->id,
            'vendor_id' => $vendorEket->id,
            'shop_id' => $shopEket->id,
            'wholesale_cost' => '15000.00', // Cheaper, but shop is outside Uyo!
        ]);

        // 1. Sourcing strictly excludes shops outside Uyo, Akwa Ibom State
        $resolvedUyo = $this->sourcingService->resolveCheapestOffer($product, 1, $this->uyoLga->id);
        $this->assertNotEquals($offerEket->id, $resolvedUyo['supplier_offer_id'], 'Non-Uyo shop offer must not be selected');

        // 2. Tie-breaking between identical-cost active Uyo offers deterministically resolves by offer ID
        $this->assertEquals($offerA->id, $resolvedUyo['supplier_offer_id']);
        $this->assertEquals($shopA->id, $resolvedUyo['shop_id']);

        $resolvedEket = $this->sourcingService->resolveCheapestOffer($product, 1, $this->eketLga->id);
        $this->assertEquals($offerA->id, $resolvedEket['supplier_offer_id']);
        $this->assertEquals($shopA->id, $resolvedEket['shop_id']);
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
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'freshness_confirmed_at' => Carbon::now(),
        ]);

        $offer = $this->createConfirmedOffer([
            'product_id' => $product->id,
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'wholesale_cost' => '5800.00',
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
        $this->assertEquals($offer->id, $orderItem->fulfillment_supplier_offer_id);
        $this->assertEquals('5800.00', $orderItem->fulfillment_supplier_cost_snapshot);
        $this->assertEquals($vendorA->id, $orderItem->fulfillment_supplier_vendor_id);
        $this->assertEquals($shopA->id, $orderItem->fulfillment_supplier_shop_id);

        // Later: supplier raises their wholesale cost on the offer
        $offer->wholesale_cost = '7200.00';
        $offer->save();

        $this->assertEquals('5800.00', $orderItem->fresh()->supplier_cost_snapshot);
        $this->assertEquals('5800.00', $orderItem->fresh()->fulfillment_supplier_cost_snapshot);

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

    public function test_checkout_keeps_supplier_sourcing_non_binding_until_verified_payment(): void
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
            'availability_confirmed_at' => Carbon::now(),
            'availability_confirmed_by' => $this->adminUser->id,
            'availability_expires_at' => Carbon::now()->addHours(24),
        ]);

        DeliveryLane::firstOrCreate([
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
        ], [
            'base_fee' => '500.00',
            'bulky_surcharge' => '1000.00',
            'logistics_company_base_payable' => '450.00',
            'logistics_company_bulky_surcharge' => '800.00',
            'estimated_delivery_hours_min' => 2,
            'estimated_delivery_hours_max' => 4,
            'is_active' => true,
        ]);

        $checkoutService = app(\App\Services\CheckoutExecutionService::class);

        $result = $checkoutService->execute($this->customerUser, [
            'shop_id' => $shopA->id,
            'fulfillment_type' => 'doorstep_delivery',
            'destination_lga_id' => $this->uyoLga->id,
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
        // Company payable uses the independent corridor tariff, not the customer delivery fee.
        $this->assertSame('500.00', (string) $order->delivery_fee);
        $this->assertSame('450.00', (string) $order->logistics_company_payable_amount);

        // Checkout performs only a non-binding feasibility check. No supplier
        // assignment, wholesale-cost snapshot or sourcing timestamp may exist yet.
        $this->assertSame('awaiting_payment', $order->sourcing_status);
        $this->assertNull($order->selected_supplier_id);
        $this->assertNull($order->selected_shop_id);
        $this->assertNull($order->sourced_at);
        $this->assertNull($order->supplier_cost_total_snapshot);
        $this->assertNull($order->fulfillment_supplier_cost_total_snapshot);
        $this->assertSame(0, OrderSupplierAssignment::where('order_id', $order->id)->count());

        $orderItem = $order->items()->first();
        $this->assertNotNull($orderItem);
        $this->assertNull($orderItem->supplier_offer_id);
        $this->assertNull($orderItem->supplier_vendor_id);
        $this->assertNull($orderItem->supplier_shop_id);
        $this->assertNull($orderItem->supplier_cost_snapshot);
        $this->assertNull($orderItem->supplier_snapshot_at);
        $this->assertSame('0.00', (string) $orderItem->cost_price);

        // Missing company tariff must fail closed before a new order or payment intent is created.
        DeliveryLane::where('origin_lga_id', $this->uyoLga->id)
            ->where('destination_lga_id', $this->uyoLga->id)
            ->update(['logistics_company_base_payable' => null]);
        $ordersBeforeMissingTariffAttempt = Order::count();
        $blocked = $checkoutService->execute($this->customerUser, [
            'shop_id' => $shopA->id,
            'fulfillment_type' => 'doorstep_delivery',
            'destination_lga_id' => $this->uyoLga->id,
            '_checkout_idempotency_key' => 'IDEMP-MISSING-TARIFF-' . uniqid(),
            '_checkout_request_hash' => hash('sha256', 'missing-tariff-test'),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $this->assertFalse($blocked['success']);
        $this->assertStringContainsString('no approved logistics-company payable tariff', strtolower($blocked['message']));
        $this->assertSame($ordersBeforeMissingTariffAttempt, Order::count());
    }

    public function test_paid_supplier_confirmation_timeout_automatically_fails_over_with_balanced_payable_recovery(): void
    {
        [$userA, $vendorA, $shopA] = $this->createSupplier('Primary Supplier', 'primary-failover@supplier.ng', '08011110001');
        [$userB, $vendorB, $shopB] = $this->createSupplier('Backup Supplier', 'backup-failover@supplier.ng', '08011110002');

        $product = Product::create([
            'vendor_id' => null,
            'shop_id' => null,
            'category_id' => $this->category->id,
            'name' => 'Supplier Failover Test Product',
            'slug' => 'supplier-failover-test-product',
            'unit_price' => '32000.00',
            'cost_price' => '0.00',
            'is_in_stock' => true,
            'inventory_quantity' => 0,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subMinute(),
            'freshness_confirmed_at' => Carbon::now()->subMinute(),
        ]);

        $this->createConfirmedOffer([
            'product_id' => $product->id,
            'vendor_id' => $vendorA->id,
            'shop_id' => $shopA->id,
            'wholesale_cost' => '25000.00',
        ]);
        $this->createConfirmedOffer([
            'product_id' => $product->id,
            'vendor_id' => $vendorB->id,
            'shop_id' => $shopB->id,
            'wholesale_cost' => '27000.00',
        ]);

        DeliveryLane::create([
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'base_fee' => '500.00',
            'bulky_surcharge' => '1000.00',
            'logistics_company_base_payable' => '450.00',
            'logistics_company_bulky_surcharge' => '800.00',
            'estimated_delivery_hours_min' => 2,
            'estimated_delivery_hours_max' => 4,
            'is_active' => true,
        ]);

        $checkout = app(\App\Services\CheckoutExecutionService::class)->execute($this->customerUser, [
            'shop_id' => $shopA->id,
            'fulfillment_type' => 'doorstep_delivery',
            'destination_lga_id' => $this->uyoLga->id,
            '_checkout_idempotency_key' => 'IDEMP-FAILOVER-' . uniqid(),
            '_checkout_request_hash' => hash('sha256', 'supplier-failover-quantity-three'),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);
        $this->assertTrue($checkout['success'], $checkout['message'] ?? 'Checkout failed.');
        $order = $checkout['order'];

        $settlement = app(\App\Services\OrderSettlementService::class)->settlePaystackOrder($order, [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => (int) bcmul((string) $order->grand_total, '100', 0),
            'reference' => (string) $order->payment_reference,
            'id' => 'TX-FAILOVER-' . $order->id,
            'channel' => 'card',
        ], $this->customerUser);
        $this->assertTrue($settlement['success'] ?? false, $settlement['message'] ?? 'Payment settlement failed.');

        $order->refresh();
        $item = OrderItem::where('order_id', $order->id)->firstOrFail();
        $this->assertSame((int) $vendorA->id, (int) $item->supplier_vendor_id);
        $this->assertSame('25000.00', (string) $item->supplier_cost_snapshot);
        $failedAssignment = OrderSupplierAssignment::where('order_id', $order->id)
            ->where('supplier_vendor_id', $vendorA->id)
            ->firstOrFail();

        // Exercise the real 60-operating-minute timeout worker and its automatic
        // failover path, not only the manual supplier-rejection endpoint.
        Carbon::setTestNow(Carbon::parse('2026-10-24 10:01:00', 'Africa/Lagos'));
        OrderSupplierAssignment::whereKey($failedAssignment->id)->update([
            'assigned_at' => Carbon::parse('2026-10-24 08:59:00', 'Africa/Lagos'),
        ]);
        try {
            $this->artisan('supplier:check-confirmation-sla')->assertExitCode(0);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertDatabaseHas('admin_audit_logs', [
            'target_type' => Order::class,
            'target_id' => $order->id,
            'action' => 'supplier_confirmation_sla_timed_out',
        ]);

        $order->refresh();
        $item->refresh();
        $failedAssignment->refresh();
        $replacementAssignment = OrderSupplierAssignment::where('order_id', $order->id)
            ->where('supplier_vendor_id', $vendorB->id)
            ->firstOrFail();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->order_status);
        $this->assertSame('awaiting_supplier_confirmation', $order->sourcing_status);
        $this->assertSame('admin_reassigned', $failedAssignment->status);
        $this->assertSame('awaiting_supplier_confirmation', $replacementAssignment->status);
        $this->assertSame((int) $vendorA->id, (int) $item->supplier_vendor_id, 'Capture-time supplier identity must remain immutable.');
        $this->assertSame('25000.00', (string) $item->supplier_cost_snapshot, 'Capture-time cost must remain immutable.');
        $this->assertSame((int) $vendorB->id, (int) $item->fulfillment_supplier_vendor_id);
        $this->assertSame((int) $shopB->id, (int) $item->fulfillment_supplier_shop_id);
        $this->assertSame('27000.00', (string) $item->fulfillment_supplier_cost_snapshot);

        $oldSupplierNet = '0.00';
        foreach (\App\Models\FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'supplier_payable')
            ->where('beneficiary_type', VendorProfile::class)
            ->where('beneficiary_id', $vendorA->id)
            ->get() as $entry) {
            $oldSupplierNet = $entry->entry_type === 'credit'
                ? \App\Services\BcMathService::add($oldSupplierNet, (string) $entry->amount)
                : \App\Services\BcMathService::sub($oldSupplierNet, (string) $entry->amount);
        }
        $newSupplierNet = '0.00';
        foreach (\App\Models\FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'supplier_payable')
            ->where('beneficiary_type', VendorProfile::class)
            ->where('beneficiary_id', $vendorB->id)
            ->get() as $entry) {
            $newSupplierNet = $entry->entry_type === 'credit'
                ? \App\Services\BcMathService::add($newSupplierNet, (string) $entry->amount)
                : \App\Services\BcMathService::sub($newSupplierNet, (string) $entry->amount);
        }

        $this->assertSame('0.00', $oldSupplierNet, 'Failed supplier payable must be reversed exactly once.');
        $this->assertSame('81000.00', $newSupplierNet, 'Replacement payable must match three units at the replacement source cost.');
        $this->assertDatabaseHas('admin_audit_logs', [
            'target_type' => Order::class,
            'target_id' => $order->id,
            'action' => 'automatic_supplier_failover_applied',
        ]);

        // Replaying the timeout/rejection recovery after the assignment has
        // transitioned must be a no-op: no second payable reversal, replacement
        // payable, assignment, or audit success may be created.
        $journalCountBeforeReplay = \App\Models\FinancialJournalEntry::where('order_id', $order->id)->count();
        $assignmentCountBeforeReplay = OrderSupplierAssignment::where('order_id', $order->id)->count();
        $replay = app(\App\Services\SupplierReassignmentRecoveryService::class)
            ->attemptAutomaticFailover($order->id, $failedAssignment->id);

        $this->assertFalse($replay['success'] ?? false);
        $this->assertSame(
            $journalCountBeforeReplay,
            \App\Models\FinancialJournalEntry::where('order_id', $order->id)->count(),
            'A replay must not duplicate any financial journal entry.'
        );
        $this->assertSame(
            $assignmentCountBeforeReplay,
            OrderSupplierAssignment::where('order_id', $order->id)->count(),
            'A replay must not create another supplier assignment.'
        );
    }

}
