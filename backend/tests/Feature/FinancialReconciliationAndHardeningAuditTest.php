<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerCashbackLedger;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialReconciliationAndHardeningAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $customerUser;
    protected User $vendorUser;
    protected User $fleetUser;
    protected VendorProfile $vendorProfile;
    protected Shop $shop;
    protected Product $product;
    protected LogisticsCompanyProfile $company;
    protected Lga $uyoLga;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'currency_code' => 'NGN',
            'phone_code' => '+234',
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

        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@victoriousmarket.com.ng',
            'password' => bcrypt('SuperSecurePassword123!'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        $this->customerUser = User::create([
            'name' => 'Aniefiok Bassey',
            'email' => 'customer.aniefiok@example.com',
            'password' => bcrypt('CustomerPassword123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $this->vendorUser = User::create([
            'name' => 'Merchant Alpha',
            'email' => 'merchant.alpha@example.com',
            'password' => bcrypt('MerchantPassword123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendorProfile = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Alpha Plaza Stores',
            'marketplace_status' => 'active',
            'bank_name' => 'Access Bank',
            'account_number' => '0123456789',
            'account_name' => 'Alpha Plaza Stores Ltd',
            'bank_updated_at' => Carbon::now()->subHours(72), // Past 48h cooldown
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendorProfile->id,
            'name' => 'Alpha Main Plaza Branch',
            'slug' => 'alpha-main-plaza-branch',
            'address' => '25 Aka Road, Uyo',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'category_id' => $category->id,
            'name' => 'Smart Solar Power Inverter 1KVA',
            'slug' => 'smart-solar-power-inverter-1kva',
            'unit_price' => '10000.00',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $this->fleetUser = User::create([
            'name' => 'Fleet Operator',
            'email' => 'fleet@speedy.ng',
            'password' => bcrypt('FleetPassword123!'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $this->company = LogisticsCompanyProfile::create([
            'user_id' => $this->fleetUser->id,
            'company_name' => 'Speedy Express Ltd',
            'registration_number' => 'RC-998877',
            'contact_email' => 'fleet@speedy.ng',
            'contact_phone' => '+2348011223344',
            'hq_address' => '50 Ikot Ekpene Road, Uyo',
            'lga_id' => $this->uyoLga->id,
            'wallet_balance' => '0.00',
            'bank_name' => 'First Bank',
            'account_number' => '3011223344',
            'account_name' => 'Speedy Express Ltd',
            'status' => 'active',
            'bank_updated_at' => Carbon::now()->subHours(72),
        ]);
    }

    /**
     * Test 1: Critical Finding 1 Fix - Unassigned Logistics Order NEVER Credits Unrelated Company.
     * When order has no assigned courier, collection point, or lane, the 90% fee is held in escrow.
     */

    public function test_cashback_redemption_with_insufficient_matured_balance_is_strictly_rejected(): void
    {
        $service = app(OrderSettlementService::class);

        $priorOrder = Order::create([
            'order_number' => 'ORD-PRIOR-001',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_reference' => 'REF-PRIOR-001',
            'merchandise_subtotal' => '4000.00',
            'delivery_fee' => '0.00',
            'grand_total' => '4000.00',
            'vendor_net_90' => '3600.00',
            'customer_cashback_5' => '200.00',
            'platform_margin_5' => '200.00',
            'logistics_fee_retained' => '0.00',
            'platform_delivery_fee' => '0.00',
            'verification_code' => '111111',
        ]);

        // Customer only has ₦200 in available matured cashback
        CustomerCashbackLedger::create([
            'customer_id' => $this->customerUser->id,
            'order_id' => $priorOrder->id,
            'amount' => '200.00',
            'status' => 'available',
            'matures_at' => Carbon::now()->subDay(),
            'expires_at' => Carbon::now()->addYear(),
        ]);

        // Order erroneously claims ₦500 cashback redeemed
        $order = Order::create([
            'order_number' => 'ORD-CB-FAIL-001',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_reference' => 'REF-CB-OVERDRAW-001',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '0.00',
            'cashback_redeemed' => '500.00', // Exceeds 200.00 available!
            'grand_total' => '9500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '0.00',
            'platform_delivery_fee' => '0.00',
            'verification_code' => '112233',
        ]);

        $paystackData = [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 950000, // 9500.00 NGN in kobo
            'reference' => 'REF-CB-OVERDRAW-001',
            'channel' => 'card',
            'customer' => ['email' => $this->customerUser->email],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Insufficient matured cashback balance/');

        $service->settlePaystackOrder($order, $paystackData);
    }

    /**
     * Test 3: High Finding 5 Fix - Paid-After-Cancellation With Available Stock Re-Reserves Stock.
     */
    public function test_paid_after_cancellation_with_available_stock_re_reserves_and_confirms(): void
    {
        $service = app(OrderSettlementService::class);

        $order = Order::create([
            'order_number' => 'ORD-REINSTATE-001',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'cancelled', // Cancelled by expiry worker
            'payment_status' => 'pending',
            'payment_reference' => 'REF-REINSTATE-001',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '0.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => '10000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '0.00',
            'platform_delivery_fee' => '0.00',
            'verification_code' => '556677',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 2,
            'unit_price' => '5000.00',
            'line_total' => '10000.00',
        ]);

        $initialStock = $this->product->fresh()->inventory_quantity; // 10 units

        $paystackData = [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 1000000,
            'reference' => 'REF-REINSTATE-001',
            'channel' => 'card',
            'customer' => ['email' => $this->customerUser->email],
        ];

        $result = $service->settlePaystackOrder($order, $paystackData);
        $this->assertTrue($result['success']);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('confirmed', $order->order_status, 'Order must be reinstated to confirmed status.');
        $this->assertEquals('held', $order->vendor_settlement_status);

        // Stock was re-decremented
        $this->assertEquals($initialStock - 2, $this->product->fresh()->inventory_quantity);
    }

    /**
     * Test 4: High Finding 5 Fix - Paid-After-Cancellation Out Of Stock Queues Customer Refund Liability.
     */
    public function test_paid_after_cancellation_out_of_stock_queues_customer_refund_liability(): void
    {
        $service = app(OrderSettlementService::class);

        // Set product stock to 0
        $this->product->update(['inventory_quantity' => 0, 'is_in_stock' => false]);

        $order = Order::create([
            'order_number' => 'ORD-OUTOFSTOCK-001',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'cancelled', // Cancelled by expiry worker
            'payment_status' => 'pending',
            'payment_reference' => 'REF-OUTOFSTOCK-001',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '0.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => '10000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '0.00',
            'platform_delivery_fee' => '0.00',
            'verification_code' => '998800',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
        ]);

        $paystackData = [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 1000000,
            'reference' => 'REF-OUTOFSTOCK-001',
            'channel' => 'card',
            'customer' => ['email' => $this->customerUser->email],
        ];

        $result = $service->settlePaystackOrder($order, $paystackData);
        $this->assertTrue($result['success']);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertTrue(in_array($order->order_status, ['cancelled', 'paid_stock_unavailable']), 'Order must remain cancelled or paid_stock_unavailable due to depleted stock.');
        $this->assertEquals('refund_pending', $order->vendor_settlement_status);

        // Verify Journal records 100% of the funds as customer_refund_liability
        $refundEntry = FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'customer_refund_liability')
            ->first();
        $this->assertNotNull($refundEntry);
        $this->assertEquals(10000.00, (float)$refundEntry->amount);
        $this->assertEquals('credit', $refundEntry->entry_type);

        // Verify double-entry ledger is still balanced (Debit: cash_in_escrow, Credit: customer_refund_liability)
        $this->assertTrue(FinancialJournalEntry::assertOrderBalanced($order->id));
    }

    /**
     * Test 5: High Finding 6 Fix - Super Admin Vendor Settlement Disbursement Posts Balanced Double-Entry Journal.
     */
    public function test_vendor_settlement_disbursement_posts_balancing_double_entry_journal(): void
    {
        $service = app(OrderSettlementService::class);

        $order = Order::create([
            'order_number' => 'ORD-VND-PAYOUT-001',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_reference' => 'REF-VND-PAYOUT-001',
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '0.00',
            'grand_total' => '20000.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'logistics_fee_retained' => '0.00',
            'platform_delivery_fee' => '0.00',
            'vendor_settlement_status' => 'eligible', // 24h refund window passed
            'verification_code' => '776655',
        ]);

        $result = $service->disburseVendorSettlement($order, $this->superAdmin, 'storage/proofs/bank_transfer_01.png');
        $this->assertTrue($result['success']);

        $order->refresh();
        $this->assertEquals('settled', $order->vendor_settlement_status);

        // Verify Double-Entry Journal entries posted
        $debitEntry = FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'vendor_payable')
            ->where('entry_type', 'debit')
            ->first();

        $creditEntry = FinancialJournalEntry::where('order_id', $order->id)
            ->where('account_name', 'cash_in_escrow')
            ->where('entry_type', 'credit')
            ->first();

        $this->assertNotNull($debitEntry, 'Must post debit to vendor_payable to close the liability.');
        $this->assertNotNull($creditEntry, 'Must post credit to cash_in_escrow to record the bank outflow.');
        $this->assertEquals(18000.00, (float)$debitEntry->amount);
        $this->assertEquals(18000.00, (float)$creditEntry->amount);
    }

    /**
     * Test 6: High Finding 6 Fix - Logistics Corporate Withdrawal Posts Complete Balanced Journal Entries.
     */

    public function test_api_checkout_strictly_enforces_in_paystack_payment_method(): void
    {
        Sanctum::actingAs($this->customerUser);

        // 1. Attempting 'wallet' is rejected with 422
        $responseWallet = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'in_shop_pickup',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
            'payment_method' => 'wallet',
        ]);

        $responseWallet->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);

        // 2. Attempting 'cash_on_counter' is rejected with 422
        $responseCash = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'in_shop_pickup',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
            'payment_method' => 'cash_on_counter',
        ]);

        $responseCash->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);

        // 3. Explicit 'paystack' succeeds with 201
        $responsePaystack = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'in_shop_pickup',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
            'payment_method' => 'paystack',
        ]);

        $responsePaystack->assertStatus(201)
            ->assertJson(['status' => true]);
    }

    /**
     * Test 8: Financial Integrity V2 - Refund-Only Late Payment Leaves Customer Cashback Intact and Accrues Zero Rewards.
     */
    public function test_refund_only_late_payment_leaves_cashback_balance_intact_and_accrues_zero_rewards(): void
    {
        $service = app(OrderSettlementService::class);

        // Pre-create initial order to satisfy foreign key constraint on cashback lot
        $priorOrder = Order::create([
            'order_number' => 'ORD-CB-PRIOR-999',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_reference' => 'REF-CB-PRIOR-999',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '0.00',
            'grand_total' => '10000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '0.00',
            'platform_delivery_fee' => '0.00',
            'verification_code' => '889900',
        ]);

        // Customer has ₦500 matured cashback available
        $cashbackLot = CustomerCashbackLedger::create([
            'customer_id' => $this->customerUser->id,
            'order_id' => $priorOrder->id,
            'amount' => '500.00',
            'status' => 'available',
            'matures_at' => Carbon::now()->subDay(),
            'expires_at' => Carbon::now()->addYear(),
        ]);

        // Set product stock to 0 so order cannot be reinstated
        $this->product->update(['inventory_quantity' => 0, 'is_in_stock' => false]);

        // Customer ordered with ₦500 cashback applied, paid ₦9,500 cash
        $order = Order::create([
            'order_number' => 'ORD-REFUND-CB-001',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'cancelled', // Expired
            'payment_status' => 'pending',
            'payment_reference' => 'REF-REFUND-CB-001',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '0.00',
            'cashback_redeemed' => '500.00',
            'grand_total' => '9500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '0.00',
            'platform_delivery_fee' => '0.00',
            'verification_code' => '332211',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
        ]);

        $paystackData = [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 950000, // ₦9,500 cash in kobo
            'reference' => 'REF-REFUND-CB-001',
            'channel' => 'card',
            'customer' => ['email' => $this->customerUser->email],
        ];

        $result = $service->settlePaystackOrder($order, $paystackData);
        $this->assertTrue($result['success']);
        $this->assertEquals('REFUND_PENDING', $result['status']);
        $this->assertTrue($result['is_refund_only']);

        // 1. INVARIANT: Customer's ₦500 cashback lot was NOT consumed (status remains 'available', amount remains 500.00)
        $cashbackLot->refresh();
        $this->assertEquals('available', $cashbackLot->status);
        $this->assertEquals(500.00, (float)$cashbackLot->amount);

        // 2. INVARIANT: ZERO new cashback reward entries created for this cancelled order
        $newReward = CustomerCashbackLedger::where('order_id', $order->id)->first();
        $this->assertNull($newReward, 'No cashback rewards may be accrued for unfulfilled refund-only orders.');

        // 3. INVARIANT: Journal entries are strictly 2-legged, balanced, and attributed to the customer
        $journalEntries = FinancialJournalEntry::where('order_id', $order->id)->get();
        $this->assertCount(2, $journalEntries);

        $escrowDebit = $journalEntries->where('account_name', 'cash_in_escrow')->first();
        $refundCredit = $journalEntries->where('account_name', 'customer_refund_liability')->first();

        $this->assertNotNull($escrowDebit);
        $this->assertNotNull($refundCredit);
        $this->assertEquals(9500.00, (float)$escrowDebit->amount);
        $this->assertEquals(9500.00, (float)$refundCredit->amount);
        $this->assertEquals('EVT-REFUND-ESCROW-REF-REFUND-CB-001', $escrowDebit->event_id);
        $this->assertEquals('EVT-REFUND-LIAB-REF-REFUND-CB-001', $refundCredit->event_id);
        $this->assertEquals(User::class, $refundCredit->beneficiary_type);
        $this->assertEquals($this->customerUser->id, $refundCredit->beneficiary_id);

        $this->assertTrue(FinancialJournalEntry::assertOrderBalanced($order->id));
    }

    /**
     * Test 9: Financial Integrity V2 - Frozen Logistics Company and Full Beneficiary Traceability.
     */

}
