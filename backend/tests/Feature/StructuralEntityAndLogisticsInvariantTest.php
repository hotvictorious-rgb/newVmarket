<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\DeliveryLane;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StructuralEntityAndLogisticsInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected Shop $shop;
    protected Country $country;
    protected State $state;
    protected Lga $lga;
    protected User $customer;
    protected User $riderUser;
    protected RiderProfile $rider;

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
        $this->state = State::create([
            'country_id' => $this->country->id,
            'name' => 'Akwa Ibom',
            'code' => 'AK',
            'is_active' => true,
        ]);
        $this->lga = Lga::create([
            'state_id' => $this->state->id,
            'name' => 'Uyo',
            'code' => 'UYO',
            'is_active' => true,
        ]);

        // Vendor Profile
        $this->vendorUser = User::create([
            'name' => 'Merchant Test User',
            'email' => 'merchant@test.com',
            'phone' => '+2348011223344',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Apex Electronics Hub',
            'contact_phone' => '+2348011223344',
            'marketplace_status' => 'active',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1000000001',
            'account_name' => 'Apex Electronics Hub',
        ]);

        // Shop belonging to Vendor
        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Apex Plaza Branch',
            'slug' => 'apex-plaza-branch',
            'address' => '10 Oron Road, Uyo',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        // Customer
        $this->customer = User::create([
            'name' => 'Customer Shopper',
            'email' => 'shopper@test.com',
            'phone' => '+2348099887766',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        // Delivery Courier (Rider)
        $this->riderUser = User::create([
            'name' => 'Rider Dispatcher',
            'email' => 'rider@speedlogistics.ng',
            'phone' => '+2348033445566',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $this->rider = RiderProfile::create([
            'user_id' => $this->riderUser->id,
            'vendor_id' => $this->vendor->id,
            'assigned_lga_id' => $this->lga->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
            'is_online' => true,
        ]);
    }

    /**
     * PROOF 1: No shop can exist without a vendor.
     * Enforced by database NOT NULL constraint and foreign key on shops.vendor_id.
     */
    public function test_no_shop_can_exist_without_a_vendor(): void
    {
        // Case A: Attempting to insert a shop with null vendor_id MUST trigger database QueryException
        $this->expectException(QueryException::class);

        Shop::create([
            'vendor_id' => null, // Strictly forbidden!
            'name' => 'Orphan Shop',
            'slug' => 'orphan-shop',
            'address' => 'No Vendor Street',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
        ]);
    }

    /**
     * PROOF 1B: No shop can reference a non-existent vendor.
     */
    public function test_no_shop_can_reference_non_existent_vendor(): void
    {
        $this->expectException(QueryException::class);

        Shop::create([
            'vendor_id' => 999999, // Foreign key violation!
            'name' => 'Ghost Vendor Shop',
            'slug' => 'ghost-vendor-shop',
            'address' => 'Ghost Street',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
        ]);
    }

    /**
     * PROOF 2: No order can exist without a shop.
     * Enforced by database NOT NULL constraint and foreign key on orders.shop_id.
     */
    public function test_no_order_can_exist_without_a_shop(): void
    {
        $this->expectException(QueryException::class);

        Order::create([
            'order_number' => 'VM-TEST-NOSHOP',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => null, // Strictly forbidden!
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '500.00',
            'grand_total' => '10500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
        ]);
    }

    /**
     * PROOF 3: No order can exist without a vendor.
     * Enforced by database NOT NULL constraint and foreign key on orders.vendor_id.
     */
    public function test_no_order_can_exist_without_a_vendor(): void
    {
        $this->expectException(QueryException::class);

        Order::create([
            'order_number' => 'VM-TEST-NOVENDOR',
            'customer_id' => $this->customer->id,
            'vendor_id' => null, // Strictly forbidden!
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '500.00',
            'grand_total' => '10500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
        ]);
    }

            /**
     * PROOF 5: No vendor can sell without being active.
     * If a merchant is suspended or a shop branch is inactive, checkout is strictly rejected.
     */
    public function test_no_vendor_can_sell_without_being_active(): void
    {
        $category = \App\Models\Category::create([
            'name' => 'Computers & Tech',
            'slug' => 'computers-tech',
            'is_active' => true,
        ]);

        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $category->id,
            'name' => 'Wireless Keyboard',
            'slug' => 'wireless-keyboard',
            'unit_price' => '15000.00',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'Shopper',
            'phone' => '+2348011223344',
            'address_line' => '12 Ikot Ekpene Road',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
        ]);

        Sanctum::actingAs($this->customer, ['*']);

        // Case A: Vendor profile is suspended -> Checkout MUST be rejected
        $this->vendor->marketplace_status = 'suspended';
        $this->vendor->save();

        $resSuspendedVendor = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resSuspendedVendor->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'The merchant providing this item is currently not active on the marketplace.');

        // Case B: Vendor is active, but Shop is deactivated (closed branch) -> Checkout MUST be rejected
        $this->vendor->marketplace_status = 'active';
        $this->vendor->save();

        $this->shop->is_active = false;
        $this->shop->save();

        $resInactiveShop = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resInactiveShop->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'The selected shop branch is currently inactive.');
    }

    /**
     * PROOF 6A: Delivery is strictly HALTED if the Rider is inactive at the point of delivery.
     */
    public function test_delivery_halted_if_rider_is_inactive_at_point_of_delivery(): void
    {
        $order = Order::create([
            'order_number' => 'VM-HALT-RIDER-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '21500.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'logistics_fee_retained' => '1350.00',
            'platform_delivery_fee' => '150.00',
            'verification_code' => '112233',
        ]);

        // Deactivate the rider profile
        $this->rider->is_active = false;
        $this->rider->save();

        $settlementService = app(OrderSettlementService::class);
        $result = $settlementService->verifyCustomerDeliveryOtp($order, '112233', $this->rider);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Vendor rider profile is inactive', $result['message']);
        $this->assertEquals('out_for_delivery', $order->fresh()->order_status, 'Order status must remain out_for_delivery');
    }

        /**
     * PROOF 6C: Delivery is strictly HALTED if the Vendor is suspended at point of delivery.
     */
    public function test_delivery_halted_if_vendor_is_suspended_at_point_of_delivery(): void
    {
        $order = Order::create([
            'order_number' => 'VM-HALT-VENDOR-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '21500.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'logistics_fee_retained' => '1350.00',
            'platform_delivery_fee' => '150.00',
            'verification_code' => '778899',
        ]);

        // Suspend the merchant vendor
        $this->vendor->marketplace_status = 'suspended';
        $this->vendor->save();

        $settlementService = app(OrderSettlementService::class);
        $result = $settlementService->verifyCustomerDeliveryOtp($order, '778899', $this->rider);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Merchant marketplace account is suspended', $result['message']);
        $this->assertEquals('out_for_delivery', $order->fresh()->order_status, 'Order status must remain out_for_delivery');
    }

    /**
     * PROOF 6D: Delivery is strictly HALTED if the Shop branch is inactive at point of delivery.
     */
    public function test_delivery_halted_if_shop_branch_is_inactive_at_point_of_delivery(): void
    {
        $order = Order::create([
            'order_number' => 'VM-HALT-SHOP-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '21500.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'logistics_fee_retained' => '1350.00',
            'platform_delivery_fee' => '150.00',
            'verification_code' => '334455',
        ]);

        // Deactivate the shop branch
        $this->shop->is_active = false;
        $this->shop->save();

        $settlementService = app(OrderSettlementService::class);
        $result = $settlementService->verifyCustomerDeliveryOtp($order, '334455', $this->rider);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Merchant shop branch is inactive or closed', $result['message']);
        $this->assertEquals('out_for_delivery', $order->fresh()->order_status, 'Order status must remain out_for_delivery');
    }

        /**
     * PROOF 8A: Database Engine Foreign Key Protection & SoftDelete Audit Preservation:
     * A shop CANNOT be hard-deleted when orders reference it (throws QueryException).
     * Soft-deleting preserves historical relationship access for order receipts.
     */
    public function test_shop_cannot_be_deleted_at_database_level_when_orders_exist(): void
    {
        $order = Order::create([
            'order_number' => 'VM-TEST-SHOP-DEL-GUARD',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '500.00',
            'grand_total' => '10500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
        ]);

        // Attempting to hard-delete (forceDelete) MUST trigger database QueryException (FK RESTRICT)
        $this->expectException(QueryException::class);
        $this->shop->forceDelete();
    }

    /**
     * PROOF 8B: Application Deletion Guard:
     * Validates whether a shop can be safely deleted or if financial transactions block it.
     */
    public function test_shop_deletion_guard_validates_transaction_and_reservation_history(): void
    {
        // 1. With an active order
        $order = Order::create([
            'order_number' => 'VM-TEST-DEL-GUARD-02',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '500.00',
            'grand_total' => '10500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
        ]);

        $reason = null;
        $this->assertFalse($this->shop->canBeSafelyDeleted($reason));
        $this->assertStringContainsString('Shop branch cannot be deleted because permanent historical transaction records', $reason);

        // 2. Fresh shop with NO orders and NO reservations CAN be safely deleted
        $freshShop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Empty Clean Shop',
            'slug' => 'empty-clean-shop',
            'address' => '55 Empty Way',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'is_active' => true,
        ]);

        $this->assertTrue($freshShop->canBeSafelyDeleted($reason));
        $this->assertTrue($freshShop->delete());
    }

    /**
     * PROOF 8C: Shop Closure / Deactivation Guard:
     * Prevents vendors from closing/deactivating a branch while customer orders are in-flight or held in escrow.
     */
    public function test_shop_closure_guard_blocks_deactivation_during_in_flight_orders_or_escrow(): void
    {
        // 1. Order is in-flight (processing)
        $order = Order::create([
            'order_number' => 'VM-INFLIGHT-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'order_status' => 'processing',
            'vendor_settlement_status' => 'held',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '500.00',
            'grand_total' => '10500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
        ]);

        $reason = null;
        $this->assertTrue($this->shop->hasInFlightOrders());
        $this->assertFalse($this->shop->canBeClosedOrDeactivated($reason));
        $this->assertStringContainsString('active in-flight customer orders', $reason);

        // 2. Order is delivered, but still in held escrow (within 24h return window)
        $order->order_status = 'delivered';
        $order->vendor_settlement_status = 'held';
        $order->save();

        $this->assertFalse($this->shop->hasInFlightOrders());
        $this->assertTrue($this->shop->hasPendingEscrow());
        $this->assertFalse($this->shop->canBeClosedOrDeactivated($reason));
        $this->assertStringContainsString('pending escrow settlement balances', $reason);

        // 3. Order is settled (cleared out)
        $order->vendor_settlement_status = 'settled';
        $order->save();

        $this->assertFalse($this->shop->hasInFlightOrders());
        $this->assertFalse($this->shop->hasPendingEscrow());
        $this->assertTrue($this->shop->canBeClosedOrDeactivated($reason));
    }

    /**
     * PROOF 9: SoftDeletes preserves historical order relationship access.
     */
    public function test_soft_deleted_shop_preserves_historical_order_relationship_and_details(): void
    {
        $order = Order::create([
            'order_number' => 'VM-TEST-SOFT-DEL-RECEIPT',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'order_status' => 'delivered',
            'vendor_settlement_status' => 'settled',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '500.00',
            'grand_total' => '10500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
        ]);

        // Soft-delete the shop
        $this->shop->delete();
        $this->assertTrue($this->shop->trashed());

        // Regular query hides soft-deleted shop
        $this->assertNull(Shop::find($this->shop->id));

        // Order relationship STILL loads the historical shop flawlessly via withTrashed()
        $freshOrder = Order::with('shop')->find($order->id);
        $this->assertNotNull($freshOrder->shop);
        $this->assertEquals($this->shop->id, $freshOrder->shop->id);
        $this->assertEquals('Apex Plaza Branch', $freshOrder->shop->name);
    }

    /**
     * PROOF 10: Primary branch protection & atomic branch promotion.
     */
    public function test_primary_branch_protection_and_atomic_migration(): void
    {
        // 1. Mark this shop as primary
        $this->shop->is_primary_branch = true;
        $this->shop->save();

        // 2. Create second branch for same vendor
        $secondBranch = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Apex Airport Road Branch',
            'slug' => 'apex-airport-road-branch',
            'address' => '22 Airport Road',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'is_primary_branch' => false,
            'is_active' => true,
        ]);

        // Primary branch CANNOT be deleted or deactivated while other branches exist
        $reason = null;
        $this->assertFalse($this->shop->canBeClosedOrDeactivated($reason));
        $this->assertStringContainsString('Cannot close or deactivate the primary branch', $reason);

        $this->assertFalse($this->shop->canBeSafelyDeleted($reason));
        $this->assertStringContainsString('Cannot delete the primary branch while other branches exist', $reason);

        // 3. Atomically promote second branch to primary
        $secondBranch->makePrimaryBranch();

        // Verification: First branch is demoted, second is now primary
        $this->assertFalse($this->shop->fresh()->is_primary_branch);
        $this->assertTrue($secondBranch->fresh()->is_primary_branch);
    }

    /**
     * PROOF 11: Bulk inventory reassignment between branches.
     */
    public function test_bulk_inventory_reassignment_between_branches(): void
    {
        $secondBranch = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Apex Mall Branch',
            'slug' => 'apex-mall-branch',
            'address' => 'Shop 14, Tropicana Mall',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'is_primary_branch' => false,
            'is_active' => true,
        ]);

        $category = \App\Models\Category::firstOrCreate(
            ['slug' => 'gadgets-transfer'],
            ['name' => 'Gadgets Transfer', 'is_active' => true]
        );

        $product1 = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $category->id,
            'name' => 'Power Bank 20000mAh',
            'slug' => 'power-bank-20000mah',
            'unit_price' => '12000.00',
            'inventory_quantity' => 15,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $product2 = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $category->id,
            'name' => 'Bluetooth Earbuds',
            'slug' => 'bluetooth-earbuds-pro',
            'unit_price' => '8500.00',
            'inventory_quantity' => 20,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        // Transfer all products from branch 1 to branch 2
        $transferredCount = $this->shop->transferProductsTo($secondBranch);
        $this->assertGreaterThanOrEqual(2, $transferredCount);

        // Verification: Products now belong to branch 2
        $this->assertEquals($secondBranch->id, $product1->fresh()->shop_id);
        $this->assertEquals($secondBranch->id, $product2->fresh()->shop_id);
    }

    /**
     * PROOF 12: Rider custody clearance blocks deactivation during transit.
     */
    public function test_rider_custody_clearance_blocks_deactivation_during_transit(): void
    {
        $order = Order::create([
            'order_number' => 'VM-CUSTODY-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '21500.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'logistics_fee_retained' => '1350.00',
            'platform_delivery_fee' => '150.00',
        ]);

        $reason = null;
        $this->assertTrue($this->rider->hasActiveCustodyOrders());
        $this->assertFalse($this->rider->canBeDeactivated($reason));
        $this->assertStringContainsString('Courier has active customer parcels in physical custody', $reason);

        // Once order reaches terminal state (delivered), courier can be safely deactivated
        $order->order_status = 'delivered';
        $order->save();

        $this->assertFalse($this->rider->hasActiveCustodyOrders());
        $this->assertTrue($this->rider->canBeDeactivated($reason));
    }

    /**
     * PROOF 13: Delivery halt incident persists reason on order and logs AdminAuditLog.
     */
    public function test_delivery_halt_incident_persists_reason_and_logs_audit_trail(): void
    {
        $order = Order::create([
            'order_number' => 'VM-HALT-AUDIT-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '21500.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'logistics_fee_retained' => '1350.00',
            'platform_delivery_fee' => '150.00',
            'verification_code' => '556677',
        ]);

        // Suspend merchant while out for delivery
        $this->vendor->marketplace_status = 'suspended';
        $this->vendor->save();

        $settlementService = app(\App\Services\OrderSettlementService::class);
        $result = $settlementService->verifyCustomerDeliveryOtp($order, '556677', $this->rider);

        $this->assertFalse($result['success']);

        // Verification 1: Order has delivery_halted_reason stamped
        $freshOrder = $order->fresh();
        $this->assertNotNull($freshOrder->delivery_halted_at);
        $this->assertStringContainsString('Merchant marketplace account is suspended', $freshOrder->delivery_halted_reason);

        // Verification 2: AdminAuditLog entry was automatically generated
        $auditLog = \App\Models\AdminAuditLog::where('target_id', $order->id)
            ->where('action', 'delivery_handshake_halted_entity_inactive')
            ->first();

        $this->assertNotNull($auditLog, 'Admin audit log must be recorded for delivery halt incident');
        $this->assertEquals($order->id, $auditLog->target_id);
    }

    /**
     * PROOF 14: Order placement is strictly rejected if Vendor is deactivated, suspended, or soft-deleted.
     */
    public function test_order_placement_rejected_if_vendor_is_deactivated_or_suspended_or_trashed(): void
    {
        $category = \App\Models\Category::firstOrCreate(
            ['slug' => 'gadgets'],
            ['name' => 'Gadgets', 'is_active' => true]
        );

        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $category->id,
            'name' => 'Noise Cancelling Headphones',
            'slug' => 'noise-cancelling-headphones',
            'unit_price' => '30000.00',
            'inventory_quantity' => 15,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'John Customer',
            'phone' => '+2348011223344',
            'address_line' => '44 Wellington Bassey Way',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
        ]);

        Sanctum::actingAs($this->customer, ['*']);

        // Case A: Vendor marketplace_status = 'suspended'
        $this->vendor->marketplace_status = 'suspended';
        $this->vendor->save();

        $resSuspended = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resSuspended->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'The merchant providing this item is currently not active on the marketplace.');

        // Also verify Intent creation rejects suspended vendor
        $resIntent = $this->postJson('/api/v1/checkout/intent', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resIntent->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'The merchant providing this item is currently not active on the marketplace.');

        // Case B: Vendor user account is suspended
        $this->vendor->marketplace_status = 'active';
        $this->vendor->save();
        $this->vendorUser->status = 'suspended';
        $this->vendorUser->save();

        $resVendorUserSuspended = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resVendorUserSuspended->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'The merchant user account is inactive.');

        // Case C: Vendor profile is soft-deleted
        $this->vendorUser->status = 'active';
        $this->vendorUser->save();
        $this->vendor->delete(); // Soft-deletes vendor profile

        $resVendorDeleted = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resVendorDeleted->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'The merchant providing this item is currently not active on the marketplace.');

        // Restore vendor for subsequent proofs
        $this->vendor->restore();
    }

    /**
     * PROOF 15: Order placement is strictly rejected if Shop is inactive or soft-deleted.
     */
    public function test_order_placement_rejected_if_shop_is_inactive_or_trashed(): void
    {
        $category = \App\Models\Category::firstOrCreate(
            ['slug' => 'appliances'],
            ['name' => 'Appliances', 'is_active' => true]
        );

        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $category->id,
            'name' => 'Electric Kettle',
            'slug' => 'electric-kettle',
            'unit_price' => '12000.00',
            'inventory_quantity' => 20,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'John Customer',
            'phone' => '+2348011223344',
            'address_line' => '44 Wellington Bassey Way',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
        ]);

        Sanctum::actingAs($this->customer, ['*']);

        // Case A: Shop is_active = false
        $this->shop->is_active = false;
        $this->shop->save();

        $resInactiveShop = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resInactiveShop->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'The selected shop branch is currently inactive.');

        // Intent creation with inactive shop
        $resIntent = $this->postJson('/api/v1/checkout/intent', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resIntent->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'The selected shop branch is currently inactive.');

        // In-Shop Pickup Reservation creation with inactive shop
        $resPickup = $this->postJson('/api/v1/customer/pickup-reservations', [
            'shop_id' => $this->shop->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $resPickup->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'This shop branch is not active.');

        // Case B: Shop is soft-deleted
        $this->shop->is_active = true;
        $this->shop->save();
        $this->shop->delete(); // Soft-deletes shop branch

        $resTrashedShop = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resTrashedShop->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'The selected shop branch is currently inactive.');

        // Restore shop
        $this->shop->restore();
    }

            /**
     * PROOF 18: Recommended Hardening - Customer account and Product marketplace freshness guards.
     */
    public function test_recommended_hardening_customer_account_and_product_freshness_guards(): void
    {
        $category = \App\Models\Category::firstOrCreate(
            ['slug' => 'audio'],
            ['name' => 'Audio', 'is_active' => true]
        );

        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $category->id,
            'name' => 'Studio Monitor Earphones',
            'slug' => 'studio-monitor-earphones-' . uniqid(),
            'unit_price' => '45000.00',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'John Customer',
            'phone' => '+2348011223344',
            'address_line' => '44 Wellington Bassey Way',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
        ]);

        Sanctum::actingAs($this->customer, ['*']);

        // Case A: Customer account is suspended -> HTTP 403 Forbidden
        $this->customer->status = 'suspended';
        $this->customer->save();

        $resSuspendedCustomer = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resSuspendedCustomer->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Your customer account is deactivated or suspended.');

        // Intent creation by suspended customer
        $resIntentCustomer = $this->postJson('/api/v1/checkout/intent', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resIntentCustomer->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Your customer account is deactivated or suspended.');

        // In-Shop Pickup Reservation by suspended customer
        $resPickupCustomer = $this->postJson('/api/v1/customer/pickup-reservations', [
            'shop_id' => $this->shop->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $resPickupCustomer->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Your customer account is deactivated or suspended.');

        // Restore customer account
        $this->customer->status = 'active';
        $this->customer->save();

        // Case B: Product is soft-deleted -> Checkout rejected (not found in eligible products)
        $product->delete();

        $resProductTrashed = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resProductTrashed->assertStatus(404);
    }
}


