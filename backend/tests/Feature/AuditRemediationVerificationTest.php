<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\CustomerCashbackLedger;
use App\Models\CustomerWalletCredit;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\LogisticsCollectionPoint;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupReservation;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\ReturnEvidence;
use App\Models\ReturnItem;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\DoorstepHandshakeService;
use App\Services\PaystackService;
use App\Services\ReturnAndRefundService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class AuditRemediationVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $customer;
    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected Shop $shop;
    protected Product $product;
    protected User $fleetUser;
    protected LogisticsCompanyProfile $company;
    protected RiderProfile $rider;
    protected LogisticsCollectionPoint $collectionPoint;
    protected State $state;
    protected Lga $lga;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NGA',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'currency_symbol' => '₦',
            'is_active' => true,
        ]);

        $this->state = State::create([
            'country_id' => $country->id,
            'name' => 'Akwa Ibom',
            'state_code' => 'AK',
            'is_active' => true,
        ]);

        $this->lga = Lga::create(['state_id' => $this->state->id, 'name' => 'Uyo', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Super Finance Admin',
            'email' => 'admin@vmarket.ng',
            'password' => bcrypt('AdminPass123!'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Shopper Grace',
            'email' => 'grace@vmarket.ng',
            'password' => bcrypt('CustomerPass123!'),
            'user_type' => 'customer',
            'phone' => '08011112222',
            'status' => 'active',
        ]);

        $this->fleetUser = User::create([
            'name' => 'Logistics Owner',
            'email' => 'logistics@speedy.ng',
            'password' => bcrypt('LogisticsPass123!'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $this->company = LogisticsCompanyProfile::create([
            'user_id' => $this->fleetUser->id,
            'company_name' => 'Speedy Logistics',
            'contact_phone' => '08055556666',
            'business_registration_number' => 'RC-SPEEDY-001',
            'operating_state_id' => $this->state->id,
            'is_verified' => true,
            'status' => 'active',
            'settlement_bank_name' => 'Access Bank',
            'settlement_account_number' => '0123456789',
            'settlement_account_name' => 'Speedy Logistics',
        ]);

        $riderUser = User::create([
            'name' => 'Rider Edet',
            'email' => 'edet@speedy.ng',
            'password' => bcrypt('RiderPass123!'),
            'user_type' => 'rider',
            'phone' => '08022223333',
            'status' => 'active',
        ]);

        $this->rider = RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => $this->company->id,
            'assigned_state_id' => $this->state->id,
            'assigned_lga_id' => $this->lga->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->collectionPoint = LogisticsCollectionPoint::create([
            'logistics_company_id' => $this->company->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'name' => 'Uyo Hub',
            'code' => 'CP-UYO-001',
            'address' => '10 Abak Road, Uyo',
            'landmark' => 'Near Plaza',
            'contact_phone' => '08055556666',
            'contact_person' => 'Hub Manager',
            'operating_hours' => '08:00 AM - 06:00 PM',
            'is_active' => true,
        ]);

        $this->vendorUser = User::create([
            'name' => 'Vendor Alpha',
            'email' => 'alpha@merchant.ng',
            'password' => bcrypt('VendorPass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Alpha Gadgets',
            'business_phone' => '08077778888',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1000000001',
            'account_name' => 'Alpha Gadgets',
            'marketplace_status' => 'active',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'country_id' => $country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'name' => 'Alpha Shop',
            'slug' => 'alpha-shop',
            'address' => '10 Abak Road',
            'phone' => '08077778888',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $category = Category::create([
            'name' => 'Tech',
            'slug' => 'tech',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'name' => 'Smart Speaker',
            'slug' => 'smart-speaker',
            'sku' => 'SPK-001',
            'unit_price' => '10000.00',
            'inventory_quantity' => 20,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'package_tier' => 'bike',
        ]);

        SystemSetting::set('refund_window_hours', 24);
    }

    private function createOrder(string $status = 'delivered', int $qty = 2, string $cashbackRedeemed = '0.00'): array
    {
        $unitPrice = 10000.00;
        $productTotal = $unitPrice * $qty;
        $deliveryFee = 1500.00;
        $grandTotal = bcsub(bcadd((string)$productTotal, (string)$deliveryFee, 2), $cashbackRedeemed, 2);

        $order = Order::create([
            'order_number' => 'ORD-REV-' . strtoupper(bin2hex(random_bytes(4))),
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'logistics_company_id' => $this->company->id,
            'assigned_rider_id' => $this->rider->id,
            'order_type' => 'delivery',
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => $status,
            'status' => $status,
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'payment_reference' => 'PAY-REF-' . strtoupper(bin2hex(random_bytes(4))),
            'merchandise_subtotal' => $productTotal,
            'delivery_fee' => $deliveryFee,
            'cashback_redeemed' => $cashbackRedeemed,
            'grand_total' => $grandTotal,
            'total_amount' => $grandTotal,
            'vendor_net_90' => round($productTotal * 0.90, 2),
            'customer_cashback_5' => round($productTotal * 0.05, 2),
            'platform_margin_5' => round($productTotal * 0.05, 2),
            'logistics_fee_retained' => round($deliveryFee * 0.90, 2),
            'platform_delivery_fee' => round($deliveryFee * 0.10, 2),
            'currency' => 'NGN',
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'delivery_verification_otp' => '123456',
            'verification_code' => '123456',
            'paid_at' => Carbon::now()->subHours(2),
            'received_at' => $status === 'delivered' ? Carbon::now()->subHours(1) : null,
            'delivered_at' => $status === 'delivered' ? Carbon::now()->subHours(1) : null,
            'delivery_confirmed_at' => $status === 'delivered' ? Carbon::now()->subHours(1) : null,
            'refund_window_expires_at' => $status === 'delivered' ? Carbon::now()->addHours(23) : null,
            'guest_access_token' => bin2hex(random_bytes(16)),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $productTotal,
        ]);

        return ['order' => $order, 'item' => $item];
    }

    /**
     * Finding 1: A return cannot be requested before delivery
     */
    public function test_finding_1_return_cannot_be_requested_before_delivery(): void
    {
        $fixture = $this->createOrder('confirmed', 1);
        $order = $fixture['order'];
        $item = $fixture['item'];

        $service = app(ReturnAndRefundService::class);
        $res = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Item damaged'
        );

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('cannot be returned', $res['message']);
        $this->assertEquals(0, ReturnCase::count());
    }

    /**
     * Finding 2: Duplicate order_item_id entries within incoming request are aggregated and cannot overstate purchase quantity
     */
    public function test_finding_2_duplicate_order_items_aggregated_and_validated_strictly(): void
    {
        $fixture = $this->createOrder('delivered', 2);
        $order = $fixture['order'];
        $item = $fixture['item'];

        $service = app(ReturnAndRefundService::class);

        // Customer bought 2 units. In a single request, customer supplies 2 entries of 2 units each = 4 units
        $res = $service->createReturnCase(
            $this->customer,
            $order,
            [
                ['order_item_id' => $item->id, 'quantity' => 2],
                ['order_item_id' => $item->id, 'quantity' => 2],
            ],
            'defective_product',
            'Overstate quantity attempt'
        );

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Only 2 eligible units remain', $res['message']);
        $this->assertEquals(0, ReturnCase::count());
    }

    /**
     * Finding 3 & 10: Failed Paystack refund does not mark case refunded
     */
    public function test_finding_3_failed_paystack_refund_does_not_mark_case_refunded(): void
    {
        $fixture = $this->createOrder('delivered', 1);
        $order = $fixture['order'];
        $item = $fixture['item'];

        $service = app(ReturnAndRefundService::class);
        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Legitimate defect'
        );
        $case = $createRes['case'];
        $case->status = 'refund_pending';
        $case->save();

        // Mock Paystack to fail
        $mockPaystack = Mockery::mock(PaystackService::class);
        $mockPaystack->shouldReceive('initiateRefund')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Paystack declined: Insufficient balance on account',
            ]);

        $customService = new ReturnAndRefundService($mockPaystack);

        $res = $customService->adminDisburseRefund(
            $this->superAdmin,
            $case,
            'BANK-REF-112233',
            'proof.pdf'
        );

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Paystack gateway refund failed', $res['message']);
        $this->assertEquals('refund_pending', $case->fresh()->status);
        $this->assertNull($case->fresh()->refunded_at);
    }

    /**
     * Finding 4: Hub inspection cannot reset a refunded case
     */
    public function test_finding_4_hub_inspection_cannot_reset_refunded_case(): void
    {
        $fixture = $this->createOrder('delivered', 1);
        $order = $fixture['order'];
        $item = $fixture['item'];

        $service = app(ReturnAndRefundService::class);
        $createRes = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Defect description'
        );
        $case = $createRes['case'];
        $case->status = 'refunded';
        $case->save();

        // Attempt hub inspection on an already refunded case
        $inspRes = $service->recordHubInspection(
            $this->fleetUser,
            $case,
            $this->collectionPoint->id,
            'defective_as_claimed'
        );

        $this->assertFalse($inspRes['success']);
        $this->assertStringContainsString("already in 'refunded' status", $inspRes['message']);
        $this->assertEquals('refunded', $case->fresh()->status);
    }

    /**
     * Finding 5: Doorstep wallet credit is reversed upon manual cash refund disburse
     */
    public function test_finding_5_doorstep_wallet_credit_reversed_on_cash_refund(): void
    {
        $fixture = $this->createOrder('delivered', 1);
        $order = $fixture['order'];
        $item = $fixture['item'];

        $case = ReturnCase::create([
            'case_number' => ReturnCase::generateCaseNumber(),
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'source' => 'doorstep_refusal',
            'return_reason' => 'defective_product',
            'status' => 'refund_pending',
            'total_refundable_amount' => '10000.00',
        ]);

        $walletCredit = CustomerWalletCredit::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'return_case_id' => $case->id,
            'amount' => '10000.00',
            'credit_type' => 'doorstep_refund_credit',
            'status' => 'available',
        ]);

        $mockPaystack = Mockery::mock(PaystackService::class);
        $mockPaystack->shouldReceive('initiateRefund')
            ->once()
            ->andReturn([
                'success' => true,
                'refund_reference' => 'PSTK-REF-778899',
            ]);

        $service = new ReturnAndRefundService($mockPaystack);
        $res = $service->adminDisburseRefund(
            $this->superAdmin,
            $case,
            'BANK-REF-9988',
            'proof.pdf'
        );

        $this->assertTrue($res['success']);
        $this->assertEquals('refunded', $case->fresh()->status);
        $this->assertEquals('reversed_for_cash_refund', $walletCredit->fresh()->status);
    }

    /**
     * Finding 6: Delivery manifest cannot omit order items
     */
    public function test_finding_6_delivery_manifest_cannot_omit_order_items(): void
    {
        $fixture = $this->createOrder('delivered', 2);
        $order = $fixture['order'];
        $itemA = $fixture['item'];

        // Add second order item
        $itemB = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => 'Second Gadget',
            'quantity' => 1,
            'unit_price' => 5000.00,
            'line_total' => 5000.00,
        ]);

        $service = app(DoorstepHandshakeService::class);

        // Manifest only supplies item A, omitting item B
        $res = $service->processDoorstepHandover(
            $order,
            $this->rider,
            [
                [
                    'order_item_id' => $itemA->id,
                    'accepted_quantity' => 2,
                    'rejected_quantity' => 0,
                ],
            ],
            '123456',
            'photo.jpg'
        );

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Delivery manifest is incomplete', $res['message']);
    }

    /**
     * Finding 7: Delivery manifest cannot supply duplicate lines
     */
    public function test_finding_7_delivery_manifest_cannot_supply_duplicate_lines(): void
    {
        $fixture = $this->createOrder('delivered', 2);
        $order = $fixture['order'];
        $item = $fixture['item'];

        $service = app(DoorstepHandshakeService::class);

        // Duplicate line for same order_item_id
        $res = $service->processDoorstepHandover(
            $order,
            $this->rider,
            [
                [
                    'order_item_id' => $item->id,
                    'accepted_quantity' => 2,
                    'rejected_quantity' => 0,
                ],
                [
                    'order_item_id' => $item->id,
                    'accepted_quantity' => 2,
                    'rejected_quantity' => 0,
                ],
            ],
            '123456',
            'photo.jpg'
        );

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Duplicate order_item_id', $res['message']);
    }

    /**
     * Finding 8: Return calculation accounts for allocated discounts
     */
    public function test_finding_8_return_calculation_accounts_for_allocated_discounts(): void
    {
        // ₦20,000 merchandise, ₦1,500 delivery, ₦4,000 cashback redeemed discount
        $fixture = $this->createOrder('delivered', 2, '4000.00');
        $order = $fixture['order'];
        $item = $fixture['item'];

        $service = app(ReturnAndRefundService::class);
        $res = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]], // returning 1 out of 2 units
            'defective_product',
            'Defective unit'
        );

        $this->assertTrue($res['success']);
        $case = $res['case'];
        $case->status = 'refund_pending';
        $case->save();

        // Line subtotal = ₦10,000. Total merchandise = ₦20,000 (50% share).
        // Proportional discount = 50% of ₦4,000 = ₦2,000.
        // Refundable amount = ₦10,000 - ₦2,000 = ₦8,000.00.
        $this->assertEquals('8000.00', $case->total_refundable_amount);
        $this->assertDatabaseHas('return_items', [
            'return_case_id' => $case->id,
            'allocated_discount' => '2000.00',
            'refundable_amount' => '8000.00',
        ]);
    }

    /**
     * Finding 9: Package tier recognizes fragile as keke in checkout
     */
    public function test_finding_9_package_tier_recognizes_fragile_as_keke(): void
    {
        $fragileProduct = Product::create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'category_id' => $this->product->category_id,
            'name' => 'Fragile Glass Art',
            'slug' => 'fragile-glass-art',
            'sku' => 'GLS-001',
            'unit_price' => '15000.00',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'package_tier' => 'fragile',
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->customer);

        $address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'country_id' => Country::first()->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'contact_name' => 'Shopper Grace',
            'phone' => '08011112222',
            'address_line' => '12 Oron Road',
            'is_default' => true,
        ]);

        $response = $this->postJson('/api/v1/checkout/intent', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                [
                    'product_id' => $fragileProduct->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertNotNull($data);
    }

    /**
     * Finding 11: Paystack callback does not leak guest access token to arbitrary visitors
     */
    public function test_finding_11_payment_callback_does_not_leak_token_to_arbitrary_visitor(): void
    {
        $fixture = $this->createOrder('pending', 1);
        $order = $fixture['order'];

        $mockPaystack = Mockery::mock(PaystackService::class);
        $mockPaystack->shouldReceive('verifyTransaction')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Simulated verification failure']);
        $this->app->instance(PaystackService::class, $mockPaystack);

        // An arbitrary unauthenticated guest visits payment callback without having checkout session
        $response = $this->get('/payment/paystack/callback?reference=' . $order->payment_reference);

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringNotContainsString('token=' . $order->guest_access_token, $location);
    }

    /**
     * Finding 12: Customer wallet balance uses exact string format without float conversion
     */
    public function test_finding_12_customer_wallet_balance_uses_exact_bcmath_string(): void
    {
        $fixtureA = $this->createOrder('delivered', 1);
        $fixtureB = $this->createOrder('delivered', 1);

        CustomerWalletCredit::create([
            'customer_id' => $this->customer->id,
            'order_id' => $fixtureA['order']->id,
            'amount' => '15000.50',
            'credit_type' => 'doorstep_refund_credit',
            'status' => 'available',
        ]);

        CustomerWalletCredit::create([
            'customer_id' => $this->customer->id,
            'order_id' => $fixtureB['order']->id,
            'amount' => '25000.25',
            'credit_type' => 'doorstep_refund_credit',
            'status' => 'available',
        ]);

        $balance = CustomerWalletCredit::getAvailableBalance($this->customer->id);
        $this->assertEquals('40000.75', $balance);
    }

    /**
     * Finding 14: reorderRejectedItems clearly identifies quote preview contract
     */
    public function test_finding_14_reorder_rejected_items_returns_quote_preview_contract(): void
    {
        $fixture = $this->createOrder('delivered', 1);
        $order = $fixture['order'];
        $item = $fixture['item'];

        $case = ReturnCase::create([
            'case_number' => 'RET-QUOTE-001',
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'source' => 'doorstep_refusal',
            'return_reason' => 'defective_product',
            'status' => 'in_transit_back_to_vendor',
            'total_refundable_amount' => '10000.00',
        ]);

        ReturnItem::create([
            'return_case_id' => $case->id,
            'order_item_id' => $item->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
            'refundable_amount' => '10000.00',
            'item_condition' => 'damaged_defective',
            'status' => 'requested',
        ]);

        $this->actingAs($this->customer);

        $response = $this->postJson('/api/v1/customer/orders/reorder-rejected-items', [
            'case_number' => 'RET-QUOTE-001',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'Replacement quote prepared. Wallet credit cannot be used for checkout at launch; pay using an approved method if you choose to proceed.',
            'data' => [
                'operation' => 'quote_preview',
                'is_quote' => true,
                'wallet_credit_applied' => '0.00',
                'net_payable_cash' => '10000.00',
            ],
        ]);
    }

    /**
     * Finding 15: Directory traversal in return evidence is filtered
     */
    public function test_finding_15_directory_traversal_in_evidence_filtered(): void
    {
        $fixture = $this->createOrder('delivered', 1);
        $order = $fixture['order'];
        $item = $fixture['item'];

        $service = app(ReturnAndRefundService::class);
        $res = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Evidence test',
            null,
            [
                '../../etc/passwd',
                '/var/www/secret.key',
                'evidence/valid_photo.jpg',
            ]
        );

        $this->assertTrue($res['success']);
        $case = $res['case'];
        $case->status = 'refund_pending';
        $case->save();

        // Only the valid relative path should be attached
        $evidences = ReturnEvidence::where('return_case_id', $case->id)->get();
        $this->assertEquals(1, $evidences->count());
        $this->assertEquals('evidence/valid_photo.jpg', $evidences->first()->file_path);
    }

    /**
     * Hardening Check: Partial wallet credit spend blocks cash refund double-spend
     */
    public function test_partial_wallet_credit_spend_blocks_cash_refund(): void
    {
        $fixture = $this->createOrder('delivered', 1);
        $order = $fixture['order'];
        $item = $fixture['item'];

        $service = app(ReturnAndRefundService::class);
        $res = $service->createReturnCase(
            $this->customer,
            $order,
            [['order_item_id' => $item->id, 'quantity' => 1]],
            'defective_product',
            'Test partial wallet spend'
        );

        $case = $res['case'];
        $case->status = 'refund_pending';
        $case->save();

        // Simulate instant doorstep wallet credit creation
        CustomerWalletCredit::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'return_case_id' => $case->id,
            'amount' => '1000.00',
            'credit_type' => 'doorstep_refund',
            'status' => 'available',
        ]);

        // Customer spends part of it on another order, spawning a second 'redeemed' lot
        CustomerWalletCredit::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'return_case_id' => $case->id,
            'amount' => '200.00',
            'credit_type' => 'doorstep_refund',
            'status' => 'redeemed',
            'spent_on_order_id' => $order->id,
        ]);

        $disburseRes = $service->adminDisburseRefund($this->superAdmin, $case);
        $this->assertFalse($disburseRes['success']);
        $this->assertStringContainsString('partially or fully redeemed', $disburseRes['message']);
    }

    /**
     * Hardening Check: Production CSP excludes unsafe-eval
     */
    public function test_production_csp_excludes_unsafe_eval(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);

        // In testing / local, it can be present or absent, but let's test middleware directly under production environment
        $middleware = new \App\Http\Middleware\SecurityHeadersMiddleware();
        $request = \Illuminate\Http\Request::create('/', 'GET');
        
        config(['app.url' => 'https://newvmarket.ng']);
        $prodResponse = $middleware->handle($request, function () {
            return new \Illuminate\Http\Response('OK');
        });

        $prodCsp = $prodResponse->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString("'unsafe-eval'", $prodCsp);
    }

    public function test_pickup_reservation_cancellation_rechecks_state_under_lock_and_preserves_ordered_reservation(): void
    {
        $reservation = PickupReservation::create([
            'reservation_code' => 'RES-CANCEL-RACE-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'estimated_total' => '10000.00',
            'status' => 'order_placed',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/customer/pickup-reservations/RES-CANCEL-RACE-01/cancel')
            ->assertStatus(422)
            ->assertJson(['status' => false]);

        $this->assertSame('order_placed', $reservation->fresh()->status);

        $reservation->update(['status' => 'inspected_accepted']);
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/customer/pickup-reservations/RES-CANCEL-RACE-01/cancel')
            ->assertStatus(200)
            ->assertJson(['status' => true]);
        $this->assertSame('canceled', $reservation->fresh()->status);
    }

    public function test_pickup_inspection_rechecks_reservation_state_under_row_lock(): void
    {
        $reservation = PickupReservation::create([
            'reservation_code' => 'RES-INSPECT-RACE-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'estimated_total' => '10000.00',
            'status' => 'reserved',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $service = app(\App\\Services\\OrderSettlementService::class);
        $result = $service->approvePickupInspection($reservation, $this->vendorUser);

        $this->assertTrue($result['success']);
        $this->assertSame('inspected_accepted', $reservation->fresh()->status);

        $result = $service->approvePickupInspection($reservation->fresh(), $this->vendorUser);
        $this->assertFalse($result['success']);
        $this->assertSame('inspected_accepted', $reservation->fresh()->status);
    }

    public function test_admin_return_rejection_is_locked_and_refuses_financially_advanced_cases(): void
    {
        $fixture = $this->createOrder('delivered', 1);
        $order = $fixture['order'];
        $case = ReturnCase::create([
            'case_number' => 'RET-REJECT-GUARD-01',
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'return_reason' => 'defective_product',
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '10000.00',
            'paystack_refund_id' => 'RFD-ALREADY-DISPATCHED',
        ]);

        $service = app(ReturnAndRefundService::class);
        $result = $service->adminRejectReturn($this->superAdmin, $case, 'Customer claim rejected');
        $this->assertFalse($result['success']);
        $this->assertSame('refund_pending', $case->fresh()->status);
        $this->assertSame('RFD-ALREADY-DISPATCHED', $case->fresh()->paystack_refund_id);

        $case->status = 'under_review';
        $case->save();
        $accepted = $service->adminRejectReturn($this->superAdmin, $case->fresh(), 'Evidence does not support claim');
        $this->assertTrue($accepted['success']);
        $this->assertSame('rejected', $case->fresh()->status);
        $this->assertSame('rejected', $case->fresh()->resolution_type);
        $this->assertSame($this->superAdmin->id, $case->fresh()->reviewed_by_admin_id);
    }

    /**
     * Test P0 (Finding 2): Concurrent refund initiation is blocked by atomic claim token.
     */
    public function test_finding_2_concurrent_refund_initiation_is_blocked_by_claim_token(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RACE-001',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_type' => 'delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'grand_total' => '10000.00',
            'total_amount' => '10000.00',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '0.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'payment_reference' => 'ref_race_123',
        ]);

        $case = ReturnCase::create([
            'case_number' => ReturnCase::generateCaseNumber(),
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'return_reason' => 'defective_product',
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '10000.00',
            'refund_claim_token' => 'CLAIM-ACTIVE-12345',
            'refund_claimed_at' => \Illuminate\Support\Carbon::now(),
        ]);

        $service = app(ReturnAndRefundService::class);
        $res = $service->adminDisburseRefund($this->superAdmin, $case);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('currently being processed by another worker', $res['message']);
    }

    /**
     * Test P1 (Finding 3): Refund disbursement strictly requires prior approval into 'refund_pending'.
     */
    public function test_finding_3_refund_disbursement_strictly_requires_prior_approval(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-APPR-001',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_type' => 'delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'grand_total' => '5000.00',
            'total_amount' => '5000.00',
            'merchandise_subtotal' => '5000.00',
            'delivery_fee' => '0.00',
            'vendor_net_90' => '4500.00',
            'customer_cashback_5' => '250.00',
            'platform_margin_5' => '250.00',
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'payment_reference' => 'ref_appr_123',
        ]);

        $case = ReturnCase::create([
            'case_number' => ReturnCase::generateCaseNumber(),
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'return_reason' => 'wrong_item_sent',
            'status' => 'inspected',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '5000.00',
        ]);

        $service = app(ReturnAndRefundService::class);
        $res = $service->adminDisburseRefund($this->superAdmin, $case);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString("Requires prior Super Admin approval into 'refund_pending'", $res['message']);
    }

    /**
     * Test P1 (Finding 4): Late-payment settlement for out-of-stock orders initiates refund outside DB transaction.
     */
    public function test_finding_4_late_payment_settlement_initiates_gateway_refund(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-LATE-001',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_type' => 'delivery',
            'order_status' => 'cancelled',
            'payment_status' => 'pending',
            'grand_total' => '15000.00',
            'total_amount' => '15000.00',
            'merchandise_subtotal' => '15000.00',
            'delivery_fee' => '0.00',
            'vendor_net_90' => '13500.00',
            'customer_cashback_5' => '750.00',
            'platform_margin_5' => '750.00',
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
            'payment_reference' => 'ref_late_pay_777',
        ]);

        $mockPaystack = Mockery::mock(PaystackService::class);
        $mockPaystack->shouldReceive('initiateRefund')
            ->once()
            ->with('ref_late_pay_777', '15000.00', Mockery::type('string'))
            ->andReturn([
                'success' => true,
                'refund_reference' => 'RFD-AUTO-LATE-999',
            ]);
        $this->app->instance(PaystackService::class, $mockPaystack);

        $settlementService = app(\App\Services\OrderSettlementService::class);
        $res = $settlementService->settlePaystackOrder($order, [
            'status' => 'success',
            'reference' => 'ref_late_pay_777',
            'amount' => 1500000,
            'currency' => 'NGN',
            'id' => 999111,
            'channel' => 'card',
            'customer' => ['email' => $this->customer->email],
        ]);

        $this->assertTrue($res['success']);
        $this->assertEquals('REFUND_PENDING', $res['status']);
        $this->assertEquals('RFD-AUTO-LATE-999', $order->fresh()->paystack_refund_reference);
        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertTrue(in_array($order->fresh()->order_status, ['cancelled', 'paid_stock_unavailable']), 'Order must be cancelled or marked paid_stock_unavailable.');
        $this->assertEquals('refund_pending', $order->fresh()->vendor_settlement_status);
    }

    public function test_checkout_intent_idempotency_key_replays_same_intent_and_rejects_changed_payload(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->customer);
        $address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'country_id' => Country::first()->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'contact_name' => 'Shopper Grace',
            'phone' => '08011112222',
            'address_line' => '12 Oron Road',
            'is_default' => true,
        ]);

        $payload = [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ];

        $first = $this->withHeader('Idempotency-Key', 'checkout-retry-001')
            ->postJson('/api/v1/checkout/intent', $payload);
        $first->assertCreated();
        $firstId = $first->json('data.intent_id');

        $retry = $this->withHeader('Idempotency-Key', 'checkout-retry-001')
            ->postJson('/api/v1/checkout/intent', $payload);
        $retry->assertOk();
        $this->assertSame($firstId, $retry->json('data.intent_id'));
        $this->assertSame(1, \App\Models\CheckoutIntent::where('user_id', $this->customer->id)->count());

        $changed = $payload;
        $changed['items'][0]['quantity'] = 2;
        $this->withHeader('Idempotency-Key', 'checkout-retry-001')
            ->postJson('/api/v1/checkout/intent', $changed)
            ->assertStatus(409);
        $this->assertSame(1, \App\Models\CheckoutIntent::where('user_id', $this->customer->id)->count());

        // A different key represents an intentional distinct attempt, even for identical cart data.
        $distinctAttempt = $this->withHeader('Idempotency-Key', 'checkout-attempt-002')
            ->postJson('/api/v1/checkout/intent', $payload);
        $distinctAttempt->assertCreated();
        $this->assertNotSame($first->json('data.fingerprint'), $distinctAttempt->json('data.fingerprint'));
        $this->assertSame(2, \App\Models\CheckoutIntent::where('user_id', $this->customer->id)->count());
    }


    public function test_order_checkout_retry_reuses_order_and_never_reinitializes_paystack(): void
    {
        \Laravel\\Sanctum\\Sanctum::actingAs($this->customer);
        $this->product->update([
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => now(),
            'inventory_quantity' => 10,
            'is_in_stock' => true,
        ]);

        $gateway = Mockery::mock(PaystackService::class);
        $gateway->shouldReceive('initializeTransaction')->once()->andReturn([
            'success' => true,
            'authorization_url' => 'https://checkout.paystack.test/once',
            'access_code' => 'access_once',
            'reference' => 'VM-IDEMPOTENT-ORDER-001',
        ]);
        $this->app->instance(PaystackService::class, $gateway);

        $payload = [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'in_shop_pickup',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'paystack',
        ];

        $first = $this->withHeader('Idempotency-Key', 'order-retry-001')
            ->postJson('/api/v1/customer/orders/checkout', $payload);
        $first->assertCreated();
        $orderNumber = $first->json('data.order_number');

        $retry = $this->withHeader('Idempotency-Key', 'order-retry-001')
            ->postJson('/api/v1/customer/orders/checkout', $payload);
        $retry->assertOk();
        $this->assertSame($orderNumber, $retry->json('data.order_number'));
        $this->assertSame(
            $first->json('data.paystack.authorization_url'),
            $retry->json('data.paystack.authorization_url')
        );
        $this->assertSame(1, Order::where('customer_id', $this->customer->id)->count());
        $this->assertSame(1, Order::where('customer_id', $this->customer->id)
            ->where('checkout_idempotency_key', 'order-retry-001')->count());

        $changed = $payload;
        $changed['items'][0]['quantity'] = 2;
        $this->withHeader('Idempotency-Key', 'order-retry-001')
            ->postJson('/api/v1/customer/orders/checkout', $changed)
            ->assertStatus(409);
        $this->assertSame(1, Order::where('customer_id', $this->customer->id)->count());
    }

}
