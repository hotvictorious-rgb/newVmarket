<?php

namespace Tests\Feature;

use App\Console\Commands\CheckSupplierConfirmationSlaCommand;
use App\Models\AdminAuditLog;
use App\Models\DeliveryLane;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSupplierAssignment;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\CheckoutExecutionService;
use App\Services\OrderSettlementService;
use App\Services\SourcingAllocationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WholeOrderConsolidatedSourcingAndFleetDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected State $akwaIbom;
    protected Lga $uyoLga;
    protected Lga $ikotEkpeneLga;
    protected DeliveryLane $intraUyoLane;
    protected User $superAdmin;
    protected User $customer;
    protected VendorProfile $supplierA;
    protected Shop $shopA;
    protected VendorProfile $supplierB;
    protected Shop $shopB;
    protected Product $product1;
    protected Product $product2;
    protected LogisticsCompanyProfile $logisticsCompany;
    protected RiderProfile $bikeRider;
    protected RiderProfile $kekeRider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = \App\Models\Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);
        $country = $this->country;

        $this->akwaIbom = State::create(['country_id' => $this->country->id, 'name' => 'Akwa Ibom', 'code' => 'AK', 'is_active' => true]);
        $this->uyoLga = Lga::create(['state_id' => $this->akwaIbom->id, 'name' => 'Uyo', 'code' => 'UYO', 'is_active' => true]);
        $this->ikotEkpeneLga = Lga::create(['state_id' => $this->akwaIbom->id, 'name' => 'Ikot Ekpene', 'code' => 'IKP', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@victoriousmarket.ng',
            'phone' => '08011111111',
            'password' => bcrypt('AdminPass123!'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Ekaette Bassey',
            'email' => 'ekaette@gmail.com',
            'phone' => '08022222222',
            'password' => bcrypt('CustomerPass123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        // Logistics Company and Riders
        $logUser = User::create([
            'name' => 'Swift Uyo Logistics Ops',
            'email' => 'fleet@swiftuyo.ng',
            'phone' => '08033333333',
            'password' => bcrypt('FleetPass123!'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);
        $this->logisticsCompany = LogisticsCompanyProfile::create([
            'user_id' => $logUser->id,
            'company_name' => 'Swift Uyo 3PL Logistics',
            'hq_lga_id' => $this->uyoLga->id,
            'is_approved' => true,
            'is_active' => true,
        ]);

        $bikeRiderUser = User::create([
            'name' => 'Bike Courier John',
            'email' => 'john.rider@swiftuyo.ng',
            'phone' => '08044444444',
            'password' => bcrypt('RiderPass123!'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $this->bikeRider = RiderProfile::create([
            'user_id' => $bikeRiderUser->id,
            'logistics_company_id' => $this->logisticsCompany->id,
            'assigned_lga_id' => $this->uyoLga->id,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'UYO-123-BK',
            'is_active' => true,
            'is_online' => true,
        ]);

        $kekeRiderUser = User::create([
            'name' => 'Keke Cargo Edet',
            'email' => 'edet.cargo@swiftuyo.ng',
            'phone' => '08055555555',
            'password' => bcrypt('RiderPass123!'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $this->kekeRider = RiderProfile::create([
            'user_id' => $kekeRiderUser->id,
            'logistics_company_id' => $this->logisticsCompany->id,
            'assigned_lga_id' => $this->uyoLga->id,
            'vehicle_type' => 'keke',
            'license_plate' => 'UYO-456-KK',
            'is_active' => true,
            'is_online' => true,
        ]);

        // Intra-Uyo Delivery Lane
        $this->intraUyoLane = DeliveryLane::create([
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'assigned_logistics_company_id' => $this->logisticsCompany->id,
            'base_fee' => '1500.00',
            'bulky_surcharge' => '800.00',
            'logistics_company_base_payable' => '1200.00',
            'logistics_company_bulky_surcharge' => '500.00',
            'estimated_delivery_hours_min' => 1,
            'estimated_delivery_hours_max' => 4,
            'is_active' => true,
        ]);

        // Suppliers
        $vendorUserA = User::create([
            'name' => 'Akpan Wholesale Merchant',
            'email' => 'akpan@merchant.ng',
            'phone' => '08066666666',
            'password' => bcrypt('MerchantPass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->supplierA = VendorProfile::create([
            'user_id' => $vendorUserA->id,
            'business_name' => 'Akpan Wholesalers Ltd',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
        ]);
        $this->shopA = Shop::create([
            'vendor_id' => $this->supplierA->id,
            'country_id' => $country->id,
            'state_id' => $this->akwaIbom->id,
            'lga_id' => $this->uyoLga->id,
            'name' => 'Akpan Main Market Depot',
            'slug' => 'akpan-main-market-depot',
            'address' => '12 Oron Road, Uyo',
            'phone' => '08066666666',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $vendorUserB = User::create([
            'name' => 'Bassey Trade Hub',
            'email' => 'bassey@tradehub.ng',
            'phone' => '08077777777',
            'password' => bcrypt('MerchantPass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->supplierB = VendorProfile::create([
            'user_id' => $vendorUserB->id,
            'business_name' => 'Bassey Trade Hub Ltd',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
        ]);
        $this->shopB = Shop::create([
            'vendor_id' => $this->supplierB->id,
            'country_id' => $country->id,
            'state_id' => $this->akwaIbom->id,
            'lga_id' => $this->uyoLga->id,
            'name' => 'Bassey Wholesale Center',
            'slug' => 'bassey-wholesale-center',
            'address' => '45 Ikot Ekpene Road, Uyo',
            'phone' => '08077777777',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $this->customerAddress = \App\Models\CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'Ekaette Bassey',
            'phone' => '08022222222',
            'address_line' => '5 Nelson Mandela Street, Uyo',
            'country_id' => $country->id,
            'state_id' => $this->akwaIbom->id,
            'lga_id' => $this->uyoLga->id,
            'is_default' => true,
        ]);

        // Products
        $category = \App\Models\Category::create([
            'name' => 'Food & Groceries',
            'slug' => 'food-groceries',
            'is_active' => true,
        ]);

        $this->product1 = Product::create([
            'category_id' => $category->id,
            'name' => '50kg Royal Rice Bag',
            'slug' => '50kg-royal-rice-bag',
            'unit_price' => '45000.00',
            'package_tier' => 'bulky',
            'condition' => 'brand_new',
            'inventory_quantity' => 100,
            'status' => 'active',
            'is_verified' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'is_in_stock' => true,
            'listing_confirmed_at' => Carbon::now()->subMinute(),
        ]);

        $this->product2 = Product::create([
            'category_id' => $category->id,
            'name' => 'Pure Groundnut Oil 25L',
            'slug' => 'pure-groundnut-oil-25l',
            'unit_price' => '32000.00',
            'package_tier' => 'keke',
            'condition' => 'brand_new',
            'inventory_quantity' => 100,
            'status' => 'active',
            'is_verified' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'is_in_stock' => true,
            'listing_confirmed_at' => Carbon::now()->subMinute(),
        ]);
    }

    /**
     * Helper to create a confirmed eligible supplier offer
     */
    protected function createConfirmedOffer(Product $product, VendorProfile $vendor, Shop $shop, string $wholesaleCost): SupplierOffer
    {
        return SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'wholesale_cost' => $wholesaleCost,
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now()->subMinute(),
            'availability_confirmed_by' => $this->superAdmin->id,
            'availability_expires_at' => Carbon::now()->addDays(7),
        ]);
    }

    /**
     * 1. Consolidated Sourcing Law: Fewest distinct pickup locations first.
     * Supplier A has both Product 1 (₦40,000) and Product 2 (₦30,000) -> Total 1 shop stop.
     * Supplier B has Product 1 cheaper (₦38,000), but only Supplier A has Product 2.
     * Choosing Supplier B for Product 1 would fragment into 2 stops (Shop A + Shop B).
     * The law mandates selecting 1 consolidated stop at Shop A.
     */
    public function test_consolidated_sourcing_law_prioritizes_fewest_distinct_shops_first(): void
    {
        // Supplier A offers both products
        $this->createConfirmedOffer($this->product1, $this->supplierA, $this->shopA, '40000.00');
        $this->createConfirmedOffer($this->product2, $this->supplierA, $this->shopA, '30000.00');

        // Supplier B offers Product 1 slightly cheaper, but does NOT offer Product 2
        $this->createConfirmedOffer($this->product1, $this->supplierB, $this->shopB, '38000.00');

        $sourcingService = app(SourcingAllocationService::class);
        $plan = $sourcingService->resolveOptimalWholeOrderPlan([
            ['product' => $this->product1, 'quantity' => 1],
            ['product' => $this->product2, 'quantity' => 1],
        ], 'doorstep_delivery');

        $this->assertNotNull($plan);
        $this->assertSame(1, $plan['locations_count'], 'Consolidated plan must select 1 single pickup location.');
        $this->assertEquals([$this->shopA->id], $plan['distinct_shop_ids']);
        $this->assertSame('70000.00', $plan['total_wholesale_cost']);

        // Both items must be allocated to Supplier A
        $this->assertSame($this->supplierA->id, $plan['items'][0]['resolved_offer']['vendor_id']);
        $this->assertSame($this->supplierA->id, $plan['items'][1]['resolved_offer']['vendor_id']);
    }

    /**
     * 2. Supplier under active 24-hr SKU penalty is excluded from sourcing allocation.
     */
    public function test_supplier_under_24h_sku_penalty_is_excluded_from_allocation(): void
    {
        $offerA = $this->createConfirmedOffer($this->product1, $this->supplierA, $this->shopA, '35000.00');
        // Supplier A is penalized on Product 1
        $offerA->unavailability_penalty_expires_at = Carbon::now()->addHours(12);
        $offerA->save();

        // Supplier B offers Product 1 with no penalty
        $this->createConfirmedOffer($this->product1, $this->supplierB, $this->shopB, '37000.00');

        $sourcingService = app(SourcingAllocationService::class);
        $plan = $sourcingService->resolveOptimalWholeOrderPlan([
            ['product' => $this->product1, 'quantity' => 1],
        ], 'doorstep_delivery');

        $this->assertNotNull($plan);
        $this->assertSame($this->supplierB->id, $plan['items'][0]['resolved_offer']['vendor_id'], 'Penalized supplier must be excluded.');
    }

    /**
     * 3. Supplier with 5 or more active pending handovers is capacity-capped.
     */
    public function test_supplier_with_five_or_more_pending_handovers_is_excluded(): void
    {
        $this->createConfirmedOffer($this->product1, $this->supplierA, $this->shopA, '35000.00');
        $this->createConfirmedOffer($this->product1, $this->supplierB, $this->shopB, '38000.00');

        // Create 5 active pending handovers across distinct orders for Supplier A
        for ($i = 0; $i < 5; $i++) {
            $dummyOrder = Order::create([
                'order_number' => 'VM-TEST-DUMMY-' . $i,
                'customer_id' => $this->customer->id,
                'vendor_id' => $this->supplierA->id,
                'shop_id' => $this->shopA->id,
                'origin_lga_id' => $this->uyoLga->id,
                'destination_lga_id' => $this->uyoLga->id,
                'retailer_model_order' => true,
                'order_status' => 'confirmed',
                'payment_status' => 'paid',
                'sourcing_status' => 'awaiting_supplier_confirmation',
                'fulfillment_type' => 'doorstep_delivery',
                'delivery_fee' => '1500.00',
                'vendor_net_90' => '0.00',
                'customer_cashback_5' => '0.00',
                'platform_margin_5' => '0.00',
                'merchandise_subtotal' => '10000.00',
                'grand_total' => '10000.00',
            ]);

            OrderSupplierAssignment::create([
                'order_id' => $dummyOrder->id,
                'supplier_vendor_id' => $this->supplierA->id,
                'supplier_shop_id' => $this->shopA->id,
                'status' => 'ready_for_collection',
            ]);
        }

        $sourcingService = app(SourcingAllocationService::class);
        $plan = $sourcingService->resolveOptimalWholeOrderPlan([
            ['product' => $this->product1, 'quantity' => 1],
        ], 'doorstep_delivery');

        $this->assertNotNull($plan);
        $this->assertSame($this->supplierB->id, $plan['items'][0]['resolved_offer']['vendor_id'], 'Supplier at capacity limit must be excluded.');
    }

    /**
     * 4. Post-payment stockout routes into stockout_revalidation_hold, NOT automated refund.
     */
    public function test_post_payment_stockout_routes_to_stockout_revalidation_hold_without_refund(): void
    {
        $offerA = $this->createConfirmedOffer($this->product1, $this->supplierA, $this->shopA, '40000.00');

        // Customer checkouts
        $checkoutService = app(CheckoutExecutionService::class);
        $checkoutResult = $checkoutService->execute($this->customer, [
            'fulfillment_type' => 'doorstep_delivery',
            'customer_address_id' => $this->customerAddress->id,
            'payment_method' => 'paystack',
            'items' => [
                ['product_id' => $this->product1->id, 'quantity' => 1],
            ],
        ]);

        $this->assertTrue($checkoutResult['success']);
        $order = $checkoutResult['order'];
        $this->assertSame('awaiting_payment', $order->sourcing_status);

        // Before payment settles, Supplier A marks offer unavailable / penalized
        $offerA->is_available = false;
        $offerA->save();

        // Customer payment webhook arrives
        $settlementService = app(OrderSettlementService::class);
        $settleResult = $settlementService->settlePaystackOrder(
            $order,
            [
                'reference' => $order->payment_reference,
                'status' => 'success',
                'amount' => (int) bcmul((string) $order->grand_total, '100'),
                'currency' => 'NGN',
                'channel' => 'card',
            ],
            $this->customer
        );

        $this->assertTrue($settleResult['success']);
        $order->refresh();

        // Must transition to stockout_revalidation_hold, NOT supplier_confirmed or automated refund
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('stockout_revalidation_hold', $order->sourcing_status);
        $this->assertStringContainsString('No eligible Uyo supplier plan remains', $order->sourcing_notes);

        // No supplier assignment is created when post-payment revalidation finds
        // no eligible offer. The paid order remains held for admin recovery.
        $this->assertSame(0, OrderSupplierAssignment::where('order_id', $order->id)->count());

        // Admin audit log was created
        $audit = AdminAuditLog::where('target_id', $order->id)
            ->where('action', 'supplier_stockout_revalidation_hold')
            ->first();
        $this->assertNotNull($audit);
    }

    /**
     * 5. Logistics Rider Assignment: Bulky orders require cargo vehicle dispatch (keke/van)
     * and strictly block motorcycle/bicycle riders.
     */
    public function test_logistics_rider_assignment_enforces_cargo_vehicle_for_bulky_items(): void
    {
        $this->createConfirmedOffer($this->product1, $this->supplierA, $this->shopA, '40000.00');

        $checkoutService = app(CheckoutExecutionService::class);
        $checkoutResult = $checkoutService->execute($this->customer, [
            'fulfillment_type' => 'doorstep_delivery',
            'customer_address_id' => $this->customerAddress->id,
            'payment_method' => 'paystack',
            'items' => [
                ['product_id' => $this->product1->id, 'quantity' => 1], // bulky item
            ],
        ]);

        $this->assertTrue($checkoutResult['success']);
        $order = $checkoutResult['order'];

        // Settle payment
        app(OrderSettlementService::class)->settlePaystackOrder(
            $order,
            [
                'reference' => $order->payment_reference,
                'status' => 'success',
                'amount' => (int) bcmul((string) $order->grand_total, '100'),
                'currency' => 'NGN',
                'channel' => 'card',
            ],
            $this->customer
        );
        $order->refresh();

        // Logistics company tries to assign bike rider to bulky order -> BLOCKED
        $this->actingAs($this->logisticsCompany->user);
        $response = $this->post(route('logistics.orders.assign_rider', $order->id), [
            'rider_id' => $this->bikeRider->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('cargo dispatch', session('error'));
        $this->assertNull($order->fresh()->rider_id);

        // Logistics company assigns keke cargo rider -> SUCCEEDS
        $successResponse = $this->post(route('logistics.orders.assign_rider', $order->id), [
            'rider_id' => $this->kekeRider->id,
        ]);

        $successResponse->assertSessionHas('status');
        $this->assertSame($this->kekeRider->id, $order->fresh()->rider_id);
    }

    /**
     * 6. Mandatory Courier Pickup Photo Proof is strictly required for retailer orders.
     */
    public function test_mandatory_courier_pickup_photo_proof_is_enforced(): void
    {
        $this->createConfirmedOffer($this->product1, $this->supplierA, $this->shopA, '40000.00');

        $checkoutService = app(CheckoutExecutionService::class);
        $checkoutResult = $checkoutService->execute($this->customer, [
            'fulfillment_type' => 'doorstep_delivery',
            'customer_address_id' => $this->customerAddress->id,
            'payment_method' => 'paystack',
            'items' => [
                ['product_id' => $this->product1->id, 'quantity' => 1],
            ],
        ]);
        $this->assertTrue($checkoutResult['success']);
        $order = $checkoutResult['order'];

        app(OrderSettlementService::class)->settlePaystackOrder(
            $order,
            [
                'reference' => $order->payment_reference,
                'status' => 'success',
                'amount' => (int) bcmul((string) $order->grand_total, '100'),
                'currency' => 'NGN',
                'channel' => 'card',
            ],
            $this->customer
        );

        $order->rider_id = $this->kekeRider->id;
        $order->order_status = 'ready_for_pickup';
        $order->save();

        $assignment = OrderSupplierAssignment::where('order_id', $order->id)->first();
        $assignment->status = 'ready_for_collection';
        $assignment->supplier_pickup_code = '123456';
        $assignment->save();

        $settlementService = app(OrderSettlementService::class);

        // Verification without photo proof -> FAILS
        $resultNoPhoto = $settlementService->verifyRiderPickupOtp(
            $order,
            '123456',
            $this->kekeRider,
            null // no photo
        );

        $this->assertFalse($resultNoPhoto['success']);
        $this->assertStringContainsString('Mandatory courier pickup photo proof', $resultNoPhoto['message']);
        $this->assertSame('ready_for_collection', $assignment->fresh()->status);

        // Verification with photo proof -> SUCCEEDS
        $resultWithPhoto = $settlementService->verifyRiderPickupOtp(
            $order,
            '123456',
            $this->kekeRider,
            '/storage/pickup_proofs/sample.webp',
            'Sealed package verified and collected.'
        );

        $this->assertTrue($resultWithPhoto['success']);
        $this->assertSame('collected', $assignment->fresh()->status);
        $this->assertSame('out_for_delivery', $order->fresh()->order_status);
    }

    /**
     * 7. Sourcing Confirmation SLA: 60 operating minutes during 8:00 AM – 6:00 PM WAT.
     * Pauses at 6:00 PM and applies 24-hr penalty on timeout breach.
     */
    public function test_sourcing_confirmation_sla_pauses_outside_window_and_times_out(): void
    {
        $offerA = $this->createConfirmedOffer($this->product1, $this->supplierA, $this->shopA, '40000.00');

        $order = Order::create([
            'order_number' => 'VM-TEST-SLA-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->supplierA->id,
            'shop_id' => $this->shopA->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'retailer_model_order' => true,
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'sourcing_status' => 'awaiting_supplier_confirmation',
            'fulfillment_type' => 'doorstep_delivery',
            'delivery_fee' => '1500.00',
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '0.00',
            'platform_margin_5' => '0.00',
            'merchandise_subtotal' => '45000.00',
            'grand_total' => '46500.00',
        ]);

        $assignment = OrderSupplierAssignment::create([
            'order_id' => $order->id,
            'supplier_vendor_id' => $this->supplierA->id,
            'supplier_shop_id' => $this->shopA->id,
            'status' => 'awaiting_supplier_confirmation',
            'assigned_at' => Carbon::parse('2026-10-22 17:30:00', 'Africa/Lagos'), // 5:30 PM (30 min before pause)
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'quantity' => 1,
            'unit_price' => '45000.00',
            'line_total' => '45000.00',
            'supplier_vendor_id' => $this->supplierA->id,
            'supplier_shop_id' => $this->shopA->id,
            'supplier_cost_snapshot' => '40000.00',
            'package_tier' => 'bulky',
        ]);

        $command = app(CheckSupplierConfirmationSlaCommand::class);

        // At 19:30 PM same day (7:30 PM): Only 30 operating minutes elapsed (timer paused at 18:00)
        $elapsedEvening = $command->calculateOperatingMinutes(
            Carbon::parse('2026-10-22 17:30:00', 'Africa/Lagos'),
            Carbon::parse('2026-10-22 19:30:00', 'Africa/Lagos')
        );
        $this->assertSame(30, $elapsedEvening, 'Evening timer must pause at 6:00 PM.');

        // At 08:45 AM next morning: 30 min from previous day + 45 min today = 75 min (breached!)
        $elapsedNextDay = $command->calculateOperatingMinutes(
            Carbon::parse('2026-10-22 17:30:00', 'Africa/Lagos'),
            Carbon::parse('2026-10-23 08:45:00', 'Africa/Lagos')
        );
        $this->assertSame(75, $elapsedNextDay, 'Morning resumption must accurately accumulate active operating minutes.');

        // Deterministically simulate 10:00 AM WAT next day (breaching the 60-min SLA)
        Carbon::setTestNow(Carbon::parse('2026-10-23 10:00:00', 'Africa/Lagos'));
        $assignment->assigned_at = Carbon::parse('2026-10-23 08:00:00', 'Africa/Lagos');
        $assignment->save();

        Artisan::call('supplier:check-confirmation-sla');

        $assignment->refresh();
        $order->refresh();
        $offerA->refresh();

        Carbon::setTestNow(null); // Reset mocked time

        $this->assertSame('timed_out', $assignment->status);
        $this->assertSame('timed_out', $order->sourcing_status);
        $this->assertTrue($offerA->isPenaltyActive(), '24-hour SKU penalty must be active on breached offer.');
    }

    /**
     * 8. Combinatorial Scaling: 5 items with 10 suppliers each (would be 10^5 = 100,000 combinations).
     * Pruning and consolidation sorting bound combinations without CPU/memory exhaustion.
     */
    public function test_combinatorial_pruning_prevents_cartesian_explosion_on_multi_item_many_supplier_order(): void
    {
        $category = \App\Models\Category::first();
        $products = [];
        for ($p = 0; $p < 5; $p++) {
            $products[] = Product::create([
                'category_id' => $category->id,
                'name' => "Scaling Product SKU {$p}",
                'slug' => "scaling-product-sku-{$p}",
                'unit_price' => '10000.00',
                'package_tier' => 'bike',
                'condition' => 'brand_new',
                'inventory_quantity' => 100,
                'status' => 'active',
                'is_verified' => true,
                'is_approved' => true,
                'listing_status' => 'listed',
                'is_in_stock' => true,
                'listing_confirmed_at' => Carbon::now()->subMinute(),
            ]);
        }

        // Create 10 distinct suppliers and approved shops in Uyo
        $suppliers = [];
        for ($s = 0; $s < 10; $s++) {
            $u = User::create([
                'name' => "Supplier {$s}",
                'email' => "supplier{$s}_scale@test.ng",
                'phone' => "0808888000{$s}",
                'password' => bcrypt('Pass123!'),
                'user_type' => 'vendor',
                'status' => 'active',
            ]);
            $vp = VendorProfile::create([
                'user_id' => $u->id,
                'business_name' => "Supplier Corp {$s}",
                'kyc_status' => 'verified',
                'marketplace_status' => 'active',
            ]);
            $sh = Shop::create([
                'vendor_id' => $vp->id,
                'country_id' => $this->country->id,
                'state_id' => $this->akwaIbom->id,
                'lga_id' => $this->uyoLga->id,
                'name' => "Depot {$s}",
                'slug' => "depot-{$s}-scale",
                'address' => "Address {$s}, Uyo",
                'phone' => "0808888000{$s}",
                'is_active' => true,
                'pickup_enabled' => true,
            ]);
            $suppliers[] = ['vendor' => $vp, 'shop' => $sh];
        }

        // Supplier 0 offers ALL 5 products (making Supplier 0 the single-stop candidate)
        for ($p = 0; $p < 5; $p++) {
            $this->createConfirmedOffer($products[$p], $suppliers[0]['vendor'], $suppliers[0]['shop'], '8000.00');
        }

        // Other 9 suppliers offer some items at various prices (fragmented)
        for ($s = 1; $s < 10; $s++) {
            for ($p = 0; $p < 4; $p++) {
                $this->createConfirmedOffer($products[$p], $suppliers[$s]['vendor'], $suppliers[$s]['shop'], '7500.00');
            }
        }

        $items = array_map(fn($prod) => ['product' => $prod, 'quantity' => 1], $products);

        $startTime = microtime(true);
        $sourcingService = app(SourcingAllocationService::class);
        $plan = $sourcingService->resolveOptimalWholeOrderPlan($items, 'doorstep_delivery');
        $duration = microtime(true) - $startTime;

        $this->assertNotNull($plan);
        $this->assertLessThan(1.0, $duration, 'Pruned resolution must complete in under 1 second.');
        // Single-stop fast-path must have identified Supplier 0 as the sole consolidated 1-stop plan
        $this->assertSame(1, $plan['locations_count'], 'Must consolidate into 1 single stop at Supplier 0.');
        $this->assertSame($suppliers[0]['shop']->id, $plan['distinct_shop_ids'][0]);
    }

    /**
     * 9. Concurrency: Two orders competing for the last inventory unit.
     * Settle Order A succeeds -> inventory becomes 0.
     * Settle Order B detects depleted stock -> routes to stockout_revalidation_hold without double deduction or automated refund.
     */
    public function test_concurrent_payment_settlement_on_last_inventory_unit_routes_to_hold_without_double_deduction(): void
    {
        $category = \App\Models\Category::first();
        $singleUnitProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Scarce Solar Generator 1KW',
            'slug' => 'scarce-solar-generator-1kw',
            'unit_price' => '250000.00',
            'package_tier' => 'bulky',
            'condition' => 'brand_new',
            'inventory_quantity' => 1, // EXACTLY 1 UNIT LEFT
            'status' => 'active',
            'is_verified' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'is_in_stock' => true,
            'listing_confirmed_at' => Carbon::now()->subMinute(),
        ]);

        $offer = $this->createConfirmedOffer($singleUnitProduct, $this->supplierA, $this->shopA, '220000.00');

        // Order 1 for the unit
        $order1 = Order::create([
            'order_number' => 'VM-RACE-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->supplierA->id,
            'shop_id' => $this->shopA->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'retailer_model_order' => true,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'sourcing_status' => 'awaiting_payment',
            'fulfillment_type' => 'doorstep_delivery',
            'delivery_fee' => '1500.00',
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '0.00',
            'platform_margin_5' => '0.00',
            'merchandise_subtotal' => '250000.00',
            'grand_total' => '251500.00',
            'supplier_cost_total_snapshot' => '220000.00',
            'payment_reference' => 'REF-RACE-01',
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $singleUnitProduct->id,
            'product_name' => $singleUnitProduct->name,
            'quantity' => 1,
            'unit_price' => '250000.00',
            'line_total' => '250000.00',
            'supplier_offer_id' => $offer->id,
            'supplier_vendor_id' => $this->supplierA->id,
            'supplier_shop_id' => $this->shopA->id,
            'supplier_cost_snapshot' => '220000.00',
            'package_tier' => 'bulky',
        ]);

        OrderSupplierAssignment::create([
            'order_id' => $order1->id,
            'supplier_vendor_id' => $this->supplierA->id,
            'supplier_shop_id' => $this->shopA->id,
            'status' => 'awaiting_payment',
        ]);

        // Order 2 for the same unit
        $order2 = Order::create([
            'order_number' => 'VM-RACE-02',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->supplierA->id,
            'shop_id' => $this->shopA->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'retailer_model_order' => true,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'sourcing_status' => 'awaiting_payment',
            'fulfillment_type' => 'doorstep_delivery',
            'delivery_fee' => '1500.00',
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '0.00',
            'platform_margin_5' => '0.00',
            'merchandise_subtotal' => '250000.00',
            'grand_total' => '251500.00',
            'supplier_cost_total_snapshot' => '220000.00',
            'payment_reference' => 'REF-RACE-02',
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $singleUnitProduct->id,
            'product_name' => $singleUnitProduct->name,
            'quantity' => 1,
            'unit_price' => '250000.00',
            'line_total' => '250000.00',
            'supplier_offer_id' => $offer->id,
            'supplier_vendor_id' => $this->supplierA->id,
            'supplier_shop_id' => $this->shopA->id,
            'supplier_cost_snapshot' => '220000.00',
            'package_tier' => 'bulky',
        ]);

        OrderSupplierAssignment::create([
            'order_id' => $order2->id,
            'supplier_vendor_id' => $this->supplierA->id,
            'supplier_shop_id' => $this->shopA->id,
            'status' => 'awaiting_payment',
        ]);

        $settlementService = app(OrderSettlementService::class);

        // Step A: Payment for Order 1 arrives and settles
        $result1 = $settlementService->settlePaystackOrder(
            $order1,
            [
                'reference' => 'REF-RACE-01',
                'status' => 'success',
                'amount' => 25150000,
                'currency' => 'NGN',
                'channel' => 'card',
            ],
            $this->customer
        );

        $this->assertTrue($result1['success']);
        $order1->refresh();
        $this->assertSame('paid', $order1->payment_status);
        $this->assertSame('awaiting_supplier_confirmation', $order1->sourcing_status);

        // Stock quantity should now be 0 (depleted)
        $singleUnitProduct->refresh();
        $this->assertSame(0, (int) $singleUnitProduct->inventory_quantity);

        // Step B: Payment for Order 2 arrives for the same depleted SKU
        $result2 = $settlementService->settlePaystackOrder(
            $order2,
            [
                'reference' => 'REF-RACE-02',
                'status' => 'success',
                'amount' => 25150000,
                'currency' => 'NGN',
                'channel' => 'card',
            ],
            $this->customer
        );

        $this->assertTrue($result2['success']);
        $order2->refresh();

        // Order 2 is paid, but caught by post-payment revalidation -> routes to hold
        $this->assertSame('paid', $order2->payment_status);
        $this->assertSame('stockout_revalidation_hold', $order2->sourcing_status);

        // Inventory must NEVER become negative
        $singleUnitProduct->refresh();
        $this->assertSame(0, (int) $singleUnitProduct->inventory_quantity);

        // An audit log was written for Order 2 hold
        $audit = AdminAuditLog::where('target_id', $order2->id)
            ->where('action', 'supplier_stockout_revalidation_hold')
            ->first();
        $this->assertNotNull($audit);
    }
}
