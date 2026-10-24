<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\CustomerCashbackLedger;
use App\Models\CashbackRecoveryLedger;
use App\Models\DeliveryLane;
use App\Models\DisputeAndReturn;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\ReturnCase;
use App\Models\Shop;
use App\Models\SupplierOffer;
use App\Models\State;
use App\Models\SystemSetting;
use App\Models\VendorProfile;
use App\Models\User;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class FinancialLedgerAndCashbackLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected State $state;
    protected Lga $uyoLga;
    protected Lga $ikotEkpeneLga;
    protected User $vendorUser;
    protected VendorProfile $vendorProfile;
    protected Shop $shop;
    protected Category $category;
    protected Product $product;
    protected User $customerUser;
    protected User $superAdmin;
    protected LogisticsCompanyProfile $fleetCompany;
    protected RiderProfile $rider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->uyoLga = Lga::where('name', 'Uyo')->firstOrFail();
        $this->ikotEkpeneLga = Lga::where('name', 'Ikot Ekpene')->first() ?? Lga::where('id', '!=', $this->uyoLga->id)->firstOrFail();

        // Super Admin
        $this->superAdmin = User::where('user_type', 'super_admin')->first() ?? User::create([
            'name' => 'Chief Executive',
            'email' => 'ceo@victoriousmarket.com',
            'phone' => '+2348000000001',
            'password' => bcrypt('password'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        // Vendor & Shop
        $this->vendorUser = User::where('user_type', 'vendor')->first();
        if (!$this->vendorUser) {
            $this->vendorUser = User::create([
                'name' => 'Emeka Merchant',
                'email' => 'emeka@merchant.com',
                'phone' => '+2348000000002',
                'password' => bcrypt('password'),
                'user_type' => 'vendor',
                'status' => 'active',
            ]);
            $vendorProfile = $this->vendorUser->vendorProfile()->create([
                'business_name' => 'Emeka Global Store',
                'marketplace_status' => 'active',
                'kyc_status' => 'verified',
                'bank_name' => 'First Bank of Nigeria',
                'account_number' => '1234567890',
                'account_name' => 'Emeka Global Store Ltd',
            ]);
        } else {
            $vendorProfile = $this->vendorUser->vendorProfile;
        }

        $this->vendorProfile = $vendorProfile;

        $this->shop = Shop::where('vendor_id', $vendorProfile->id)->first() ?? Shop::create([
            'vendor_id' => $vendorProfile->id,
            'lga_id' => $this->uyoLga->id,
            'name' => 'Emeka Uyo Central Branch',
            'address' => '10 Abak Road, Uyo',
            'pickup_enabled' => true,
            'is_active' => true,
        ]);

        $this->category = Category::first() ?? Category::create([
            'name' => 'Phones & Electronics',
            'slug' => 'phones-electronics',
            'is_active' => true,
        ]);

        $this->product = Product::where('vendor_id', $vendorProfile->id)->first() ?? Product::create([
            'vendor_id' => $vendorProfile->id,
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => 'Samsung Galaxy S24 Ultra',
            'slug' => 'samsung-galaxy-s24-ultra',
            'unit_price' => '10000.00',
            'inventory_quantity' => 20,
            'is_in_stock' => true,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
            'package_tier' => 'standard',
        ]);

        $this->product->update([
            'cost_price' => '5000.00',
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => Carbon::now(),
        ]);

        SupplierOffer::create([
            'product_id' => $this->product->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'wholesale_cost' => '5000.00',
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now(),
            'availability_confirmed_by' => $this->vendorUser->id,
            'availability_expires_at' => Carbon::now()->addHour(),
        ]);

        // Customer
        $this->customerUser = User::where('user_type', 'customer')->first();
        if (!$this->customerUser) {
            $this->customerUser = User::create([
                'name' => 'Anietie Customer',
                'email' => 'anietie@customer.com',
                'phone' => '+2348000000003',
                'password' => bcrypt('password'),
                'user_type' => 'customer',
                'status' => 'active',
            ]);
        }

        CustomerAddress::firstOrCreate([
            'user_id' => $this->customerUser->id,
            'lga_id' => $this->uyoLga->id,
        ], [
            'address_line' => '55 Oron Road, Uyo',
            'is_default' => true,
        ]);

        // Logistics Company & Rider
        $this->fleetCompany = LogisticsCompanyProfile::first();
        if (!$this->fleetCompany) {
            $logisticsUser = User::create([
                'name' => 'Swift Courier Ltd',
                'email' => 'swift@fleet.com',
                'phone' => '+2348000000004',
                'password' => bcrypt('password'),
                'user_type' => 'logistics_company',
                'status' => 'active',
            ]);

            $this->fleetCompany = LogisticsCompanyProfile::create([
                'user_id' => $logisticsUser->id,
                'company_name' => 'Swift Fleet Logistics',
                'hq_lga_id' => $this->uyoLga->id,
                'wallet_balance' => '0.00',
                'status' => 'active',
            ]);
        }

        $existingVendorRider = RiderProfile::where('vendor_id', $this->vendorProfile->id)->first();
        if ($existingVendorRider) {
            $this->rider = $existingVendorRider;
        } else {
            $riderUser = User::create([
                'name' => 'Kufre Dispatch',
                'email' => 'kufre@rider.com',
                'phone' => '+2348000000005',
                'password' => bcrypt('password'),
                'user_type' => 'rider',
                'status' => 'active',
            ]);

            $this->rider = RiderProfile::create([
                'user_id' => $riderUser->id,
                'vendor_id' => $this->vendorProfile->id,
                'assigned_lga_id' => $this->uyoLga->id,
                'is_active' => true,
                'is_online' => true,
            ]);
        }
        $this->rider->update(['vendor_id' => $this->vendorProfile->id, 'logistics_company_id' => null]);

        // Delivery Lane
        DeliveryLane::updateOrCreate([
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
        ], [
            'base_fee' => '1000.00',
            'bulky_surcharge' => '500.00',
            'logistics_company_base_payable' => '900.00',
            'logistics_company_bulky_surcharge' => '400.00',
            'estimated_delivery_hours_min' => 2,
            'estimated_delivery_hours_max' => 4,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Double-Entry Balanced General Ledger entries in settlePaystackOrder.
     * Mathematical invariant: Sum of Debits === Sum of Credits.
     */
    public function test_double_entry_balanced_general_ledger_on_paystack_settlement(): void
    {
        $reference = Order::generateUniquePaymentReference();

        // Merchandise subtotal = 10,000.00, delivery fee = 1,000.00, cashback redeemed = 500.00, grand total = 10,500.00
        // Splits:
        // Vendor Net 90% = 9,000.00
        // Cashback 5% reward = 500.00
        // Platform margin 5% = 500.00
        // Vendor delivery earning 90% = 900.00
        // Platform delivery 10% = 100.00
        // Total Retained Platform Revenue = 500.00 + 100.00 = 600.00
        //
        // DEBITS:
        // cash_in_escrow = 10,500.00
        // cashback_liability (redeemed) = 500.00
        // Total Debits = 11,000.00
        //
        // CREDITS:
        // vendor_payable = 9,900.00 (merchandise + delivery fee)
        // cashback_liability (reward accrued) = 500.00
        // platform_revenue = 600.00
        // Total Credits = 11,000.00
        // Difference Delta = 0.00!

        // Seed customer with matured available cashback of 500.00 from a previously completed order
        $pastOrder = Order::create([
            'order_number' => 'ORD-PAST-0001',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'payment_reference' => Order::generateUniquePaymentReference(),
            'merchandise_subtotal' => '10000.00',
            'grand_total' => '10000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
        ]);

        CustomerCashbackLedger::create([
            'customer_id' => $this->customerUser->id,
            'order_id' => $pastOrder->id,
            'amount' => '500.00',
            'status' => 'available',
            'matures_at' => Carbon::now()->subDays(5),
            'expires_at' => Carbon::now()->addDays(360),
        ]);

        $order = Order::create([
            'order_number' => 'ORD-20261016-0001',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'paystack',
            'payment_reference' => $reference,
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1000.00',
            'cashback_redeemed' => '500.00',
            'grand_total' => '10500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '900.00',
            'platform_delivery_fee' => '100.00',
            'verification_code' => '123456',
            'pickup_verification_code' => '654321',
            'vendor_settlement_status' => 'held',
        ]);

        $service = app(OrderSettlementService::class);
        $paystackData = [
            'reference' => $reference,
            'status' => 'success',
            'amount' => 1050000, // 10,500.00 NGN in Kobo
            'currency' => 'NGN',
            'channel' => 'card',
        ];

        $settlement = $service->settlePaystackOrder($order, $paystackData, $this->customerUser, 'paystack_test');
        $this->assertTrue($settlement['success']);
        $this->assertEquals('PAID', $settlement['status']);

        // Assert Order journal entries exist and are mathematically balanced
        $isBalanced = FinancialJournalEntry::assertOrderBalanced($order->id);
        $this->assertTrue($isBalanced, 'General ledger debits must strictly equal credits.');

        $entries = FinancialJournalEntry::where('order_id', $order->id)->get();
        $this->assertNotEmpty($entries);

        $totalDebits = $entries->where('entry_type', 'debit')->sum('amount');
        $totalCredits = $entries->where('entry_type', 'credit')->sum('amount');

        $this->assertEquals(11000.00, (float)$totalDebits);
        $this->assertEquals(11000.00, (float)$totalCredits);
    }

    /**
     * Test 2: Vendor-managed delivery earnings stay in vendor payable, with no fleet or rider wallet credit.
     */
    public function test_delivery_confirmation_does_not_pay_fleet_or_rider()
    {
        $order = Order::create([
            'order_number' => 'ORD-20261016-0002',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'rider_id' => $this->rider->id,
            'logistics_company_id' => $this->fleetCompany->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'payment_reference' => Order::generateUniquePaymentReference(),
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '11000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '900.00',
            'platform_delivery_fee' => '100.00',
            'verification_code' => '987654',
            'rider_arrived_at' => Carbon::now(),
            'vendor_settlement_status' => 'held',
        ]);

        $this->rider->update(['vendor_id' => null, 'shop_id' => null, 'logistics_company_id' => $this->fleetCompany->id]);
        $this->rider->refresh();

        $fleetWalletBefore = (float)$this->fleetCompany->wallet_balance;
        $riderBalanceBefore = (float)($this->rider->current_balance ?? 0);

        $service = app(OrderSettlementService::class);
        $result = $service->verifyCustomerDeliveryOtp($order, '987654', $this->rider, '/test/doorstep-proof.jpg');

        $this->assertTrue($result['success'], $result['message'] ?? 'Failed');

        $this->fleetCompany->refresh();
        $this->rider->refresh();

        // Vendor delivery earnings are recorded as vendor payable. Neither a fleet nor rider
        // wallet receives platform-managed delivery compensation.
        $this->assertEquals($fleetWalletBefore, (float)$this->fleetCompany->fresh()->wallet_balance);
        $this->assertEquals($riderBalanceBefore, (float)($this->rider->fresh()->current_balance ?? 0));
        // Vendor delivery share is posted when payment is settled, not by the delivery OTP.
        // Delivery confirmation itself must not create a fleet or rider payout.
        $this->assertDatabaseMissing('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'logistics_payable',
        ]);
        $this->assertDatabaseMissing('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'rider_payable',
        ]);
        $this->assertDatabaseMissing('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'logistics_payable',
        ]);
    }

    /**
     * Test 3: Webhook lookup strictly by payment_reference without metadata order_id fallback.
     */
    public function test_webhook_lookup_strictly_by_payment_reference(): void
    {
        $realRef = Order::generateUniquePaymentReference();
        $order = Order::create([
            'order_number' => 'ORD-20261016-0003',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_reference' => $realRef,
            'merchandise_subtotal' => '5000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '6000.00',
            'vendor_net_90' => '4500.00',
            'customer_cashback_5' => '250.00',
            'platform_margin_5' => '250.00',
            'logistics_fee_retained' => '900.00',
            'platform_delivery_fee' => '100.00',
            'verification_code' => '112233',
        ]);

        $secret = config('services.paystack.secret_key') ?? 'sk_test_mock_secret_key';
        config(['services.paystack.secret_key' => $secret]);

        // Attempt webhook with manipulated reference and metadata fallback attempting to settle order #3
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'reference' => 'unrelated_bogus_reference_xyz',
                'amount' => 600000,
                'currency' => 'NGN',
                'metadata' => [
                    'order_id' => $order->id,
                ],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, $secret);

        $response = $this->withHeaders([
            'x-paystack-signature' => $signature,
        ])->postJson(route('payment.paystack.webhook'), json_decode($payload, true));

        // Must reject with 404 - Not Found for transaction reference, NOT falling back to metadata['order_id']
        $response->assertStatus(404);
        $order->refresh();
        $this->assertEquals('pending', $order->payment_status);
    }

    /**
     * Test 4: Dynamic Admin Refund Window Governance & Cashback Maturation Lock.
     * Super Admin updates refund_window_hours to 48 hours; delivery handshakes adopt this setting.
     * Cashback stays pending_maturation and is strictly unredeemable until 48h has elapsed.
     */

    /**
     * Cashback matures only after the 24-hour return/refund window plus a separate 24-hour buffer.
     */
    public function test_cashback_matures_only_after_24_hour_refund_window_plus_24_hour_buffer(): void
    {
        $baseTime = Carbon::create(2026, 10, 16, 12, 0, 0);
        Carbon::setTestNow($baseTime);
        SystemSetting::set('refund_window_hours', 24);

        $order = Order::create([
            'order_number' => 'ORD-20261016-0004',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'rider_id' => $this->rider->id,
            'logistics_company_id' => $this->fleetCompany->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'payment_reference' => Order::generateUniquePaymentReference(),
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '21000.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'logistics_fee_retained' => '900.00',
            'platform_delivery_fee' => '100.00',
            'verification_code' => '445566',
            'rider_arrived_at' => $baseTime,
            'vendor_settlement_status' => 'held',
        ]);

        $this->rider->update(['vendor_id' => null, 'shop_id' => null, 'logistics_company_id' => $this->fleetCompany->id]);
        $this->rider->refresh();

        $service = app(OrderSettlementService::class);
        $result = $service->verifyCustomerDeliveryOtp($order, '445566', $this->rider, '/test/doorstep-proof.jpg');
        $this->assertTrue($result['success']);

        $order->refresh();
        $this->assertEquals('delivered', $order->order_status);
        $this->assertEquals(24, (int)$baseTime->diffInHours($order->refund_window_expires_at));

        $cashback = CustomerCashbackLedger::where('order_id', $order->id)->first();
        $this->assertNotNull($cashback);
        $this->assertEquals('pending_maturation', $cashback->status);
        $this->assertEquals(
            $order->refund_window_expires_at->copy()->addHours(24)->toIso8601String(),
            $cashback->matures_at->toIso8601String()
        );
        $this->assertEquals(
            $cashback->matures_at->copy()->addDays(365)->toIso8601String(),
            $cashback->expires_at->toIso8601String(),
            'The 365-day cashback validity period begins at maturity.'
        );

        Carbon::setTestNow($baseTime->copy()->addHours(24));
        $service->evaluateMaturation($order);
        $this->assertEquals('pending_maturation', $cashback->fresh()->status);

        Carbon::setTestNow($baseTime->copy()->addHours(25));
        $service->evaluateMaturation($order);
        $this->assertEquals('pending_maturation', $cashback->fresh()->status);

        Carbon::setTestNow($baseTime->copy()->addHours(49));
        $service->evaluateMaturation($order);
        $this->assertEquals('available', $cashback->fresh()->status);

        Carbon::setTestNow();
    }



    public function test_dispute_quarantines_cashback_and_vendor_settlement(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-20261016-0005',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'rider_id' => $this->rider->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_reference' => Order::generateUniquePaymentReference(),
            'received_at' => Carbon::now()->subHours(25),
            'refund_window_expires_at' => Carbon::now()->subHours(1),
            'vendor_settlement_status' => 'held',
            'customer_cashback_5' => '500.00',
            'merchandise_subtotal' => '10000.00',
            'grand_total' => '10000.00',
            'vendor_net_90' => '9000.00',
            'platform_margin_5' => '500.00',
        ]);

        CustomerCashbackLedger::create([
            'customer_id' => $this->customerUser->id,
            'order_id' => $order->id,
            'amount' => '500.00',
            'status' => 'pending_maturation',
            'matures_at' => $order->refund_window_expires_at->copy()->addHours(24),
            'expires_at' => $order->refund_window_expires_at->copy()->addHours(24)->addDays(365),
        ]);

        // File active dispute
        DisputeAndReturn::create([
            'order_id' => $order->id,
            'customer_id' => $this->customerUser->id,
            'reason' => 'materially_defective',
            'customer_description' => 'The screen is shattered and unusable upon opening.',
            'status' => 'under_review',
        ]);

        $service = app(OrderSettlementService::class);
        $service->evaluateMaturation($order);

        $order->refresh();
        $cashback = CustomerCashbackLedger::where('order_id', $order->id)->first();

        $this->assertEquals('disputed', $order->vendor_settlement_status);
        $this->assertEquals('quarantined_dispute', $cashback->status);
    }

    /**
     * Test 6: Abandoned Order Expiration & Inventory Restoration Command.
     */
    public function test_expired_abandoned_orders_restore_inventory(): void
    {
        $initialStock = $this->product->inventory_quantity; // 20

        // Create pending order created with stock reserved
        $order = Order::create([
            'order_number' => 'ORD-20261016-0006',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'in_shop_pickup',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_reference' => Order::generateUniquePaymentReference(),
            'expires_at' => Carbon::now()->subMinutes(15), // Expired 15 mins ago
            'merchandise_subtotal' => '20000.00',
            'grand_total' => '20000.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 3,
            'unit_price' => '10000.00',
            'line_total' => '30000.00',
        ]);

        // Simulate stock had been decremented by 3 at checkout
        $this->product->decrement('inventory_quantity', 3);
        $this->assertEquals($initialStock - 3, $this->product->fresh()->inventory_quantity);

        // Payment was abandoned at Paystack gateway
        \Illuminate\Support\Facades\Config::set('services.paystack.simulate_in_tests', false);
        \Illuminate\Support\Facades\Http::fake([
            'https://api.paystack.co/transaction/verify/*' => \Illuminate\Support\Facades\Http::response([
                'status' => true,
                'message' => 'Transaction not found or abandoned',
                'data' => [
                    'reference' => $order->payment_reference,
                    'status' => 'abandoned',
                ],
            ], 200),
        ]);

        // Run artisan expiration command
        $exitCode = Artisan::call('orders:expire-unpaid');
        $this->assertEquals(0, $exitCode);

        $order->refresh();
        $this->assertEquals('cancelled', $order->order_status);

        // Inventory must be fully restored!
        $this->assertEquals($initialStock, $this->product->fresh()->inventory_quantity);
    }

    /**
     * Test 7: Branch Alignment Check at Checkout.
     * Prevents selecting an arbitrary shop branch of a vendor if product belongs to another branch.
     */
    public function test_checkout_rejects_customer_selected_supplier_shop(): void
    {
        // Create second shop branch under same vendor
        $otherBranch = Shop::create([
            'vendor_id' => $this->shop->vendor_id,
            'lga_id' => $this->ikotEkpeneLga->id,
            'name' => 'Emeka Ikot Ekpene Branch',
            'slug' => 'emeka-ikot-ekpene-branch',
            'address' => '2 Hospital Road, Ikot Ekpene',
            'pickup_enabled' => true,
            'is_active' => false,
            'country_id' => $this->shop->country_id ?? 1,
            'state_id' => $this->shop->state_id ?? 1,
        ]);

        $this->actingAs($this->customerUser);

        $response = $this->post(route('checkout.process'), [
            'product_id' => $this->product->id, // belongs to $this->shop (Uyo)
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => 'Anietie Shopper',
            'customer_phone' => '08012345678',
            'shop_id' => $otherBranch->id, // Mismatched branch!
        ]);

        $response->assertSessionHasErrors(['shop_id']);
    }

    /**
     * Test 8: Open Redirect Sanitization in WebAuthController.
     */
    public function test_open_redirect_on_return_to_is_neutralized(): void
    {
        // Set deterministic known password
        $this->customerUser->password = bcrypt('SecretPassword123!');
        $this->customerUser->save();

        // Relative path should be allowed
        $response1 = $this->post(route('customer.login.post'), [
            'login' => $this->customerUser->email,
            'password' => 'SecretPassword123!',
            'return_to' => '/checkout/review',
        ]);
        $response1->assertRedirect('/checkout/review');
        Auth::logout();

        // External absolute URL should be neutralized to intended or default dashboard
        $response2 = $this->post(route('customer.login.post'), [
            'login' => $this->customerUser->email,
            'password' => 'SecretPassword123!',
            'return_to' => 'https://evil-phishing-domain.com/steal-creds',
        ]);
        $response2->assertRedirect(route('customer.dashboard'));
        Auth::logout();

        // Protocol-relative //evil.com must also be neutralized
        $response3 = $this->post(route('customer.login.post'), [
            'login' => $this->customerUser->email,
            'password' => 'SecretPassword123!',
            'return_to' => '//evil-phishing-domain.com',
        ]);
        $response3->assertRedirect(route('customer.dashboard'));
    }

    /**
     * Test 9: Non-Compounding Rule: Cashback is earned strictly on real money paid (excluding redeemed cashback).
     */
    public function test_cashback_earned_strictly_on_real_money_paid_excluding_redeemed_cashback(): void
    {
        // 1. Create a verified past order that generated ₦1,000 in matured cashback
        $pastOrder = Order::create([
            'order_number' => 'ORD-PAST-REWARD-001',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'payment_reference' => Order::generateUniquePaymentReference(),
            'merchandise_subtotal' => '20000.00',
            'grand_total' => '20000.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
        ]);

        CustomerCashbackLedger::create([
            'customer_id' => $this->customerUser->id,
            'order_id' => $pastOrder->id,
            'amount' => '1000.00',
            'status' => 'available',
            'matures_at' => Carbon::now()->subDay(),
            'expires_at' => Carbon::now()->addDays(365),
        ]);

        $checkoutService = app(\App\Services\CheckoutExecutionService::class);
        $this->product->update(['unit_price' => '1450000.00']);

        $payload = [
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'doorstep_delivery',
            'destination_lga_id' => $this->shop->lga_id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_price' => 1450000.00,
                ],
            ],
            'redeem_cashback' => true, // Redeem available cashback
        ];

        $result = $checkoutService->execute($this->customerUser, $payload);
        $this->assertTrue($result['success']);
        $order = $result['order'];

        // Merchandise subtotal = $this->product->unit_price (1,450,000.00)
        // Cashback redeemed = 1,000.00
        // Real money paid for merchandise = 1,450,000.00 - 1,000.00 = 1,449,000.00
        // 5% cashback earned MUST be 5% of 1,449,000.00 = 72,450.00, NEVER 5% of 1,450,000.00 (72,500.00)!
        $this->assertEquals((string)$this->product->unit_price, $order->merchandise_subtotal);
        $this->assertEquals('1000.00', $order->cashback_redeemed);
        $this->assertEquals('72450.00', $order->customer_cashback_5, 'Cashback must be 5% of real money paid (1,449,000.00), not gross subtotal.');
    }

    public function test_retailer_order_uses_item_cost_cashback_and_audited_logistics_company_override(): void
    {
        // This regression now follows the binding policy: supplier cost is quoted
        // non-bindingly before payment and captured only by verified settlement.
        // Isolate the intended offer: seeded fixtures may contain several offers
        // for this product, and cheapest-offer selection must remain authoritative.
        SupplierOffer::where('product_id', $this->product->id)->update(['is_available' => false]);
        $offer = SupplierOffer::where('product_id', $this->product->id)->orderBy('id')->firstOrFail();
        $offer->update([
            'wholesale_cost' => '6000.00',
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now(),
            'availability_confirmed_by' => $this->vendorUser->id,
            'availability_expires_at' => Carbon::now()->addHours(2),
        ]);
        Shop::where('vendor_id', $this->vendorProfile->id)
            ->where('id', '!=', $this->shop->id)
            ->update(['is_active' => false]);

        $reference = Order::generateUniquePaymentReference();
        $order = Order::create([
            'order_number' => 'VM-RETAILER-FIN-' . strtoupper(substr(sha1($reference), 0, 8)),
            'customer_id' => $this->customerUser->id,
            'vendor_id' => null,
            'shop_id' => null,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'retailer_model_order' => true,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'paystack',
            'payment_reference' => $reference,
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1000.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => '11000.00',
            'supplier_cost_total_snapshot' => null,
            'retailer_merchandise_margin' => null,
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '0.00',
            'logistics_fee_retained' => '900.00',
            'platform_delivery_fee' => '100.00',
            'logistics_company_payable_default_amount' => '900.00',
            'logistics_company_payable_amount' => '900.00',
            'sourcing_status' => 'awaiting_payment',
            'vendor_settlement_status' => 'held',
            'verification_code' => '123456',
            'pickup_verification_code' => '654321',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'line_total' => '10000.00',
            'cost_price' => '0.00',
        ]);

        $settlement = app(OrderSettlementService::class)->settlePaystackOrder($order, [
            'reference' => $reference,
            'status' => 'success',
            'amount' => 1100000,
            'currency' => 'NGN',
            'channel' => 'card',
        ], $this->customerUser, 'retailer_finance_test');

        $this->assertTrue($settlement['success']);
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'supplier_payable',
            'entry_type' => 'credit',
            'amount' => '6000.00',
        ]);
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'logistics_company_payable',
            'entry_type' => 'credit',
            'amount' => '900.00',
            'beneficiary_id' => null,
        ]);
        $this->assertDatabaseMissing('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'vendor_payable',
        ]);

        Order::where('id', $order->id)->update(['sourcing_status' => 'supplier_confirmed']);
        $service = app(\App\Services\LogisticsCompanyPayableService::class);
        $assigned = $service->assignCompany($order->fresh(), $this->superAdmin, $this->fleetCompany->id, 'Uyo route covered by approved fleet');
        $this->assertTrue($assigned['success']);
        $this->assertSame($this->fleetCompany->id, (int) $assigned['order']->logistics_company_id);

        // Commercial rate may still be adjusted after rider assignment, but not after
        // the rider confirms physical parcel custody.
        $this->rider->update(['logistics_company_id' => $this->fleetCompany->id, 'vendor_id' => null, 'shop_id' => null]);
        $preCustodyOrder = $assigned['order'];
        $preCustodyOrder->rider_id = $this->rider->id;
        $preCustodyOrder->pickup_confirmed_by_rider = false;
        $preCustodyOrder->save();

        $overridden = $service->override($preCustodyOrder->fresh(), $this->superAdmin, '1100.00', 'Rain and traffic surcharge agreed with logistics company');
        $this->assertTrue($overridden['success']);
        $this->assertSame('1100.00', $overridden['order']->logistics_company_payable_amount);
        $this->assertSame('900.00', $overridden['order']->logistics_company_payable_default_amount);
        $this->assertDatabaseHas('admin_audit_logs', [
            'target_type' => Order::class,
            'target_id' => $order->id,
            'action' => 'logistics_company_payable_overridden',
        ]);
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'delivery_subsidy_expense',
            'entry_type' => 'debit',
            'amount' => '100.00',
        ]);

        $lowered = $service->override($overridden['order'], $this->superAdmin, '800.00', 'Negotiated shorter Uyo route with lower fuel cost');
        $this->assertSame('800.00', $lowered['order']->logistics_company_payable_amount);
        $overridden = $service->override($lowered['order'], $this->superAdmin, '1100.00', 'Rain and traffic surcharge confirmed before custody');
        $this->assertSame('1100.00', $overridden['order']->logistics_company_payable_amount);

        $deliveredOrder = $overridden['order'];
        $deliveredOrder->pickup_confirmed_by_rider = true;
        $deliveredOrder->save();
        try {
            $service->override($deliveredOrder->fresh(), $this->superAdmin, '1200.00', 'Late override after rider custody');
            $this->fail('Payable override must be rejected after rider custody begins.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('after rider custody or customer handover', $exception->getMessage());
        }
        $deliveredOrder->order_status = 'delivered';
        $deliveredOrder->received_at = Carbon::now();
        $deliveredOrder->delivery_confirmed_by_customer = true;
        $deliveredOrder->proof_of_delivery_image = 'proof/retailer-delivery.jpg';
        $deliveredOrder->save();

        $walletBeforeRelease = (string) $this->fleetCompany->fresh()->wallet_balance;
        $released = $service->releaseAfterVerifiedDelivery($deliveredOrder->fresh());
        $this->assertTrue($released['success']);
        $this->assertFalse($released['replayed']);
        $this->assertSame(0, bccomp((string) $this->fleetCompany->fresh()->wallet_balance, bcadd($walletBeforeRelease, '1100.00', 2), 2));
        $this->assertSame(0, bccomp((string) $released['order']->logistics_company_wallet_credited_amount, '1100.00', 2));

        $replayedRelease = $service->releaseAfterVerifiedDelivery($released['order']->fresh());
        $this->assertTrue($replayedRelease['replayed']);
        $this->assertSame(0, bccomp((string) $this->fleetCompany->fresh()->wallet_balance, bcadd($walletBeforeRelease, '1100.00', 2), 2));

        $debits = (string) FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'debit')->sum('amount');
        $credits = (string) FinancialJournalEntry::where('order_id', $order->id)->where('entry_type', 'credit')->sum('amount');
        $this->assertSame(0, bccomp($debits, $credits, 2), 'Retailer settlement and company override adjustments must remain balanced.');
    }


    public function test_cashback_maturity_offsets_spent_cashback_recovery_and_starts_365_day_validity_at_actual_maturity(): void
    {
        $now = Carbon::create(2026, 10, 21, 10, 0, 0);
        Carbon::setTestNow($now);

        $order = Order::create([
            'order_number' => 'VM-CB-MATURE-' . strtoupper(substr(sha1((string) microtime(true)), 0, 8)),
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'retailer_model_order' => true,
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'payment_reference' => Order::generateUniquePaymentReference(),
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '11000.00',
            'supplier_cost_total_snapshot' => '6000.00',
            'retailer_merchandise_margin' => '3500.00',
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '0.00',
            'logistics_fee_retained' => '900.00',
            'platform_delivery_fee' => '100.00',
            'logistics_company_payable_default_amount' => '900.00',
            'logistics_company_payable_amount' => '900.00',
            'received_at' => $now->copy()->subHours(50),
            'refund_window_expires_at' => $now->copy()->subHours(1),
            'vendor_settlement_status' => 'held',
            'verification_code' => '123456',
            'pickup_verification_code' => '654321',
        ]);

        $returnCase = ReturnCase::create([
            'case_number' => 'RET-CB-' . strtoupper(substr(sha1((string) microtime(true)), 0, 8)),
            'customer_id' => $this->customerUser->id,
            'order_id' => $order->id,
            'vendor_id' => $this->vendorProfile->id,
            'return_reason' => 'prior partial merchandise refund',
            'status' => 'refunded',
            'total_refundable_amount' => '2000.00',
            'refund_delivery_fee' => false,
            'delivery_fee_refunded' => '0.00',
            'refunded_amount' => '2000.00',
        ]);

        $lot = CustomerCashbackLedger::create([
            'customer_id' => $this->customerUser->id,
            'order_id' => $order->id,
            'amount' => '500.00',
            'status' => 'pending_maturation',
            'matures_at' => $now->copy()->subHour(),
            'expires_at' => null,
        ]);

        $recovery = CashbackRecoveryLedger::create([
            'customer_id' => $this->customerUser->id,
            'order_id' => $order->id,
            'return_case_id' => $returnCase->id,
            'amount' => '100.00',
            'recovered_amount' => '0.00',
            'status' => 'pending_recovery',
            'idempotency_key' => 'test-cashback-recovery-' . $returnCase->id,
            'notes' => 'Fixture for future cashback offset.',
        ]);

        $result = app(\App\Services\CashbackMaturityService::class)->matureOrder($order);
        $this->assertTrue($result['matured']);
        $this->assertSame('available', $lot->fresh()->status);
        $this->assertSame(0, bccomp((string) $lot->fresh()->amount, '400.00', 2));
        $this->assertEquals($now->copy()->addDays(365)->toIso8601String(), $lot->fresh()->expires_at->toIso8601String());
        $this->assertSame('recovered', $recovery->fresh()->status);
        $this->assertSame(0, bccomp((string) $recovery->fresh()->recovered_amount, '100.00', 2));
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'cashback_recovery_receivable',
            'entry_type' => 'credit',
            'amount' => '100.00',
        ]);

        Carbon::setTestNow();
    }


    public function test_missing_logistics_tariff_can_be_reconciled_from_suspense_without_fee_fallback(): void
    {
        $reference = Order::generateUniquePaymentReference();
        $order = Order::create([
            'order_number' => 'VM-TARIFF-RECOVERY-' . strtoupper(substr(sha1($reference), 0, 8)),
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'retailer_model_order' => true,
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'payment_reference' => $reference,
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '11000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'supplier_cost_total_snapshot' => '8000.00',
            'logistics_company_id' => $this->fleetCompany->id,
            'received_at' => Carbon::now(),
            'delivery_confirmed_by_customer' => true,
            'proof_of_delivery_image' => '/test/proof-of-delivery.jpg',
            'logistics_company_payable_default_amount' => null,
            'logistics_company_payable_amount' => null,
            'logistics_company_payable_ledger_company_id' => null,
            'vendor_settlement_status' => 'held',
        ]);

        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'transaction_reference' => $reference,
            'event_id' => 'EVT-LOG-SUSPENSE-' . $reference,
            'beneficiary_type' => User::class,
            'beneficiary_id' => null,
            'account_name' => 'logistics_payable_suspense',
            'entry_type' => 'credit',
            'amount' => '1000.00',
            'currency' => 'NGN',
            'description' => 'Customer delivery fee held pending independent company tariff.',
        ]);

        $walletBefore = (string) $this->fleetCompany->fresh()->wallet_balance;

        $result = app(\App\Services\LogisticsCompanyPayableService::class)->override(
            $order,
            $this->superAdmin,
            '1200.00',
            'Explicit reconciliation of missing company corridor tariff'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('1200.00', (string) $result['order']->logistics_company_payable_amount);
        $this->assertSame((int) $this->fleetCompany->id, (int) $result['order']->logistics_company_payable_ledger_company_id);
        $this->assertTrue($result['payable_recovered_from_suspense']);
        $this->assertSame('1200.00', (string) $result['order']->logistics_company_wallet_credited_amount);
        $this->assertNotNull($result['order']->logistics_company_wallet_credited_at);
        $this->assertSame(bcadd($walletBefore, '1200.00', 2), (string) $this->fleetCompany->fresh()->wallet_balance);

        // A retried admin submission after delivery must be a no-op, not a second wallet credit.
        $retry = app(\App\Services\LogisticsCompanyPayableService::class)->override(
            $result['order'],
            $this->superAdmin,
            '1200.00',
            'Explicit reconciliation of missing company corridor tariff'
        );
        $this->assertTrue($retry['replayed']);
        $this->assertSame(bcadd($walletBefore, '1200.00', 2), (string) $this->fleetCompany->fresh()->wallet_balance);
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'logistics_payable_suspense',
            'entry_type' => 'debit',
            'amount' => '1000.00',
        ]);
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'logistics_company_payable',
            'entry_type' => 'credit',
            'amount' => '1200.00',
        ]);
        $this->assertDatabaseHas('financial_journal_entries', [
            'order_id' => $order->id,
            'account_name' => 'delivery_subsidy_expense',
            'entry_type' => 'debit',
            'amount' => '200.00',
        ]);

        $recoveryEntries = FinancialJournalEntry::where('order_id', $order->id)
            ->where('event_id', 'like', 'EVT-LOG-COMPANY-OVERRIDE-' . $order->id . '-R1-%')
            ->get();
        $debits = $recoveryEntries->where('entry_type', 'debit')->sum(fn ($entry) => (float) $entry->amount);
        $credits = $recoveryEntries->where('entry_type', 'credit')->sum(fn ($entry) => (float) $entry->amount);
        $this->assertEquals(1200.00, $debits);
        $this->assertEquals(1200.00, $credits);
    }

}
