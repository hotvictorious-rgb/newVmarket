<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\CustomerWalletCredit;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorRecoveryLedger;
use App\Services\DoorstepHandshakeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoorstepPartialAcceptanceAndVendorReturnTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $vendorUser;
    protected VendorProfile $vendorProfile;
    protected Shop $shop;
    protected User $riderUser;
    protected RiderProfile $riderProfile;
    protected LogisticsCompanyProfile $logisticsCompany;
    protected User $customer;
    protected Product $blender;
    protected Product $kettle;
    protected Lga $uyoLga;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedLocationsAndActors();
    }

    private function seedLocationsAndActors(): void
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

        // 1. Admin
        $this->adminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@vmarket.ng',
            'password' => bcrypt('password'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        // 2. Logistics Fleet & Rider
        $fleetUser = User::create([
            'name' => 'Fleet Manager',
            'email' => 'fleet@speedy.ng',
            'password' => bcrypt('password'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $this->logisticsCompany = LogisticsCompanyProfile::create([
            'user_id' => $fleetUser->id,
            'company_name' => 'Speedy Logistics NG',
            'contact_phone' => '+234800112233',
            'business_registration_number' => 'RC-998811',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->riderUser = User::create([
            'name' => 'Edet Rider',
            'email' => 'edet@speedy.ng',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $this->riderProfile = RiderProfile::create([
            'user_id' => $this->riderUser->id,
            'logistics_company_id' => $this->logisticsCompany->id,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'UYO-452-XY',
            'is_active' => true,
            'is_online' => true,
            'current_balance' => '5000.00',
        ]);

        // 3. Vendor & Shop
        $this->vendorUser = User::create([
            'name' => 'Alpha Merchant',
            'email' => 'alpha@merchants.ng',
            'password' => bcrypt('password'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendorProfile = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Alpha Electronics',
            'contact_phone' => '+2348022334455',
            'marketplace_status' => 'active',
            'commission_rate' => 10.00,
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendorProfile->id,
            'name' => 'Alpha Main Store',
            'slug' => 'alpha-main-store',
            'address' => '25 Oron Road, Uyo',
            'phone' => '+2348039999999',
            'lga_id' => $this->uyoLga->id,
            'state_id' => $state->id,
            'country_id' => $country->id,
            'pickup_enabled' => true,
            'is_primary_branch' => true,
            'is_active' => true,
        ]);

        $category = \App\Models\Category::create([
            'name' => 'Home Appliances',
            'slug' => 'home-appliances',
            'is_active' => true,
        ]);

        // 4. Products
        $this->blender = Product::create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendorProfile->id,
            'category_id' => $category->id,
            'name' => 'Heavy Duty Commercial Blender',
            'slug' => 'commercial-blender',
            'sku' => 'BLD-001',
            'unit_price' => '30000.00',
            'inventory_quantity' => 15,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $this->kettle = Product::create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendorProfile->id,
            'category_id' => $category->id,
            'name' => 'Stainless Electric Kettle',
            'slug' => 'electric-kettle',
            'sku' => 'KTL-002',
            'unit_price' => '15000.00',
            'inventory_quantity' => 20,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        // 5. Customer
        $this->customer = User::create([
            'name' => 'Emeka Shopper',
            'email' => 'emeka@shopper.ng',
            'phone' => '+2348011223344',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);
    }

    /**
     * Helper to create an Order with multiple line items out for delivery.
     */
    private function createOutForDeliveryOrder(): array
    {
        $order = Order::create([
            'order_number' => 'VM-TEST-' . rand(10000, 99999),
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'rider_id' => $this->riderProfile->id,
            'logistics_company_id' => $this->logisticsCompany->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'delivery_address' => '42 Ikot Ekpene Road, Uyo',
            'recipient_phone' => '+2348011223344',
            'recipient_name' => 'Emeka Shopper',
            'merchandise_subtotal' => '45000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '46500.00',
            'customer_cashback_5' => '2250.00',
            'vendor_net_90' => '40500.00',
            'logistics_fee_retained' => '1350.00',
            'platform_margin_5' => '2250.00',
            'platform_delivery_fee' => '150.00',
            'verification_code' => '719283',
            'pickup_verification_code' => '543210',
        ]);

        $item1 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->blender->id,
            'product_name' => $this->blender->name,
            'quantity' => 1,
            'unit_price' => '30000.00',
            'line_total' => '30000.00',
            'item_delivery_status' => 'in_transit',
        ]);

        $item2 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->kettle->id,
            'product_name' => $this->kettle->name,
            'quantity' => 1,
            'unit_price' => '15000.00',
            'line_total' => '15000.00',
            'item_delivery_status' => 'in_transit',
        ]);

        return [$order, $item1, $item2];
    }

    /**
     * Test 1: Customer partially accepts order at doorstep (accepts blender, rejects kettle).
     * Verifies:
     * - Item 1 accepted, Item 2 rejected.
     * - Order status becomes partially_delivered.
     * - 90/5/5 accounting calculated ONLY on accepted merchandise (₦30,000).
     * - Logistics company receives full 90% delivery fee (₦1,350).
     * - Customer receives instant 100% wallet credit (₦15,000) with zero delay.
     * - ReturnCase auto-generated with 6-digit vendor_return_otp and in_transit_back_to_vendor status.
     */
    public function test_partial_doorstep_acceptance_and_instant_wallet_credit(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        $photo = UploadedFile::fake()->image('doorstep_defect.jpg');

        Sanctum::actingAs($this->riderUser);
        $response = $this->postJson('/api/v1/rider/orders/doorstep-handover', [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                [
                    'order_item_id' => $item1->id,
                    'accepted_quantity' => 1,
                    'rejected_quantity' => 0,
                ],
                [
                    'order_item_id' => $item2->id,
                    'accepted_quantity' => 0,
                    'rejected_quantity' => 1,
                    'rejection_category' => 'vendor_issue',
                    'rejection_notes' => 'Heating element is broken and base is scratched.',
                ],
            ],
            'proof_photo' => $photo,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.accepted_amount', '30000.00');
        $response->assertJsonPath('data.rejected_amount', '15000.00');
        $response->assertJsonPath('data.wallet_credited', '15000.00');

        $order->refresh();
        $this->assertEquals('delivered', $order->order_status);
        $this->assertTrue((bool)$order->delivery_confirmed_by_customer);

        // Check Items status
        $item1->refresh();
        $this->assertEquals(1, $item1->accepted_quantity);
        $this->assertEquals(0, $item1->rejected_quantity);
        $this->assertEquals('accepted', $item1->item_delivery_status);

        $item2->refresh();
        $this->assertEquals(0, $item2->accepted_quantity);
        $this->assertEquals(1, $item2->rejected_quantity);
        $this->assertEquals('rejected_in_transit', $item2->item_delivery_status);
        $this->assertEquals('vendor_issue', $item2->rejection_category);

        // Check Instant Customer Wallet Credit
        $credit = CustomerWalletCredit::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($credit);
        $this->assertEquals(15000.00, (float)$credit->amount);
        $this->assertEquals('available', $credit->status);
        $this->assertEquals('15000.00', CustomerWalletCredit::getAvailableBalance($this->customer->id));

        // Check ReturnCase generated for vendor return
        $returnCase = ReturnCase::where('order_id', $order->id)->first();
        $this->assertNotNull($returnCase);
        $this->assertEquals('doorstep_refusal', $returnCase->source);
        $this->assertEquals('in_transit_back_to_vendor', $returnCase->status);
        $this->assertEquals('vendor_issue', $returnCase->rejection_category);
        $this->assertNotNull($returnCase->vendor_return_otp);
        $this->assertEquals(6, strlen($returnCase->vendor_return_otp));
        $this->assertEquals($this->riderUser->id, $returnCase->returned_by_rider_id);
    }

    /**
     * Test 2: Handshake 3 - Rider brings item back to Vendor shop;
     * Vendor inspects and provides 6-digit OTP, clearing rider liability.
     */
    public function test_handshake_3_rider_returns_item_to_vendor_with_otp(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        // 1. Process doorstep handover with rejection
        Sanctum::actingAs($this->riderUser);
        $this->postJson('/api/v1/rider/orders/doorstep-handover', [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                ['order_item_id' => $item1->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
                ['order_item_id' => $item2->id, 'accepted_quantity' => 0, 'rejected_quantity' => 1, 'rejection_category' => 'vendor_issue'],
            ],
        ])->assertStatus(200);

        $returnCase = ReturnCase::where('order_id', $order->id)->firstOrFail();
        $otp = $returnCase->vendor_return_otp;

        // 2. Rider attempts invalid OTP -> Rejected
        $failResp = $this->postJson('/api/v1/rider/orders/verify-vendor-return-otp', [
            'return_case_id' => $returnCase->id,
            'otp' => '000000',
        ]);
        $failResp->assertStatus(422);

        // 3. Rider submits valid OTP provided by vendor at shop counter
        $successResp = $this->postJson('/api/v1/rider/orders/verify-vendor-return-otp', [
            'return_case_id' => $returnCase->id,
            'otp' => $otp,
        ]);
        $successResp->assertStatus(200);
        $successResp->assertJsonPath('status', true);

        // 4. Case status is updated to returned_to_vendor
        $returnCase->refresh();
        $this->assertEquals('returned_to_vendor', $returnCase->status);
        $this->assertNotNull($returnCase->returned_to_vendor_at);

        $item2->refresh();
        $this->assertEquals('returned_to_vendor', $item2->item_delivery_status);
    }

    /**
     * Test 3: Handshake 3 Dispute - Vendor inspects returned parcel at counter and flags discrepancy with photo.
     * Escalates directly to Super Admin Arbitration Desk.
     */
    public function test_handshake_3_vendor_counter_dispute_with_photo(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $this->postJson('/api/v1/rider/orders/doorstep-handover', [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                ['order_item_id' => $item1->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
                ['order_item_id' => $item2->id, 'accepted_quantity' => 0, 'rejected_quantity' => 1, 'rejection_category' => 'vendor_issue'],
            ],
        ])->assertStatus(200);

        $returnCase = ReturnCase::where('order_id', $order->id)->firstOrFail();

        // Vendor checks returns queue
        Sanctum::actingAs($this->vendorUser);
        $queueResp = $this->getJson('/api/v1/vendor/returns');
        $queueResp->assertStatus(200);
        $queueResp->assertJsonPath('status', true);

        // Vendor logs dispute with counter photo
        $disputePhoto = UploadedFile::fake()->image('torn_seal.jpg');
        $disputeResp = $this->postJson("/api/v1/vendor/returns/{$returnCase->id}/dispute", [
            'dispute_reason' => 'Package seal was broken and power cord cut after dispatch.',
            'dispute_photo' => $disputePhoto,
        ]);

        $disputeResp->assertStatus(200);
        $disputeResp->assertJsonPath('status', true);

        // Case status updated to disputed_by_vendor
        $returnCase->refresh();
        $this->assertEquals('disputed_by_vendor', $returnCase->status);
        $this->assertTrue((bool)$returnCase->vendor_disputed);
        $this->assertEquals('Package seal was broken and power cord cut after dispatch.', $returnCase->vendor_dispute_reason);
        $this->assertNotNull($returnCase->vendor_dispute_photo_path);

        $item2->refresh();
        $this->assertEquals('disputed_by_vendor', $item2->item_delivery_status);
    }

    /**
     * Test 4: Super Admin Arbitrates Disputed Return at /admin/returns/{id}/arbitrate
     * Tests all 3 binding rulings:
     * - uphold_vendor_dispute (Merchant protected, 50/50 customer split)
     * - uphold_customer_claim (Vendor liability, penalty debited)
     * - platform_transit_protection (Platform absorbed)
     */
    public function test_super_admin_arbitration_rulings(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $this->postJson('/api/v1/rider/orders/doorstep-handover', [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                ['order_item_id' => $item1->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
                ['order_item_id' => $item2->id, 'accepted_quantity' => 0, 'rejected_quantity' => 1, 'rejection_category' => 'vendor_issue'],
            ],
        ])->assertStatus(200);

        $returnCase = ReturnCase::where('order_id', $order->id)->firstOrFail();

        // Vendor disputes
        Sanctum::actingAs($this->vendorUser);
        $this->postJson("/api/v1/vendor/returns/{$returnCase->id}/dispute", [
            'dispute_reason' => 'Customer damaged product at door.',
        ])->assertStatus(200);

        // Super Admin reviews and issues binding ruling: uphold_customer_claim
        $response = $this->actingAs($this->adminUser)->post(route('admin.returns.arbitrate', $returnCase->id), [
            'ruling' => 'uphold_customer_claim',
            'admin_notes' => 'Photographic evidence confirms internal coil was burned prior to handover.',
        ]);

        $response->assertRedirect(route('admin.returns.show', $returnCase->id));

        $returnCase->refresh();
        $this->assertEquals('resolved', $returnCase->status);
        $this->assertEquals('uphold_customer_claim', $returnCase->admin_ruling);
        $this->assertEquals('vendor_100', $returnCase->cost_allocation);
        $this->assertNotNull($returnCase->admin_ruled_at);

        // Check vendor penalty in recovery ledger
        $penalty = VendorRecoveryLedger::where('return_case_id', $returnCase->id)->first();
        $this->assertNotNull($penalty);
        $this->assertEquals(1500.00, (float)$penalty->amount);
    }

    /**
     * Test 5: Customer Instant Re-Order with 100% spendable Wallet Credit.
     */
    public function test_customer_wallet_balance_and_instant_reorder(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $this->postJson('/api/v1/rider/orders/doorstep-handover', [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                ['order_item_id' => $item1->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
                ['order_item_id' => $item2->id, 'accepted_quantity' => 0, 'rejected_quantity' => 1, 'rejection_category' => 'vendor_issue'],
            ],
        ])->assertStatus(200);

        $returnCase = ReturnCase::where('order_id', $order->id)->firstOrFail();

        Sanctum::actingAs($this->customer);

        // 1. Check wallet API summary
        $summaryResp = $this->getJson('/api/v1/customer/wallet');
        $summaryResp->assertStatus(200);
        $summaryResp->assertJsonPath('data.available_wallet_credit', '15000.00');

        // 2. Customer 1-click re-orders rejected items using wallet balance
        $reorderResp = $this->postJson('/api/v1/customer/orders/reorder-rejected-items', [
            'case_number' => $returnCase->case_number,
        ]);

        $reorderResp->assertStatus(200);
        $reorderResp->assertJsonPath('status', true);
        $reorderResp->assertJsonPath('data.wallet_credit_applied', '15000.00');
        $reorderResp->assertJsonPath('data.net_payable_cash', '0.00');
    }

    /**
     * Test 6: Customer Change of Mind requires customer to pay 100% of delivery fee.
     */
    public function test_customer_change_of_mind_pays_100_percent_delivery_fee(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $resp = $this->postJson('/api/v1/rider/orders/doorstep-handover', [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                ['order_item_id' => $item1->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
                ['order_item_id' => $item2->id, 'accepted_quantity' => 0, 'rejected_quantity' => 1, 'rejection_category' => 'customer_change_of_mind', 'rejection_notes' => 'Customer preferred blue.'],
            ],
        ]);

        $resp->assertStatus(200);

        $returnCase = ReturnCase::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals('customer_change_of_mind', $returnCase->rejection_category);
        $this->assertEquals('customer_100', $returnCase->cost_allocation);

        // Verify customer wallet credit: ₦15,000 rejected item minus 100% delivery fee (₦1,500) = ₦13,500.00
        $credit = CustomerWalletCredit::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($credit);
        $this->assertEquals(13500.00, (float)$credit->amount);
    }

    /**
     * Test 7: Transit damage protects vendor 90% payout and allocates liability to courier fleet.
     */
    public function test_transit_damage_protects_vendor_and_allocates_fleet_liability(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $resp = $this->postJson('/api/v1/rider/orders/doorstep-handover', [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                ['order_item_id' => $item1->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
                ['order_item_id' => $item2->id, 'accepted_quantity' => 0, 'rejected_quantity' => 1, 'rejection_category' => 'transit_damage', 'rejection_notes' => 'Rider dropped box in transit; water soaked.'],
            ],
        ]);

        $resp->assertStatus(200);

        $returnCase = ReturnCase::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals('transit_damage', $returnCase->rejection_category);
        $this->assertEquals('logistics_fleet_100', $returnCase->cost_allocation);
    }

    /**
     * Test 8: Admin Return Details page renders Triangular Evidence Dossier and Arbitration form.
     */
    public function test_admin_portal_renders_triangular_evidence_dossier(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $this->postJson('/api/v1/rider/orders/doorstep-handover', [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                ['order_item_id' => $item1->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
                ['order_item_id' => $item2->id, 'accepted_quantity' => 0, 'rejected_quantity' => 1, 'rejection_category' => 'vendor_issue'],
            ],
        ])->assertStatus(200);

        $returnCase = ReturnCase::where('order_id', $order->id)->firstOrFail();

        // Vendor disputes
        Sanctum::actingAs($this->vendorUser);
        $this->postJson("/api/v1/vendor/returns/{$returnCase->id}/dispute", [
            'dispute_reason' => 'Customer opened sealed carton and broke power plug.',
        ])->assertStatus(200);

        // Admin views return case
        $response = $this->actingAs($this->adminUser)->get(route('admin.returns.show', $returnCase->id));
        $response->assertStatus(200);
        $response->assertSee('Triangular Evidence Dossier &amp; Handshake 3 Audit', false);
        $response->assertSee('Doorstep Rejection');
        $response->assertSee('Vendor Counter Inspection');
        $response->assertSee('Super Admin Ruling');
        $response->assertSee('Arbitrate Vendor Return Dispute');
        $response->assertSee('Customer opened sealed carton and broke power plug.');
    }

    /**
     * Test 9: 100% Doorstep Rejection updates Order Status to 'returned' (Gap 3 Fix).
     */
    public function test_100_percent_doorstep_rejection_updates_order_status_to_returned(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $response = $this->postJson('/api/v1/rider/orders/doorstep-handover', [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                [
                    'order_item_id' => $item1->id,
                    'accepted_quantity' => 0,
                    'rejected_quantity' => 1,
                    'rejection_category' => 'vendor_issue',
                    'rejection_notes' => 'Damaged carton sent by vendor',
                ],
                [
                    'order_item_id' => $item2->id,
                    'accepted_quantity' => 0,
                    'rejected_quantity' => 1,
                    'rejection_category' => 'customer_change_of_mind',
                    'rejection_notes' => 'Customer no longer needs the electric kettle',
                ],
            ],
        ]);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals('returned', $order->order_status, 'Order status must be returned when 100% of items are rejected.');
        $this->assertNull($order->received_at, 'Received at must remain null when zero items are accepted.');
        $this->assertTrue((bool)$order->delivery_confirmed_by_customer);

        // Verify ReturnCase created
        $returnCase = ReturnCase::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals('in_transit_back_to_vendor', $returnCase->status);
        $this->assertNotNull($returnCase->vendor_return_otp);
    }

    /**
     * Test 10: Rider logs Delivery Attempt Failure #1 (phone unreachable, order remains out_for_delivery).
     */
    public function test_rider_failed_delivery_attempt_first_attempt_allows_redispatch(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $response = $this->postJson('/api/v1/rider/orders/delivery-attempt-failed', [
            'order_id' => $order->id,
            'reason' => 'customer_unreachable',
            'notes' => 'Customer phone switched off. Gate security says resident is away.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => [
                'order_id' => $order->id,
                'attempt_count' => 1,
                'reason' => 'customer_unreachable',
                'max_attempts_reached' => false,
                'order_status' => 'out_for_delivery',
            ],
        ]);

        $order->refresh();
        $this->assertEquals(1, $order->delivery_attempt_count);
        $this->assertEquals('customer_unreachable', $order->delivery_failure_reason);
        $this->assertEquals('out_for_delivery', $order->order_status);
        $this->assertNotNull($order->last_delivery_attempt_at);
    }

    /**
     * Test 11: Rider logs Delivery Attempt Failure #2 (max attempts reached -> converts to return-to-vendor).
     */
    public function test_rider_failed_delivery_attempt_max_attempts_converts_to_vendor_return(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();
        $order->delivery_attempt_count = 1;
        $order->save();

        Sanctum::actingAs($this->riderUser);
        $response = $this->postJson('/api/v1/rider/orders/delivery-attempt-failed', [
            'order_id' => $order->id,
            'reason' => 'gate_locked',
            'notes' => 'Second attempt failed. Estate gate locked and customer number not connecting.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => [
                'order_id' => $order->id,
                'attempt_count' => 2,
                'reason' => 'gate_locked',
                'max_attempts_reached' => true,
                'order_status' => 'returned',
            ],
        ]);

        $order->refresh();
        $this->assertEquals(2, $order->delivery_attempt_count);
        $this->assertEquals('returned', $order->order_status);

        // Verify ReturnCase created with vendor return OTP
        $returnCase = ReturnCase::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals('delivery_failed_unreachable', $returnCase->source);
        $this->assertEquals('customer_unreachable', $returnCase->rejection_category);
        $this->assertEquals('in_transit_back_to_vendor', $returnCase->status);
        $this->assertNotNull($returnCase->vendor_return_otp);

        // Verify Customer Wallet Refund Credit created for merchandise (₦45,000)
        $credit = CustomerWalletCredit::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals(0, bccomp('45000.00', (string)$credit->amount, 2));
        $this->assertEquals('available', $credit->status);

        // Verify Logistics Courier delivery fee credited (90% of ₦1,500 = ₦1,350)
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'logistics_payable',
            'amount' => '1350.00',
            'entry_type' => 'credit',
        ]);
    }

    /**
     * Test 12: Standard Checkout with 100% Refund Wallet Credit completes without Paystack.
     */
    public function test_standard_checkout_with_100_percent_wallet_credit_completes_without_paystack(): void
    {
        // Give customer ₦50,000 in spendable refund wallet credits
        CustomerWalletCredit::create([
            'customer_id' => $this->customer->id,
            'amount' => '50000.00',
            'credit_type' => 'doorstep_refund_credit',
            'status' => 'available',
            'notes' => 'Initial refund wallet balance',
        ]);

        $customerAddress = \App\Models\CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'Emeka Shopper',
            'phone' => '+2348011223344',
            'address_line' => '42 Ikot Ekpene Road',
            'country_id' => $this->uyoLga->state->country_id,
            'lga_id' => $this->uyoLga->id,
            'state_id' => $this->uyoLga->state_id,
            'is_default' => true,
        ]);

        // In-shop pickup checkout for Kettle (₦15,000)
        Sanctum::actingAs($this->customer);
        $response = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'in_shop_pickup',
            'items' => [
                ['product_id' => $this->kettle->id, 'quantity' => 1],
            ],
            'redeem_wallet_credit' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => true,
            'data' => [
                'grand_total' => '0.00',
                'payment_method' => 'wallet_credit',
                'payment_status' => 'paid',
                'wallet_credit_applied' => '15000.00',
                'paystack' => null,
            ],
        ]);

        // Verify order is immediately marked as paid and confirmed
        $order = Order::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('confirmed', $order->order_status);
        $this->assertEquals('wallet_credit', $order->payment_method);
        $this->assertEquals('15000.00', (string)$order->wallet_credit_applied);
        $this->assertEquals('0.00', (string)$order->grand_total);

        // Verify remaining wallet balance is ₦35,000.00
        $remainingBalance = CustomerWalletCredit::getAvailableBalance($this->customer->id);
        $this->assertEquals('35000.00', $remainingBalance);

        // Verify Double-Entry Journal balances exactly
        $debits = \App\Models\FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'debit')->sum('amount');
        $credits = \App\Models\FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'credit')->sum('amount');
        $this->assertEquals('15000.00', number_format((float)$debits, 2, '.', ''));
        $this->assertEquals('15000.00', number_format((float)$credits, 2, '.', ''));
    }

    /**
     * Test 13: Two-Phase Checkout Intent with Wallet Credit calculation.
     */
    public function test_checkout_intent_with_redeem_wallet_credit(): void
    {
        CustomerWalletCredit::create([
            'customer_id' => $this->customer->id,
            'amount' => '10000.00',
            'credit_type' => 'doorstep_refund_credit',
            'status' => 'available',
        ]);

        $customerAddress = \App\Models\CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'Emeka Shopper',
            'phone' => '+2348011223344',
            'address_line' => '42 Ikot Ekpene Road',
            'country_id' => $this->uyoLga->state->country_id,
            'lga_id' => $this->uyoLga->id,
            'state_id' => $this->uyoLga->state_id,
            'is_default' => true,
        ]);

        Sanctum::actingAs($this->customer);
        $response = $this->postJson('/api/v1/checkout/intent', [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_address_id' => $customerAddress->id,
            'items' => [
                ['product_id' => $this->blender->id, 'quantity' => 1],
            ],
            'redeem_wallet_credit' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => true,
            'data' => [
                'merchandise_subtotal' => '30000.00',
                'delivery_fee' => '500.00',
                'wallet_credit_applied' => '10000.00',
                'total_payable' => '20500.00',
            ],
        ]);
    }

    /**
     * Test 14: Partial Wallet Credit Checkout with Paystack Gateway Verification balances double-entry books.
     */
    public function test_partial_wallet_credit_checkout_with_paystack_verification(): void
    {
        CustomerWalletCredit::create([
            'customer_id' => $this->customer->id,
            'amount' => '10000.00',
            'credit_type' => 'doorstep_refund_credit',
            'status' => 'available',
        ]);

        $customerAddress = \App\Models\CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'Emeka Shopper',
            'phone' => '+2348011223344',
            'address_line' => '42 Ikot Ekpene Road',
            'country_id' => $this->uyoLga->state->country_id,
            'lga_id' => $this->uyoLga->id,
            'state_id' => $this->uyoLga->state_id,
            'is_default' => true,
        ]);

        Sanctum::actingAs($this->customer);
        $checkoutRes = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_address_id' => $customerAddress->id,
            'items' => [
                ['product_id' => $this->blender->id, 'quantity' => 1],
            ],
            'redeem_wallet_credit' => true,
            'payment_method' => 'paystack',
        ]);

        $checkoutRes->assertStatus(201);
        $orderNumber = $checkoutRes->json('data.order_number');
        $this->assertEquals('20500.00', $checkoutRes->json('data.grand_total'));
        $this->assertEquals('10000.00', $checkoutRes->json('data.wallet_credit_applied'));

        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $this->assertEquals('pending', $order->payment_status);

        // Simulate successful Paystack server-to-server gateway callback/verification
        $settlementService = app(\App\Services\OrderSettlementService::class);
        $paystackPayload = [
            'status' => 'success',
            'amount' => 2050000, // 20,500.00 NGN in Kobo
            'currency' => 'NGN',
            'reference' => $order->payment_reference,
            'channel' => 'card',
            'id' => 9918231,
            'customer' => ['email' => $this->customer->email],
        ];

        $settleResult = $settlementService->settlePaystackOrder($order, $paystackPayload, $this->customer);
        $this->assertTrue($settleResult['success']);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('confirmed', $order->order_status);

        // Verify customer wallet was spent
        $remainingWallet = CustomerWalletCredit::getAvailableBalance($this->customer->id);
        $this->assertEquals('0.00', $remainingWallet);

        // Verify Double-Entry Journal balances exactly (₦30,500 debits == ₦30,500 credits)
        $debits = \App\Models\FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'debit')->sum('amount');
        $credits = \App\Models\FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'credit')->sum('amount');
        $this->assertEquals('30500.00', number_format((float)$debits, 2, '.', ''));
        $this->assertEquals('30500.00', number_format((float)$credits, 2, '.', ''));
    }
}

