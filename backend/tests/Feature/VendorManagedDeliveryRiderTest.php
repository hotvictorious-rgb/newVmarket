<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\DoorstepHandshakeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorManagedDeliveryRiderTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendorUserA;
    protected VendorProfile $vendorA;
    protected Shop $shopA;

    protected User $vendorUserB;
    protected VendorProfile $vendorB;
    protected Shop $shopB;

    protected User $customer;
    protected Product $productA;
    protected Lga $uyoLga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLocationsAndMerchants();
    }

    private function seedLocationsAndMerchants(): void
    {
        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);

        $state = State::create([
            'country_id' => $country->id,
            'name' => 'Akwa Ibom',
            'code' => 'AK',
            'is_active' => true,
        ]);

        $this->uyoLga = Lga::create([
            'state_id' => $state->id,
            'name' => 'Uyo',
            'code' => 'UYO',
            'is_active' => true,
        ]);

        // Customer
        $this->customer = User::create([
            'name' => 'Emem Customer',
            'email' => 'emem@example.com',
            'phone' => '08011112222',
            'password' => bcrypt('Password123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        // Vendor A
        $this->vendorUserA = User::create([
            'name' => 'Bassey Merchant',
            'email' => 'bassey@vendora.ng',
            'phone' => '08033334444',
            'password' => bcrypt('Password123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendorA = VendorProfile::create([
            'user_id' => $this->vendorUserA->id,
            'business_name' => 'Bassey Groceries',
            'marketplace_status' => 'active',
        ]);

        $this->shopA = Shop::create([
            'vendor_id' => $this->vendorA->id,
            'name' => 'Bassey Main Shop',
            'slug' => 'bassey-main-shop',
            'address' => '12 Oron Road, Uyo',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'is_active' => true,
        ]);

        $category = \App\Models\Category::create([
            'name' => 'Groceries',
            'slug' => 'groceries',
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'category_id' => $category->id,
            'name' => 'Fresh Palm Oil 5L',
            'slug' => 'fresh-palm-oil-5l',
            'unit_price' => '10000.00',
            'inventory_quantity' => 50,
            'is_active' => true,
            'approval_status' => 'approved',
            'is_available_for_order' => true,
        ]);

        // Vendor B
        $this->vendorUserB = User::create([
            'name' => 'Anietie Merchant',
            'email' => 'anietie@vendorb.ng',
            'phone' => '08055556666',
            'password' => bcrypt('Password123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendorB = VendorProfile::create([
            'user_id' => $this->vendorUserB->id,
            'business_name' => 'Anietie Electronics',
            'marketplace_status' => 'active',
        ]);

        $this->shopB = Shop::create([
            'vendor_id' => $this->vendorB->id,
            'name' => 'Anietie Plaza Shop',
            'slug' => 'anietie-plaza-shop',
            'address' => '5 Ikot Ekpene Road, Uyo',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Vendor can onboard their own delivery rider
     */
    public function test_vendor_can_add_and_list_their_own_delivery_riders(): void
    {
        $response = $this->actingAs($this->vendorUserA, 'sanctum')->postJson('/api/v1/vendor/riders', [
            'name' => 'Iniobong Dispatch',
            'phone' => '08077778888',
            'email' => 'iniobong@bassey.ng',
            'password' => 'SecurePass123!',
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'UYO-112-AK',
            'shop_id' => $this->shopA->id,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => true,
            'message' => 'Delivery rider successfully added to your merchant fleet.',
            'data' => [
                'name' => 'Iniobong Dispatch',
                'phone' => '08077778888',
                'vehicle_type' => 'motorcycle',
                'is_active' => true,
            ],
        ]);

        $riderId = $response->json('data.id');
        $rider = RiderProfile::findOrFail($riderId);

        // Assert rider belongs to Vendor A
        $this->assertEquals($this->vendorA->id, $rider->vendor_id);
        $this->assertEquals($this->shopA->id, $rider->shop_id);
        $this->assertTrue($rider->isVendorRider());
        $this->assertFalse($rider->isFleetRider());

        // Vendor lists their riders
        $listResponse = $this->actingAs($this->vendorUserA, 'sanctum')->getJson('/api/v1/vendor/riders');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonCount(1, 'data');
        $this->assertEquals('Iniobong Dispatch', $listResponse->json('data.0.name'));

        // Vendor B sees 0 riders
        $vendorBList = $this->actingAs($this->vendorUserB, 'sanctum')->getJson('/api/v1/vendor/riders');
        $vendorBList->assertStatus(200);
        $vendorBList->assertJsonCount(0, 'data');
    }

    /**
     * Test 2: Vendor can toggle rider active status
     */
    public function test_vendor_can_activate_and_deactivate_their_rider(): void
    {
        $riderUser = User::create([
            'name' => 'Kufre Rider',
            'email' => 'kufre@bassey.ng',
            'phone' => '08099990000',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $rider = RiderProfile::create([
            'user_id' => $riderUser->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
        ]);

        // Deactivate rider
        $response = $this->actingAs($this->vendorUserA, 'sanctum')->patchJson("/api/v1/vendor/riders/{$rider->id}/status", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $rider->refresh();
        $this->assertFalse((bool)$rider->is_active);

        // Vendor B cannot modify Vendor A's rider
        $unauthorized = $this->actingAs($this->vendorUserB, 'sanctum')->patchJson("/api/v1/vendor/riders/{$rider->id}/status", [
            'is_active' => true,
        ]);
        $unauthorized->assertStatus(404);
    }

    /**
     * Test 3: Vendor assigns their own rider to their order
     */
    public function test_vendor_can_assign_their_rider_to_order_and_blocks_cross_vendor_riders(): void
    {
        // Rider belonging to Vendor A
        $riderUserA = User::create([
            'name' => 'Vendor A Rider',
            'email' => 'riderA@bassey.ng',
            'phone' => '08012345678',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $riderA = RiderProfile::create([
            'user_id' => $riderUserA->id,
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);

        // Rider belonging to Vendor B
        $riderUserB = User::create([
            'name' => 'Vendor B Rider',
            'email' => 'riderB@anietie.ng',
            'phone' => '08087654321',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $riderB = RiderProfile::create([
            'user_id' => $riderUserB->id,
            'vendor_id' => $this->vendorB->id,
            'is_active' => true,
        ]);

        // Create order for Vendor A
        $orderA = Order::create([
            'order_number' => 'VM-ORD-VND-001',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '11500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '1350.00',
            'platform_delivery_fee' => '150.00',
            'verification_code' => '445566',
        ]);

        // Vendor A attempts to assign Vendor B's rider -> strictly rejected (403)
        $rejectCross = $this->actingAs($this->vendorUserA, 'sanctum')->postJson("/api/v1/vendor/orders/{$orderA->id}/assign-rider", [
            'rider_id' => $riderB->id,
        ]);
        $rejectCross->assertStatus(403);
        $rejectCross->assertJson([
            'status' => false,
            'message' => 'Unauthorized: This delivery rider does not belong to your merchant account.',
        ]);

        // Vendor A assigns Vendor A's rider -> successful (200)
        $assignOk = $this->actingAs($this->vendorUserA, 'sanctum')->postJson("/api/v1/vendor/orders/{$orderA->id}/assign-rider", [
            'rider_id' => $riderA->id,
        ]);
        $assignOk->assertStatus(200);

        $orderA->refresh();
        $this->assertEquals($riderA->id, $orderA->rider_id);
    }

    /**
     * Test 4: Vendor-managed rider accesses rider terminal and completes doorstep delivery
     * with 90% delivery fee credited to merchant (vendor_payable), not 3rd party logistics
     */
    public function test_vendor_rider_executes_doorstep_handover_and_credits_merchant_delivery_fee(): void
    {
        $riderUser = User::create([
            'name' => 'Bassey Internal Dispatch',
            'email' => 'dispatch@bassey.ng',
            'phone' => '08022223333',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $rider = RiderProfile::create([
            'user_id' => $riderUser->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
            'is_online' => true,
        ]);

        // Rider accesses dashboard
        $dashRes = $this->actingAs($riderUser, 'sanctum')->getJson('/api/v1/rider/dashboard');
        $dashRes->assertStatus(200);
        $dashRes->assertJson([
            'status' => true,
            'data' => [
                'rider' => [
                    'id' => $rider->id,
                    'name' => 'Bassey Internal Dispatch',
                ],
            ],
        ]);

        // Create Order and OrderItem
        $order = Order::create([
            'order_number' => 'VM-ORD-DOORSTEP-VEND',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'rider_id' => $rider->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'payment_reference' => 'VM-PAY-TEST-001',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '2000.00',
            'grand_total' => '12000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '1800.00',
            'platform_delivery_fee' => '200.00',
            'verification_code' => '789123',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
        ]);

        // Execute doorstep handover via DoorstepHandshakeService
        $service = app(DoorstepHandshakeService::class);
        $manifest = [
            [
                'order_item_id' => $orderItem->id,
                'accepted_quantity' => 1,
                'rejected_quantity' => 0,
            ],
        ];

        $handoverResult = $service->processDoorstepHandover(
            $order,
            $rider,
            $manifest,
            '789123',
            'proofs/doorstep_delivered.jpg'
        );

        $this->assertTrue($handoverResult['success']);

        $order->refresh();
        $this->assertEquals('delivered', $order->order_status);

        // Verify financial entries: Delivery fee was credited to VendorProfile (vendor_payable)
        $deliveryEntry = FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'vendor_payable')
            ->where('event_id', 'like', '%-VEND-DELIVERY%')
            ->first();

        $this->assertNotNull($deliveryEntry);
        $this->assertEquals(0, bccomp((string)$deliveryEntry->amount, '1800.00', 2));
        $this->assertEquals(VendorProfile::class, $deliveryEntry->beneficiary_type);
        $this->assertEquals($this->vendorA->id, $deliveryEntry->beneficiary_id);

        // Platform 10% delivery commission = ₦200.00
        $platEntry = FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'platform_revenue')
            ->where('event_id', 'like', '%-PLAT-D%')
            ->first();
        $this->assertNotNull($platEntry);
        $this->assertEquals(0, bccomp((string)$platEntry->amount, '200.00', 2));
    }

    /**
     * Test 5: Unaffiliated rider without vendor or logistics company is rejected
     */
    public function test_unaffiliated_rider_cannot_access_rider_terminal(): void
    {
        $unaffiliatedUser = User::create([
            'name' => 'Rogue Rider',
            'email' => 'rogue@example.com',
            'phone' => '08099887766',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $rogueRider = RiderProfile::create([
            'user_id' => $unaffiliatedUser->id,
            'vendor_id' => null,
            'logistics_company_id' => null,
            'is_active' => true,
        ]);

        $res = $this->actingAs($unaffiliatedUser, 'sanctum')->getJson('/api/v1/rider/dashboard');
        $res->assertStatus(403);
        $res->assertJson([
            'status' => false,
            'message' => 'Unauthorized: Active delivery work requires one owning vendor; legacy fleet-only or ambiguously linked riders are not eligible.',
        ]);
    }

    /**
     * Test 6: Non-vendors (customers, unauthenticated users) cannot add riders
     */
    public function test_only_authenticated_vendors_can_add_riders_and_others_are_strictly_forbidden(): void
    {
        // Unauthenticated call
        $unauth = $this->postJson('/api/v1/vendor/riders', [
            'name' => 'Intruder Rider',
            'phone' => '08099991111',
            'password' => 'password123',
        ]);
        $unauth->assertStatus(401);

        // Customer attempts to call vendor rider creation
        $customerAttempt = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/vendor/riders', [
            'name' => 'Intruder Rider',
            'phone' => '08099991111',
            'password' => 'password123',
        ]);
        $customerAttempt->assertStatus(403);
    }

    private function createTestOrder(array $attributes = []): Order
    {
        $defaults = [
            'order_number' => 'VM-ORD-' . uniqid(),
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'merchandise_subtotal' => '5000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '6000.00',
            'vendor_net_90' => '4500.00',
            'customer_cashback_5' => '250.00',
            'platform_margin_5' => '250.00',
            'logistics_fee_retained' => '900.00',
            'platform_delivery_fee' => '100.00',
            'verification_code' => '123456',
        ];

        return Order::create(array_merge($defaults, $attributes));
    }

    /**
     * Test 7: Rider assignment fails if order is in-shop pickup or already in transit custody
     */
    public function test_rider_assignment_fails_if_order_is_pickup_or_already_in_transit_custody(): void
    {
        $riderUser = User::create([
            'name' => 'Assigned Test Rider',
            'email' => 'assigned@bassey.ng',
            'phone' => '08066667777',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $rider = RiderProfile::create([
            'user_id' => $riderUser->id,
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);

        // 1. Pickup order attempt
        $pickupOrder = $this->createTestOrder([
            'order_number' => 'VM-ORD-PICKUP-TEST',
            'fulfillment_type' => 'in_shop_pickup',
            'delivery_fee' => '0.00',
            'grand_total' => '5000.00',
            'logistics_fee_retained' => '0.00',
            'platform_delivery_fee' => '0.00',
        ]);

        $resPickup = $this->actingAs($this->vendorUserA, 'sanctum')->postJson("/api/v1/vendor/orders/{$pickupOrder->id}/assign-rider", [
            'rider_id' => $rider->id,
        ]);
        $resPickup->assertStatus(422);
        $resPickup->assertJsonFragment([
            'message' => 'Cannot assign delivery rider: this order is configured for in-shop pickup.',
        ]);

        // 2. Order already out for delivery / custody taken
        $inTransitOrder = $this->createTestOrder([
            'order_number' => 'VM-ORD-TRANSIT-TEST',
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
        ]);

        $resTransit = $this->actingAs($this->vendorUserA, 'sanctum')->postJson("/api/v1/vendor/orders/{$inTransitOrder->id}/assign-rider", [
            'rider_id' => $rider->id,
        ]);
        $resTransit->assertStatus(422);
        $this->assertStringContainsString('Reassignment is blocked once physical custody is taken', $resTransit->json('message'));
    }

    /**
     * Test 8: Cannot assign rider with legacy logistics_company_id
     */
    public function test_cannot_assign_rider_with_legacy_logistics_company_id(): void
    {
        $companyUser = User::create([
            'name' => 'Legacy Fleet Company',
            'email' => 'company@legacy.ng',
            'phone' => '08099112233',
            'password' => bcrypt('password'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);
        $company = \App\Models\LogisticsCompanyProfile::create([
            'user_id' => $companyUser->id,
            'company_name' => 'Legacy Fleet Ltd',
            'registration_number' => 'RC123456',
            'verification_status' => 'approved',
        ]);

        $fleetRiderUser = User::create([
            'name' => 'Legacy Fleet Rider',
            'email' => 'fleet@legacy.ng',
            'phone' => '08055554444',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $fleetRider = RiderProfile::create([
            'user_id' => $fleetRiderUser->id,
            'vendor_id' => $this->vendorA->id,
            'logistics_company_id' => $company->id,
            'is_active' => true,
        ]);

        $doorstepOrder = $this->createTestOrder([
            'order_number' => 'VM-ORD-DOORSTEP-TEST2',
            'fulfillment_type' => 'doorstep_delivery',
        ]);

        $res = $this->actingAs($this->vendorUserA, 'sanctum')->postJson("/api/v1/vendor/orders/{$doorstepOrder->id}/assign-rider", [
            'rider_id' => $fleetRider->id,
        ]);
        $res->assertStatus(403);
        $res->assertJsonFragment([
            'message' => 'Unauthorized: Legacy or fleet-linked riders cannot receive new vendor deliveries.',
        ]);
    }

    /**
     * Test 9: Vendor cannot deactivate rider holding parcels in transit custody
     */
    public function test_vendor_cannot_deactivate_rider_holding_parcels_in_transit_custody(): void
    {
        $riderUser = User::create([
            'name' => 'Active Custody Rider',
            'email' => 'custody@bassey.ng',
            'phone' => '08044443333',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $rider = RiderProfile::create([
            'user_id' => $riderUser->id,
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);

        // Order out for delivery assigned to this rider
        $this->createTestOrder([
            'order_number' => 'VM-ORD-CUSTODY-HELD',
            'rider_id' => $rider->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
        ]);

        $deactRes = $this->actingAs($this->vendorUserA, 'sanctum')->patchJson("/api/v1/vendor/riders/{$rider->id}/status", [
            'is_active' => false,
        ]);
        $deactRes->assertStatus(422);
        $this->assertStringContainsString('physical custody', $deactRes->json('message'));

        $rider->refresh();
        $this->assertTrue((bool)$rider->is_active);
    }

    /**
     * Test 10: Vendor web portal order assignment enforces multi-tenant isolation
     */
    public function test_vendor_can_assign_rider_via_web_portal_with_isolation(): void
    {
        $riderUserA = User::create([
            'name' => 'Web Rider A',
            'email' => 'webriderA@bassey.ng',
            'phone' => '08022221111',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $riderA = RiderProfile::create([
            'user_id' => $riderUserA->id,
            'vendor_id' => $this->vendorA->id,
            'is_active' => true,
        ]);

        $riderUserB = User::create([
            'name' => 'Web Rider B',
            'email' => 'webriderB@anietie.ng',
            'phone' => '08033332222',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $riderB = RiderProfile::create([
            'user_id' => $riderUserB->id,
            'vendor_id' => $this->vendorB->id,
            'is_active' => true,
        ]);

        $orderA = $this->createTestOrder([
            'order_number' => 'VM-ORD-WEB-001',
            'fulfillment_type' => 'doorstep_delivery',
        ]);

        // Vendor A attempts to assign Vendor B's rider via Web Portal
        $rejectCross = $this->actingAs($this->vendorUserA)->post("/vendor/orders/{$orderA->id}/assign-rider", [
            'rider_id' => $riderB->id,
        ]);
        $rejectCross->assertSessionHas('error');
        $orderA->refresh();
        $this->assertNull($orderA->rider_id);

        // Vendor A assigns their own rider via Web Portal
        $successAssign = $this->actingAs($this->vendorUserA)->post("/vendor/orders/{$orderA->id}/assign-rider", [
            'rider_id' => $riderA->id,
        ]);
        $successAssign->assertSessionHas('success');
        $orderA->refresh();
        $this->assertEquals($riderA->id, $orderA->rider_id);
    }
}
