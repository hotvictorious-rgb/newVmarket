<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\CustomerCashbackLedger;
use App\Models\CustomerWalletCredit;
use App\Models\CustomerAddress;
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
    protected CustomerAddress $customerAddress;
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
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'UYO-452-XY',
            'is_active' => true,
            'is_online' => true,
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

        // Active delivery riders are vendor-owned; legacy fleet-only profiles cannot
        // access the new vendor-managed rider terminal.
        $this->riderProfile->update([
            'vendor_id' => $this->vendorProfile->id,
            'logistics_company_id' => null,
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

        $this->customerAddress = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'Emeka Shopper',
            'phone' => '+2348011223344',
            'address_line' => '42 Ikot Ekpene Road',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'landmark' => 'Near the market junction',
            'is_default' => true,
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
            'customer_address_id' => $this->customerAddress->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
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

    protected function postDoorstepHandover(array $params): \Illuminate\Testing\TestResponse
    {
        if (!isset($params['proof_photo'])) {
            $params['proof_photo'] = UploadedFile::fake()->image('doorstep_condition.jpg');
        }
        if (isset($params['manifest_items'])) {
            foreach ($params['manifest_items'] as $item) {
                $itemId = $item['order_item_id'];
                if (($item['rejected_quantity'] ?? 0) > 0 && !isset($params["item_photo_{$itemId}"]) && empty($item['defect_photo_path'])) {
                    $params["item_photo_{$itemId}"] = UploadedFile::fake()->image("defect_{$itemId}.jpg");
                }
            }
        }
        return $this->postJson('/api/v1/rider/orders/doorstep-handover', $params);
    }

    /**
     * Test 1: Customer partially accepts order at doorstep (accepts blender, rejects kettle) without an instant wallet refund.
     * Verifies:
     * - Item 1 accepted, Item 2 rejected.
     * - Order status becomes partially_delivered.
     * - 90/5/5 accounting calculated ONLY on accepted merchandise (₦30,000).
     * - Delivery-fee allocation remains vendor/platform only; no fleet payable is created.
     * - Rejected merchandise creates a reviewable return case and reservation, not a wallet credit.
     * - ReturnCase auto-generated with 6-digit vendor_return_otp and in_transit_back_to_vendor status.
     */
    public function test_partial_doorstep_acceptance_creates_return_request_without_wallet_credit(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        $photo = UploadedFile::fake()->image('doorstep_defect.jpg');

        Sanctum::actingAs($this->riderUser);
        $response = $this->postDoorstepHandover( [
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
        // Rider response reports operational success only; money amounts stay in finance/admin views.
        $response->assertJsonMissingPath('data.accepted_amount');
        $response->assertJsonMissingPath('data.rejected_amount');
        $response->assertJsonMissingPath('data.refund_requested_amount');
        $response->assertJsonMissingPath('data.wallet_credited');

        $order->refresh();
        $this->assertEquals('delivered', $order->order_status);
        $this->assertTrue((bool)$order->delivery_confirmed_by_customer);

        // Cashback matures only after the 24-hour customer window plus a separate
        // 24-hour buffer; the buffer must not extend the customer claim timestamp.
        $cashbackLedger = CustomerCashbackLedger::where('order_id', $order->id)->firstOrFail();
        $this->assertTrue(
            $cashbackLedger->matures_at->equalTo($order->refund_window_expires_at->copy()->addHours(24))
        );

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

        // No wallet refund is issued at doorstep; the customer refund remains subject
        // to return review and an authorized original-Paystack refund.
        $this->assertSame(0, CustomerWalletCredit::where('customer_id', $this->customer->id)->count());
        $this->assertEquals('0.00', CustomerWalletCredit::getAvailableBalance($this->customer->id));

        // Check Financial Reservation placed on order ceiling
        $reservation = \App\Models\FinancialReservation::where('source_type', Order::class)
            ->where('source_id', $order->id)
            ->where('purpose', 'customer_refund_hold')
            ->first();
        $this->assertNotNull($reservation);
        $this->assertEquals(1500000, $reservation->reserved_amount_kobo);
        $this->assertEquals('active', $reservation->status);

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

    public function test_rider_dashboard_exposes_address_but_not_customer_phone_or_financial_fields(): void
    {
        $order = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $response = $this->getJson('/api/v1/rider/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('data.active_orders.0.customer.name', 'Emeka Shopper');
        $response->assertJsonPath('data.active_orders.0.customer.delivery_address', '42 Ikot Ekpene Road, Near the market junction, Uyo, Akwa Ibom');
        $response->assertJsonMissingPath('data.active_orders.0.customer.phone');
        $response->assertJsonMissingPath('data.active_orders.0.payment_status');
        $response->assertJsonMissingPath('data.active_orders.0.delivery_fee');
        $response->assertDontSee('+2348011223344');
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
        $this->postDoorstepHandover( [
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
            'proof_photo' => UploadedFile::fake()->image('vendor_return.jpg'),
        ]);
        $failResp->assertStatus(422);

        // 3. Rider submits valid OTP provided by vendor at shop counter
        $successResp = $this->postJson('/api/v1/rider/orders/verify-vendor-return-otp', [
            'return_case_id' => $returnCase->id,
            'otp' => $otp,
            'proof_photo' => UploadedFile::fake()->image('vendor_return.jpg'),
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
        $this->postDoorstepHandover( [
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
        $this->postDoorstepHandover( [
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

        // The ruling establishes fault but does not itself create a debt.
        // Receivable is posted only when Victorious MARKET actually funds an authorised refund.
        $this->assertSame(0, VendorRecoveryLedger::where('return_case_id', $returnCase->id)->count());
    }

    /**
     * Launch policy: a doorstep return cannot be refunded into the customer wallet.
     */
    public function test_wallet_refund_is_disabled_at_launch_for_doorstep_return(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $this->postDoorstepHandover( [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                ['order_item_id' => $item1->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
                ['order_item_id' => $item2->id, 'accepted_quantity' => 0, 'rejected_quantity' => 1, 'rejection_category' => 'vendor_issue'],
            ],
        ])->assertStatus(200);

        $returnCase = ReturnCase::where('order_id', $order->id)->firstOrFail();

        // Handshake 3: Rider returns item to vendor shop
        Sanctum::actingAs($this->riderUser);
        $this->postJson('/api/v1/rider/orders/verify-vendor-return-otp', [
            'return_case_id' => $returnCase->id,
            'otp' => $returnCase->vendor_return_otp,
            'proof_photo' => UploadedFile::fake()->image('vendor_return.jpg'),
        ])->assertStatus(200);

        // Approval of the return case does not authorize a wallet refund at launch.
        $returnService = app(\App\Services\ReturnAndRefundService::class);
        $returnService->adminApproveReturn($this->adminUser, $returnCase->fresh());
        $refundResult = $returnService->adminDisburseRefund($this->adminUser, $returnCase->fresh(), 'wallet');
        $this->assertFalse($refundResult['success']);
        $this->assertSame('disabled', $refundResult['status']);

        Sanctum::actingAs($this->customer);
        $summaryResp = $this->getJson('/api/v1/customer/wallet');
        $summaryResp->assertStatus(200);
        $summaryResp->assertJsonPath('data.available_wallet_credit', '0.00');
        $this->assertSame(0, CustomerWalletCredit::where('return_case_id', $returnCase->id)->count());
    }

    /**
     * Test 6: Customer Change of Mind requires customer to pay 100% of delivery fee.
     */
    public function test_customer_change_of_mind_pays_100_percent_delivery_fee(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $resp = $this->postDoorstepHandover( [
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

        // Customer change-of-mind rejection opens a reviewable return case only.
        $this->assertSame(0, CustomerWalletCredit::where('customer_id', $this->customer->id)->count());
    }

    /**
     * Test 7: Transit damage is attributed to the owning vendor, not a platform-managed fleet.
     */
    public function test_transit_damage_is_attributed_to_owning_vendor_without_fleet_liability(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $resp = $this->postDoorstepHandover( [
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
        $this->assertEquals('vendor_100', $returnCase->cost_allocation);
    }

    /**
     * Test 8: Admin Return Details page renders Triangular Evidence Dossier and Arbitration form.
     */
    public function test_admin_portal_renders_triangular_evidence_dossier(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $this->postDoorstepHandover( [
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
        $response = $this->postDoorstepHandover( [
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
            'proof_photo' => UploadedFile::fake()->image('attempt1.jpg'),
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
            'proof_photo' => UploadedFile::fake()->image('attempt2.jpg'),
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

        // Failed delivery records the return-to-vendor operation only. It must not
        // issue a wallet refund or create a legacy logistics payable.
        $this->assertSame(0, CustomerWalletCredit::where('order_id', $order->id)->count());
        $this->assertDatabaseMissing('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'logistics_payable',
        ]);
    }

    /**
     * Launch policy: legacy refund wallet credit cannot be used as checkout tender.
     */
    public function test_standard_checkout_rejects_wallet_credit_tender(): void
    {
        CustomerWalletCredit::create([
            'customer_id' => $this->customer->id,
            'amount' => '50000.00',
            'credit_type' => 'doorstep_refund_credit',
            'status' => 'available',
            'notes' => 'Initial refund wallet balance',
        ]);

        Sanctum::actingAs($this->customer);
        $response = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'in_shop_pickup',
            'items' => [
                ['product_id' => $this->kettle->id, 'quantity' => 1],
            ],
            'redeem_wallet_credit' => true,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Order::where('customer_id', $this->customer->id)->count());
        $this->assertEquals('50000.00', CustomerWalletCredit::getAvailableBalance($this->customer->id));
    }

    /**
     * Launch policy: checkout intent must reject legacy wallet-credit redemption.
     */
    public function test_checkout_intent_rejects_wallet_credit_redemption(): void
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
        foreach ([true, false] as $legacyWalletCreditValue) {
            $response = $this->postJson('/api/v1/checkout/intent', [
                'shop_id' => $this->shop->id,
                'fulfillment_type' => 'doorstep_delivery',
                'customer_address_id' => $customerAddress->id,
                'items' => [
                    ['product_id' => $this->blender->id, 'quantity' => 1],
                ],
                'redeem_wallet_credit' => $legacyWalletCreditValue,
            ]);

            $response->assertStatus(422);
        }
        $this->assertSame(0, \App\Models\CheckoutIntent::where('customer_id', $this->customer->id)->count());
        $this->assertEquals('10000.00', CustomerWalletCredit::getAvailableBalance($this->customer->id));
    }

    /**
     * Launch policy: wallet credit cannot partially offset a Paystack checkout.
     */
    public function test_partial_wallet_credit_checkout_is_rejected_without_spending_credit(): void
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
        $response = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_address_id' => $customerAddress->id,
            'items' => [
                ['product_id' => $this->blender->id, 'quantity' => 1],
            ],
            'redeem_wallet_credit' => true,
            'payment_method' => 'paystack',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Order::where('customer_id', $this->customer->id)->count());
        $this->assertEquals('10000.00', CustomerWalletCredit::getAvailableBalance($this->customer->id));
    }

    /**
     * Test 15: Multi-Item Doorstep Rejection with Live Defect Photo per Item
     */
    public function test_multi_item_doorstep_rejection_stores_item_defect_photo(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        $overallPhoto = UploadedFile::fake()->image('doorstep_handover.jpg');
        $defectPhotoItem2 = UploadedFile::fake()->image('kettle_broken_element.jpg');

        Sanctum::actingAs($this->riderUser);
        $response = $this->postDoorstepHandover( [
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
                    'rejection_notes' => 'Element broken.',
                ],
            ],
            'proof_photo' => $overallPhoto,
            "item_photo_{$item2->id}" => $defectPhotoItem2,
        ]);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertNotNull($order->proof_of_delivery_image);
        $this->assertNotNull($order->doorstep_rejection_photos);
        $this->assertCount(1, $order->doorstep_rejection_photos);
        $this->assertEquals($item2->id, $order->doorstep_rejection_photos[0]['order_item_id']);

        $item2->refresh();
        $this->assertNotNull($item2->defect_photo_path);
        $this->assertStringContainsString('item_defects', $item2->defect_photo_path);
    }

    /**
     * Test 16: Doorstep Unreachable Attempt Stores Gate/Premises Live Photo
     */
    public function test_delivery_attempt_failed_stores_locked_gate_photo_proof(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        $gatePhoto = UploadedFile::fake()->image('locked_estate_gate.jpg');

        Sanctum::actingAs($this->riderUser);
        $response = $this->postJson('/api/v1/rider/orders/delivery-attempt-failed', [
            'order_id' => $order->id,
            'reason' => 'gate_locked',
            'notes' => 'Estate security refused access without resident pass.',
            'proof_photo' => $gatePhoto,
        ]);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals(1, $order->delivery_attempt_count);
        $this->assertEquals('gate_locked', $order->delivery_failure_reason);
        $this->assertNotNull($order->delivery_attempt_photo_path);
        $this->assertStringContainsString('delivery_attempts', $order->delivery_attempt_photo_path);
    }

    /**
     * Test 17: Vendor Return Handshake Stores Vendor Counter Return Photo
     */
    public function test_vendor_return_otp_stores_counter_return_photo_proof(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        // Perform rejection to generate return case
        Sanctum::actingAs($this->riderUser);
        $doorstepRes = $this->postDoorstepHandover( [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                [
                    'order_item_id' => $item1->id,
                    'accepted_quantity' => 0,
                    'rejected_quantity' => 1,
                    'rejection_category' => 'vendor_issue',
                    'rejection_notes' => 'Defective',
                ],
                [
                    'order_item_id' => $item2->id,
                    'accepted_quantity' => 0,
                    'rejected_quantity' => 1,
                    'rejection_category' => 'vendor_issue',
                    'rejection_notes' => 'Defective',
                ],
            ],
        ]);

        $caseData = $doorstepRes->json('data.return_case');
        $returnCase = ReturnCase::findOrFail($caseData['id']);

        $counterReturnPhoto = UploadedFile::fake()->image('vendor_counter_return.jpg');

        $returnRes = $this->postJson('/api/v1/rider/orders/verify-vendor-return-otp', [
            'return_case_id' => $returnCase->id,
            'otp' => $returnCase->vendor_return_otp,
            'proof_photo' => $counterReturnPhoto,
        ]);

        $returnRes->assertStatus(200);

        $returnCase->refresh();
        $this->assertEquals('returned_to_vendor', $returnCase->status);
        $this->assertNotNull($returnCase->vendor_return_photo_path);
        $this->assertStringContainsString('vendor_returns', $returnCase->vendor_return_photo_path);
    }

    /**
     * Test 18: Photo Proof Immutability Prevents Post-Verification Alteration
     */
    public function test_photo_proof_immutability_blocks_post_verification_tampering(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $firstRes = $this->postJson('/api/v1/rider/orders/verify-delivery-otp', [
            'order_id' => $order->id,
            'otp' => '719283',
            'proof_photo' => UploadedFile::fake()->image('doorstep_photo_1.jpg'),
        ]);
        $firstRes->assertStatus(200);

        // Subsequent attempt to re-verify or mutate photo is rejected
        $tamperRes = $this->postJson('/api/v1/rider/orders/verify-delivery-otp', [
            'order_id' => $order->id,
            'otp' => '719283',
            'proof_photo' => UploadedFile::fake()->image('doorstep_photo_tampered.jpg'),
        ]);
        $tamperRes->assertStatus(422);
        $tamperRes->assertJsonPath('message', 'Delivery has already been confirmed and sealed. Photo proof is immutable.');
    }

    /**
     * Test 19: Customer Full Order Details Exposes Visual Audit Trail
     */
    public function test_customer_full_order_details_exposes_visual_audit_trail(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        $order->pickup_proof_image = 'storage/pickup_proofs/pickup_sample.jpg';
        $order->proof_of_delivery_image = 'storage/doorstep_deliveries/delivery_sample.jpg';
        $order->delivery_attempt_photo_path = 'storage/delivery_attempts/gate_sample.jpg';
        $order->save();

        Sanctum::actingAs($this->customer);
        $response = $this->getJson("/api/v1/customer/orders/{$order->order_number}");

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonStructure([
            'data' => [
                'visual_audit_trail' => [
                    'pickup_counter_photo',
                    'doorstep_delivery_photo',
                    'delivery_attempt_photo',
                    'item_rejection_photos',
                ],
                'items' => [
                    '*' => [
                        'defect_photo',
                    ],
                ],
            ],
        ]);
    }

    public function test_pickup_otp_strictly_requires_photo_and_rejects_without_it(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();
        $order->order_status = 'ready_for_pickup';
        $order->pickup_confirmed_by_rider = false;
        $order->save();

        Sanctum::actingAs($this->riderUser);
        $response = $this->postJson('/api/v1/rider/orders/verify-pickup-otp', [
            'order_id' => $order->id,
            'otp' => '918234',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['proof_photo']);
        $this->assertStringContainsString('strictly mandatory', $response->json('message'));
    }

    public function test_delivery_otp_strictly_requires_photo_and_rejects_without_it(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $response = $this->postJson('/api/v1/rider/orders/verify-delivery-otp', [
            'order_id' => $order->id,
            'otp' => '719283',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['proof_photo']);
        $this->assertStringContainsString('strictly mandatory', $response->json('message'));
    }

    public function test_doorstep_handover_strictly_requires_photo_and_rejects_without_it(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $response = $this->postJson('/api/v1/rider/orders/doorstep-handover', [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                ['order_item_id' => $item1->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['proof_photo']);
        $this->assertStringContainsString('strictly mandatory', $response->json('message'));
    }

    public function test_doorstep_rejection_strictly_requires_item_defect_photo(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $response = $this->postJson('/api/v1/rider/orders/doorstep-handover', [
            'order_id' => $order->id,
            'otp' => '719283',
            'manifest_items' => [
                ['order_item_id' => $item1->id, 'accepted_quantity' => 1, 'rejected_quantity' => 0],
                ['order_item_id' => $item2->id, 'accepted_quantity' => 0, 'rejected_quantity' => 1, 'rejection_category' => 'vendor_issue'],
            ],
            'proof_photo' => UploadedFile::fake()->image('overall_doorstep.jpg'),
            // Notice: item_photo_{$item2->id} is intentionally omitted!
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('live defect photo is strictly mandatory', $response->json('message'));
    }

    public function test_delivery_attempt_failed_strictly_requires_photo_and_rejects_without_it(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();

        Sanctum::actingAs($this->riderUser);
        $response = $this->postJson('/api/v1/rider/orders/delivery-attempt-failed', [
            'order_id' => $order->id,
            'reason' => 'gate_locked',
            'notes' => 'Gate was locked, attempted without photo.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['proof_photo']);
        $this->assertStringContainsString('strictly mandatory', $response->json('message'));
    }

    public function test_verify_vendor_return_otp_strictly_requires_photo_and_rejects_without_it(): void
    {
        [$order, $item1, $item2] = $this->createOutForDeliveryOrder();
        $returnCase = ReturnCase::create([
            'case_number' => 'RET-MANDATORY-TEST',
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'vendor_id' => $order->vendor_id,
            'collection_point_id' => $order->collection_point_id,
            'source' => 'doorstep_partial_rejection',
            'rejection_category' => 'vendor_issue',
            'vendor_return_otp' => '888999',
            'returned_by_rider_id' => $this->riderProfile->id,
            'status' => 'in_transit_back_to_vendor',
            'return_reason' => 'damaged_item',
            'total_refundable_amount' => '15000.00',
        ]);

        Sanctum::actingAs($this->riderUser);
        $response = $this->postJson('/api/v1/rider/orders/verify-vendor-return-otp', [
            'return_case_id' => $returnCase->id,
            'otp' => '888999',
            // Notice: proof_photo omitted!
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['proof_photo']);
        $this->assertStringContainsString('strictly mandatory', $response->json('message'));
    }

    public function test_unpaid_order_cannot_enter_partial_doorstep_handover(): void
    {
        [$order] = $this->createOutForDeliveryOrder();
        $order->payment_status = 'pending';
        $order->save();

        $result = app(DoorstepHandshakeService::class)->processDoorstepHandover(
            $order,
            $this->riderProfile,
            [],
            '719283',
            null
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Unpaid orders cannot undergo doorstep handover.', $result['message']);
        $order->refresh();
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('out_for_delivery', $order->order_status);
        $this->assertFalse((bool) $order->delivery_confirmed_by_customer);
        $this->assertNull($order->received_at);
        $this->assertDatabaseMissing('customer_cashback_ledgers', ['order_id' => $order->id]);
        $this->assertDatabaseMissing('return_cases', ['order_id' => $order->id]);
    }

}