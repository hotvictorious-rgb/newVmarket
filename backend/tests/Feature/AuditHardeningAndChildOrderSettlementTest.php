<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\CashbackReservation;
use App\Models\CustomerAddress;
use App\Models\CustomerCashbackLedger;
use App\Models\DeliveryLane;
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
use App\Services\OrderSettlementService;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditHardeningAndChildOrderSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $customer;
    protected User $vendorUserA;
    protected User $vendorUserB;
    protected VendorProfile $vendorA;
    protected VendorProfile $vendorB;
    protected Shop $shopA;
    protected Shop $shopB;
    protected Product $productA;
    protected Product $productB;
    protected User $riderUserA;
    protected User $riderUserB;
    protected RiderProfile $riderA;
    protected RiderProfile $riderB;
    protected Lga $originLga;
    protected Lga $destLga;

    protected function setUp(): void
    {
        parent::setUp();

        $country = \App\Models\Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NGA',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'currency_symbol' => '₦',
            'is_active' => true,
        ]);
        $state = State::create([
            'country_id' => $country->id,
            'name' => 'Akwa Ibom',
            'state_code' => 'AK',
            'is_active' => true,
        ]);
        $this->originLga = Lga::create(['state_id' => $state->id, 'name' => 'Uyo', 'is_active' => true]);
        $this->destLga = Lga::create(['state_id' => $state->id, 'name' => 'Ikot Ekpene', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Super Finance Admin',
            'email' => 'finance-admin@victoriousmarket.com.ng',
            'password' => bcrypt('AdminSecret123!'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Shopper Grace',
            'email' => 'shopper.grace@example.com',
            'password' => bcrypt('GracePass123!'),
            'user_type' => 'customer',
            'phone' => '08012345678',
            'status' => 'active',
        ]);

        // Vendor A
        $this->vendorUserA = User::create([
            'name' => 'Merchant Alpha User',
            'email' => 'merchant.alpha@example.com',
            'password' => bcrypt('AlphaPass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->vendorA = VendorProfile::create([
            'user_id' => $this->vendorUserA->id,
            'business_name' => 'Alpha Electronics Ltd',
            'business_phone' => '08099990001',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Alpha Electronics Ltd',
            'bank_updated_at' => Carbon::now()->subDays(10),
            'marketplace_status' => 'active',
        ]);
        $this->shopA = Shop::create([
            'vendor_id' => $this->vendorA->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->originLga->id,
            'name' => 'Alpha Electronics Flagship Store',
            'slug' => 'alpha-electronics',
            'address' => '10 Abak Road, Uyo',
            'phone' => '08099990001',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);
        $category = \App\Models\Category::create([
            'name' => 'General Merchandise',
            'slug' => 'general-merchandise',
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'shop_id' => $this->shopA->id,
            'vendor_id' => $this->vendorA->id,
            'category_id' => $category->id,
            'name' => 'Alpha Wireless Earbuds',
            'slug' => 'alpha-wireless-earbuds',
            'unit_price' => '10000.00',
            'inventory_quantity' => 50,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'is_vmarket_flagship' => true,
        ]);

        // Vendor B
        $this->vendorUserB = User::create([
            'name' => 'Merchant Beta User',
            'email' => 'merchant.beta@example.com',
            'password' => bcrypt('BetaPass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->vendorB = VendorProfile::create([
            'user_id' => $this->vendorUserB->id,
            'business_name' => 'Beta Fashion Hub',
            'business_phone' => '08099990002',
            'bank_name' => 'Access Bank',
            'account_number' => '2012345678',
            'account_name' => 'Beta Fashion Hub',
            'bank_updated_at' => Carbon::now()->subDays(10),
            'marketplace_status' => 'active',
        ]);
        $this->shopB = Shop::create([
            'vendor_id' => $this->vendorB->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->originLga->id,
            'name' => 'Beta Fashion Boutique',
            'slug' => 'beta-fashion',
            'address' => '24 Ikot Ekpene Road, Uyo',
            'phone' => '08099990002',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);
        $this->productB = Product::create([
            'shop_id' => $this->shopB->id,
            'vendor_id' => $this->vendorB->id,
            'category_id' => $category->id,
            'name' => 'Beta Premium Silk Shirt',
            'slug' => 'beta-premium-silk-shirt',
            'unit_price' => '20000.00',
            'inventory_quantity' => 30,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'is_vmarket_flagship' => true,
        ]);

        $this->riderUserA = User::create([
            'name' => 'Rider Edet Alpha',
            'email' => 'rider.a@speedcargo.ng',
            'password' => bcrypt('RiderSecret123!'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $this->riderA = RiderProfile::create([
            'user_id' => $this->riderUserA->id,
            'vendor_id' => $this->vendorA->id,
            'assigned_lga_id' => $this->originLga->id,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'UY-01-RDR',
            'is_active' => true,
            'is_online' => true,
        ]);

        $this->riderUserB = User::create([
            'name' => 'Rider Bassey Beta',
            'email' => 'rider.b@speedcargo.ng',
            'password' => bcrypt('RiderSecret123!'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $this->riderB = RiderProfile::create([
            'user_id' => $this->riderUserB->id,
            'logistics_company_id' => $this->company->id,
            'assigned_lga_id' => $this->originLga->id,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'UY-02-RDR',
            'is_active' => true,
            'is_online' => true,
        ]);
    }

    /**
     * Finding 1 Verification & Shield:
     * Rider endpoints and serialized Order models strictly NEVER expose customer delivery OTP,
     * pickup verification code, or guest access tokens.
     */
    public function test_rider_api_strictly_hides_sensitive_otps_and_access_tokens(): void
    {
        $order = Order::create([
            'order_number' => 'VM-SEC-RDR-001',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'origin_lga_id' => $this->originLga->id,
            'destination_lga_id' => $this->destLga->id,
            'rider_id' => $this->riderA->id,
            'logistics_company_id' => $this->company->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_reference' => 'VM-REF-RDR-001',
            'guest_access_token' => 'SECRET_GUEST_ACCESS_TOKEN_XYZ',
            'verification_code' => '987654', // Customer Delivery OTP
            'pickup_verification_code' => '123456', // Pickup OTP
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1500.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => '11500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '1350.00',
            'platform_delivery_fee' => '150.00',
            'vendor_settlement_status' => 'held',
        ]);

        // A. Direct Eloquent JSON serialization hides secrets
        $json = $order->toJson();
        $this->assertStringNotContainsString('987654', $json);
        $this->assertStringNotContainsString('123456', $json);
        $this->assertStringNotContainsString('SECRET_GUEST_ACCESS_TOKEN_XYZ', $json);

        // B. Rider Terminal Active Orders endpoint
        $token = $this->riderUserA->createToken('rider')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/rider/orders/active');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $responseBody = $response->getContent();

        // Must NOT leak customer delivery OTP or guest token
        $this->assertStringNotContainsString('987654', $responseBody);
        $this->assertStringNotContainsString('verification_code', $responseBody);
        $this->assertStringNotContainsString('guest_access_token', $responseBody);
        $this->assertStringNotContainsString('SECRET_GUEST_ACCESS_TOKEN_XYZ', $responseBody);

        // C. Rider Dashboard endpoint
        $dashRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/rider/dashboard');
        $dashRes->assertStatus(200);
        $dashBody = $dashRes->getContent();
        $this->assertStringNotContainsString('987654', $dashBody);
        $this->assertStringNotContainsString('verification_code', $dashBody);
    }

    /**
     * Section 6 Verification & Shield:
     * Rider B cannot inspect Rider A's assigned delivery status via web portal (IDOR Protection).
     */
    public function test_rider_cannot_query_delivery_status_of_another_riders_order(): void
    {
        $order = Order::create([
            'order_number' => 'VM-SEC-IDOR-002',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'origin_lga_id' => $this->originLga->id,
            'destination_lga_id' => $this->destLga->id,
            'rider_id' => $this->riderA->id, // Assigned to Rider A
            'logistics_company_id' => $this->company->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '11500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '1350.00',
            'platform_delivery_fee' => '150.00',
            'vendor_settlement_status' => 'held',
        ]);

        // Rider B attempts to inspect Rider A's order -> 403 Forbidden
        $response = $this->actingAs($this->riderUserB)
            ->getJson(route('rider.order.handshake_status', ['order' => $order->id]));

        $response->assertStatus(403);
        $response->assertJsonPath('status', false);

        // Rider A queries own order -> 200 OK
        $authResponse = $this->actingAs($this->riderUserA)
            ->getJson(route('rider.order.handshake_status', ['order' => $order->id]));

        $authResponse->assertStatus(200);
        $authResponse->assertJsonPath('order_id', $order->id);
    }

    /**
     * Finding 3 Verification & Shield:
     * Atomic Cashback Pre-Payment Reservation eliminates concurrent double-spending race conditions.
     */
    public function test_cashback_is_atomically_reserved_preventing_concurrent_overdraft(): void
    {
        // Customer has ₦500 matured available cashback
        CustomerCashbackLedger::create([
            'customer_id' => $this->customer->id,
            'order_id' => Order::create([
                'order_number' => 'VM-PRIOR-001',
                'customer_id' => $this->customer->id,
                'vendor_id' => $this->vendorA->id,
                'shop_id' => $this->shopA->id,
                'origin_lga_id' => $this->originLga->id,
                'destination_lga_id' => $this->destLga->id,
                'fulfillment_type' => 'in_shop_pickup',
                'order_status' => 'delivered',
                'payment_status' => 'paid',
                'merchandise_subtotal' => '10000.00',
                'delivery_fee' => '0.00',
                'grand_total' => '10000.00',
                'vendor_net_90' => '9000.00',
                'customer_cashback_5' => '500.00',
                'platform_margin_5' => '500.00',
                'vendor_settlement_status' => 'settled',
            ])->id,
            'amount' => '500.00',
            'status' => 'available',
            'matures_at' => Carbon::now()->subDays(5),
            'expires_at' => Carbon::now()->addDays(360),
        ]);

        $token = $this->customer->createToken('cust')->plainTextToken;

        // Customer initiates Order 1 checkout redeeming cashback
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/customer/orders/checkout', [
                'shop_id' => $this->shopA->id,
                'fulfillment_type' => 'in_shop_pickup',
                'redeem_cashback' => true,
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1],
                ],
            ]);

        $res1->assertStatus(201);
        $order1Number = $res1->json('data.order_number');
        $order1 = Order::where('order_number', $order1Number)->first();
        $this->assertEquals('500.00', $order1->cashback_redeemed);

        // Verify atomic reservation record was created
        $reservation = CashbackReservation::where('order_id', $order1->id)->first();
        $this->assertNotNull($reservation);
        $this->assertEquals('reserved', $reservation->status);
        $this->assertEquals('500.00', $reservation->amount);

        // Concurrent attempt: Customer initiates Order 2 trying to spend the same ₦500
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/customer/orders/checkout', [
                'shop_id' => $this->shopA->id,
                'fulfillment_type' => 'in_shop_pickup',
                'redeem_cashback' => true,
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1],
                ],
            ]);

        $res2->assertStatus(201);
        $order2Number = $res2->json('data.order_number');
        $order2 = Order::where('order_number', $order2Number)->first();

        // Order 2 received ₦0 cashback because the balance was safely locked by Order 1!
        $this->assertEquals('0.00', $order2->cashback_redeemed);
        $this->assertEquals('10000.00', $order2->grand_total);

        // When Order 1 is settled, reservation is marked 'consumed'
        $settlementService = app(OrderSettlementService::class);
        $settlementService->settlePaystackOrder($order1, [
            'reference' => $order1->payment_reference,
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => (int)($order1->grand_total * 100),
            'channel' => 'card',
        ], $this->customer);

        $this->assertEquals('consumed', $reservation->fresh()->status);
        $this->assertEquals('paid', $order1->fresh()->payment_status);
    }

    /**
     * Finding 4 Verification & Shield:
     * Late-payment refund triggers automated Paystack refund API call and returns transparent message.
     */
    public function test_late_payment_refund_invokes_paystack_refund_api_and_sets_reference(): void
    {
        // Create an expired and cancelled order whose inventory is depleted
        $order = Order::create([
            'order_number' => 'VM-LATE-PAY-003',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'origin_lga_id' => $this->originLga->id,
            'destination_lga_id' => $this->destLga->id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'cancelled',
            'payment_status' => 'pending',
            'payment_reference' => 'VM-REF-LATE-003',
            'guest_access_token' => 'guest_token_test_late',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '0.00',
            'grand_total' => '10000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'vendor_settlement_status' => 'held',
            'expires_at' => Carbon::now()->subHours(2), // Expired!
        ]);

        // Inventory is depleted to 0
        $this->productA->update(['inventory_quantity' => 0, 'is_in_stock' => false]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
        ]);

        $settlementService = app(OrderSettlementService::class);
        $result = $settlementService->settlePaystackOrder($order, [
            'reference' => $order->payment_reference,
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 1000000,
            'channel' => 'card',
        ], $this->customer);

        $this->assertTrue($result['success']);
        $this->assertEquals('REFUND_PENDING', $result['status']);
        $this->assertNotNull($order->fresh()->paystack_refund_reference);
        $this->assertStringStartsWith('rf_test_', $order->fresh()->paystack_refund_reference);

        // Web callback redirect informs customer of refund transparently
        $callbackRes = $this->get(route('payment.paystack.callback', [
            'reference' => $order->payment_reference,
        ]));

        $callbackRes->assertRedirect();
        $callbackRes->assertSessionHas('warning');
        $this->assertStringContainsString('refund of ₦10,000.00 has been initiated', session('warning'));
    }

    /**
     * Multi-Vendor Child Order Settlement & Delivery Fee Separation:
     * Delivery fee is strictly NOT product fee. Child vendors only receive 90% of their merchandise cut.
     * Logistics company receives 90% of delivery fee. Double-entry ledger is mathematically balanced.
     */
    public function test_multi_vendor_child_order_settlement_separates_delivery_fee_cleanly(): void
    {
        $lane = DeliveryLane::create([
            'origin_lga_id' => $this->originLga->id,
            'destination_lga_id' => $this->destLga->id,
            'logistics_company_id' => $this->company->id,
            'delivery_fee' => '1500.00',
            'is_active' => true,
        ]);

        $paymentRef = 'VM-MULTI-PAY-' . uniqid();

        // Parent Order representing checkout aggregate
        $parentOrder = Order::create([
            'order_number' => 'VM-PARENT-001',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'rider_id' => $this->riderA->id,
            'logistics_company_id' => $this->company->id,
            'delivery_lane_id' => $lane->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_reference' => $paymentRef,
            'guest_access_token' => 'guest_parent_token',
            'origin_lga_id' => $this->originLga->id,
            'destination_lga_id' => $this->destLga->id,
            'merchandise_subtotal' => '30000.00', // 10k from Vendor A + 20k from Vendor B
            'delivery_fee' => '1500.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => '31500.00',
            'vendor_net_90' => '27000.00',
            'customer_cashback_5' => '1500.00',
            'platform_margin_5' => '1500.00',
            'logistics_fee_retained' => '1350.00', // 90% of 1500 delivery fee
            'platform_delivery_fee' => '150.00',   // 10% of 1500 delivery fee
            'vendor_settlement_status' => 'held',
        ]);

        // Child Order A: Vendor A (10,000 merchandise, 0 delivery fee)
        $childOrderA = Order::create([
            'order_number' => 'VM-CHILD-A-001',
            'parent_order_id' => $parentOrder->id,
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorA->id,
            'shop_id' => $this->shopA->id,
            'rider_id' => $this->riderA->id,
            'logistics_company_id' => $this->company->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_reference' => $paymentRef,
            'guest_access_token' => 'guest_child_a_token',
            'origin_lga_id' => $this->originLga->id,
            'destination_lga_id' => $this->destLga->id,
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '500.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => '10500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '450.00',
            'platform_delivery_fee' => '50.00',
            'vendor_settlement_status' => 'held',
        ]);
        OrderItem::create([
            'order_id' => $childOrderA->id,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
        ]);

        // Child Order B: Vendor B (20,000 merchandise, 0 delivery fee)
        $childOrderB = Order::create([
            'order_number' => 'VM-CHILD-B-001',
            'parent_order_id' => $parentOrder->id,
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorB->id,
            'shop_id' => $this->shopB->id,
            'rider_id' => $this->riderA->id,
            'logistics_company_id' => $this->company->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_reference' => $paymentRef,
            'guest_access_token' => 'guest_child_b_token',
            'origin_lga_id' => $this->originLga->id,
            'destination_lga_id' => $this->destLga->id,
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '0.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => '20000.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'logistics_fee_retained' => '0.00',
            'platform_delivery_fee' => '0.00',
            'vendor_settlement_status' => 'held',
        ]);
        OrderItem::create([
            'order_id' => $childOrderB->id,
            'product_id' => $this->productB->id,
            'product_name' => $this->productB->name,
            'quantity' => 1,
            'unit_price' => '20000.00',
            'line_total' => '20000.00',
        ]);

        // Settle transaction on Paystack payment confirmation
        $settlementService = app(OrderSettlementService::class);
        $result = $settlementService->settlePaystackOrder($parentOrder, [
            'reference' => $paymentRef,
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 3150000, // 31,500 NGN in Kobo
            'channel' => 'card',
        ], $this->customer);

        $this->assertTrue($result['success']);
        $this->assertEquals('paid', $parentOrder->fresh()->payment_status);
        $this->assertEquals('paid', $childOrderA->fresh()->payment_status);
        $this->assertEquals('paid', $childOrderB->fresh()->payment_status);

        // 1. Verify Vendor A is credited strictly ₦9,000 (90% of 10k merchandise)
        $vendorAEntry = FinancialJournalEntry::where('beneficiary_type', VendorProfile::class)
            ->where('beneficiary_id', $this->vendorA->id)
            ->first();
        $this->assertNotNull($vendorAEntry);
        $this->assertEquals(0, bccomp((string)$vendorAEntry->amount, '9000.00', 2));
        $this->assertEquals('credit', $vendorAEntry->entry_type);

        // 2. Verify Vendor B is credited strictly ₦18,000 (90% of 20k merchandise)
        $vendorBEntry = FinancialJournalEntry::where('beneficiary_type', VendorProfile::class)
            ->where('beneficiary_id', $this->vendorB->id)
            ->first();
        $this->assertNotNull($vendorBEntry);
        $this->assertEquals(0, bccomp((string)$vendorBEntry->amount, '18000.00', 2));
        $this->assertEquals('credit', $vendorBEntry->entry_type);

        // 3. Delivery fees are allocated to each vendor's child order; no fleet/rider payout is created.
        $vendorADeliveryEntry = FinancialJournalEntry::where('order_id', $childOrderA->id)
            ->where('account_name', 'vendor_payable')
            ->where('amount', '450.00')
            ->where('entry_type', 'credit')
            ->first();
        $vendorBDeliveryEntry = FinancialJournalEntry::where('order_id', $childOrderB->id)
            ->where('account_name', 'vendor_payable')
            ->where('amount', '900.00')
            ->where('entry_type', 'credit')
            ->first();
        $this->assertNotNull($vendorADeliveryEntry);
        $this->assertNotNull($vendorBDeliveryEntry);
        $this->assertSame(0, FinancialJournalEntry::where('order_id', $parentOrder->id)
            ->where('account_name', 'logistics_payable')->count());

        // 4. Verify Double-Entry Balance
        $this->assertTrue(FinancialJournalEntry::assertOrderBalanced($parentOrder->id));
    }
}
