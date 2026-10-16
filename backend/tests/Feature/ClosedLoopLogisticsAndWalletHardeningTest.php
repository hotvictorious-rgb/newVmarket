<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Country;
use App\Models\DisputeAndReturn;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BcMathService;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosedLoopLogisticsAndWalletHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $fleetOwner1;
    protected User $fleetOwner2;
    protected User $riderUser1;
    protected User $riderUser2;
    protected User $vendorUser;
    protected User $customerUser;
    protected LogisticsCompanyProfile $company1;
    protected LogisticsCompanyProfile $company2;
    protected RiderProfile $rider1;
    protected RiderProfile $rider2;
    protected VendorProfile $vendorProfile;
    protected Shop $shop;
    protected Category $category;
    protected Product $product;
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

        // Logistics Company 1
        $this->fleetOwner1 = User::create([
            'name' => 'Speedy Express HQ',
            'email' => 'hq@speedyexpress.ng',
            'password' => bcrypt('FleetPassword123!'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);
        $this->company1 = LogisticsCompanyProfile::create([
            'user_id' => $this->fleetOwner1->id,
            'company_name' => 'Speedy Express Logistics Ltd',
            'registration_number' => 'RC-112233',
            'contact_email' => 'hq@speedyexpress.ng',
            'contact_phone' => '+2348011223344',
            'hq_address' => '12 Oron Road, Uyo',
            'lga_id' => $this->uyoLga->id,
            'fleet_size' => 15,
            'bank_name' => 'First Bank of Nigeria',
            'account_number' => '3011223344',
            'account_name' => 'Speedy Express Logistics Ltd',
            'status' => 'active',
        ]);

        // Rider 1 under Company 1
        $this->riderUser1 = User::create([
            'name' => 'Musa Courier Alpha',
            'email' => 'musa@speedyexpress.ng',
            'password' => bcrypt('RiderPassword123!'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $this->rider1 = RiderProfile::create([
            'user_id' => $this->riderUser1->id,
            'logistics_company_id' => $this->company1->id,
            'assigned_lga_id' => $this->uyoLga->id,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'UYO-100-AB',
            'is_active' => true,
            'is_online' => true,
        ]);

        // Logistics Company 2 (Competitor Fleet)
        $this->fleetOwner2 = User::create([
            'name' => 'Rapid Cargo HQ',
            'email' => 'hq@rapidcargo.ng',
            'password' => bcrypt('FleetPassword123!'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);
        $this->company2 = LogisticsCompanyProfile::create([
            'user_id' => $this->fleetOwner2->id,
            'company_name' => 'Rapid Cargo Transits Ltd',
            'registration_number' => 'RC-445566',
            'contact_email' => 'hq@rapidcargo.ng',
            'contact_phone' => '+2348055667788',
            'hq_address' => '45 Ikot Ekpene Road, Uyo',
            'lga_id' => $this->uyoLga->id,
            'fleet_size' => 10,
            'status' => 'active',
        ]);

        // Rider 2 under Company 2
        $this->riderUser2 = User::create([
            'name' => 'Emeka Courier Beta',
            'email' => 'emeka@rapidcargo.ng',
            'password' => bcrypt('RiderPassword123!'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $this->rider2 = RiderProfile::create([
            'user_id' => $this->riderUser2->id,
            'logistics_company_id' => $this->company2->id,
            'assigned_lga_id' => $this->uyoLga->id,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'UYO-200-CD',
            'is_active' => true,
            'is_online' => true,
        ]);

        // Vendor Setup
        $this->vendorUser = User::create([
            'name' => 'Tech Depot Merchant',
            'email' => 'merchant@techdepot.ng',
            'password' => bcrypt('MerchantPassword123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->vendorProfile = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Tech Depot Akwa Ibom',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1029384756',
            'account_name' => 'Tech Depot Akwa Ibom',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
        ]);
        $this->shop = Shop::create([
            'vendor_id' => $this->vendorProfile->id,
            'name' => 'Tech Depot Uyo Central',
            'slug' => 'tech-depot-uyo-central',
            'address' => '88 Abak Road',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'is_primary_branch' => true,
            'is_active' => true,
        ]);
        $this->category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendorProfile->id,
            'category_id' => $this->category->id,
            'name' => 'Wireless Noise Canceling Headphones',
            'slug' => 'wireless-noise-canceling-headphones',
            'unit_price' => '10000.00',
            'inventory_quantity' => 20,
            'is_in_stock' => true,
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        // Customer Setup
        $this->customerUser = User::create([
            'name' => 'Chidi Customer',
            'email' => 'chidi@example.com',
            'password' => bcrypt('CustomerPassword123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);
    }

    /**
     * Test 1: Only the Platform can pay Logistics Companies.
     * When an order is delivered with OTP handshake, the platform credits 90% delivery cut to corporate wallet.
     * Fleet rider receives 0.00 direct credit from platform.
     */
    public function test_only_platform_can_pay_logistics_companies_and_fleet_rider_never_credited_by_platform(): void
    {
        $order = Order::create([
            'order_number' => 'VM-ORD-PROOF-001',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'rider_id' => $this->rider1->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'payment_reference' => Order::generateUniquePaymentReference(),
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '2000.00',
            'grand_total' => '12000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '1800.00', // 90% of 2,000.00
            'platform_delivery_fee' => '200.00',   // 10% retained by VMarket
            'verification_code' => '998877',
            'vendor_settlement_status' => 'held',
        ]);

        $this->assertEquals(0.00, (float)($this->company1->wallet_balance ?? 0));
        $this->assertEquals(0.00, (float)($this->rider1->current_balance ?? 0));

        $service = app(OrderSettlementService::class);
        $result = $service->verifyCustomerDeliveryOtp($order, '998877', $this->rider1);

        $this->assertTrue($result['success']);

        $this->company1->refresh();
        $this->rider1->refresh();

        // Mathematical Proof:
        // 1. Corporate Wallet credited with exactly ₦1,800.00 (90% of ₦2,000.00 delivery fee)
        $this->assertEquals(1800.00, (float)$this->company1->wallet_balance);

        // 2. Courier current_balance is STRICTLY 0.00. The platform escrow never pays the fleet courier directly!
        $this->assertEquals(0.00, (float)($this->rider1->current_balance ?? 0));
    }

    /**
     * Test 2: Only the Logistics Company can pay its riders.
     * Guaranteed Zero-Sum Transfer:
     * Debits Company Wallet, Credits Rider Wallet by exact same amount.
     */
    public function test_only_parent_logistics_company_can_pay_rider_with_zero_sum_conservation(): void
    {
        // Fund Company 1 with earned delivery fees (₦5,000.00)
        $this->company1->creditWallet('5000.00');
        $this->company1->refresh();
        $this->assertEquals(5000.00, (float)$this->company1->wallet_balance);
        $this->assertEquals(0.00, (float)($this->rider1->current_balance ?? 0));

        // Disburse ₦2,000.00 payroll to Courier 1
        $result = $this->company1->disburseToRider($this->rider1, '2000.00', 'Shift delivery earnings', $this->fleetOwner1);

        $this->assertTrue($result['success']);
        $this->company1->refresh();
        $this->rider1->refresh();

        // Mathematical Proof of Zero-Sum Money Conservation:
        // Company Wallet: 5,000.00 - 2,000.00 = 3,000.00
        $this->assertEquals(3000.00, (float)$this->company1->wallet_balance);
        // Rider Balance: 0.00 + 2,000.00 = 2,000.00
        $this->assertEquals(2000.00, (float)$this->rider1->current_balance);

        // Invariant: Company Wallet + Rider Balances = 3,000.00 + 2,000.00 = 5,000.00 (Net Money Delta = 0.00)
        $totalSystemBalance = (float)$this->company1->wallet_balance + (float)$this->rider1->current_balance;
        $this->assertEquals(5000.00, $totalSystemBalance);

        // Verify Double-Entry Journal Entries
        $ref = $result['reference'];
        $debitEntry = FinancialJournalEntry::where('transaction_reference', $ref)->where('entry_type', 'debit')->first();
        $creditEntry = FinancialJournalEntry::where('transaction_reference', $ref)->where('entry_type', 'credit')->first();

        $this->assertNotNull($debitEntry);
        $this->assertNotNull($creditEntry);
        $this->assertEquals('logistics_company_wallet', $debitEntry->account_name);
        $this->assertEquals(2000.00, (float)$debitEntry->amount);
        $this->assertEquals('rider_wallet', $creditEntry->account_name);
        $this->assertEquals(2000.00, (float)$creditEntry->amount);
    }

    /**
     * Test 3: Cross-Company Disbursement Barrier.
     * Company 1 is strictly forbidden from paying Rider 2 (who belongs to Company 2).
     */
    public function test_cross_company_rider_disbursement_is_strictly_blocked(): void
    {
        $this->company1->creditWallet('10000.00');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Cross-company disbursements are strictly prohibited/');

        // Company 1 attempts to pay Rider 2 (who belongs to Company 2)
        $this->company1->disburseToRider($this->rider2, '2000.00', 'Illegal cross-fleet payout');
    }

    /**
     * Test 4: Logistics Company Cannot Pay Riders What It Has Not Earned.
     * Prevents overdraw and protects logistics company from artificial wallet inflation.
     */
    public function test_logistics_company_cannot_disburse_more_than_available_wallet_balance(): void
    {
        $this->company1->creditWallet('1500.00');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Insufficient corporate wallet balance/');

        // Company 1 has ₦1,500.00; attempts to pay rider ₦3,000.00
        $this->company1->disburseToRider($this->rider1, '3000.00', 'Overdraft attempt');
    }

    /**
     * Test 5: Mass Assignment Protection Against Wallet Inflation.
     * Neither wallet_balance on LogisticsCompanyProfile nor current_balance on RiderProfile can be mass-assigned.
     */
    public function test_mass_assignment_cannot_inflate_corporate_or_rider_wallets(): void
    {
        $this->company1->update([
            'wallet_balance' => '99999999.00',
            'company_name' => 'Speedy Express Renamed',
        ]);
        $this->company1->refresh();

        // Mass assignment was discarded; wallet_balance remains 0.00!
        $this->assertEquals(0.00, (float)($this->company1->wallet_balance ?? 0));
        $this->assertEquals('Speedy Express Renamed', $this->company1->company_name);

        $this->rider1->update([
            'current_balance' => '88888888.00',
            'license_plate' => 'UYO-999-ZZ',
        ]);
        $this->rider1->refresh();

        // Mass assignment was discarded; current_balance remains 0.00!
        $this->assertEquals(0.00, (float)($this->rider1->current_balance ?? 0));
        $this->assertEquals('UYO-999-ZZ', $this->rider1->license_plate);
    }

    /**
     * Test 6: Strict Non-Negative Debit Floor.
     * Calling debitWallet or debitBalance with an amount exceeding balance throws RuntimeException.
     */
    public function test_strict_non_negative_debit_floor_prevents_wallet_overdraft(): void
    {
        $this->company1->creditWallet('100.00');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Insufficient corporate wallet balance/');

        // Attempt to debit 150.00 when only 100.00 available
        $this->company1->debitWallet('150.00');
    }

    /**
     * Test 7: Web Endpoint Authorization & Validation for Fleet Payroll.
     * Only authenticated logistics company can call the web disbursement endpoint.
     */
    public function test_logistics_web_disbursement_endpoint_enforces_auth_and_balance_checks(): void
    {
        $this->company1->creditWallet('5000.00');

        // Customer cannot access logistics disbursement endpoint
        $this->actingAs($this->customerUser)
            ->post(route('logistics.riders.disburse'), [
                'rider_id' => $this->rider1->id,
                'amount' => '1000.00',
            ])
            ->assertStatus(403);

        // Logistics Company 1 successfully disburses to Rider 1
        $response = $this->actingAs($this->fleetOwner1)
            ->post(route('logistics.riders.disburse'), [
                'rider_id' => $this->rider1->id,
                'amount' => '2500.00',
                'notes' => 'Weekly fuel and delivery performance allowance',
            ]);

        $response->assertSessionHas('status');

        $this->company1->refresh();
        $this->rider1->refresh();

        $this->assertEquals(2500.00, (float)$this->company1->wallet_balance);
        $this->assertEquals(2500.00, (float)$this->rider1->current_balance);

        // Logistics Company 1 attempts to pay Rider 2 (belonging to Company 2) -> 404/rejection
        $crossFleetResponse = $this->actingAs($this->fleetOwner1)
            ->post(route('logistics.riders.disburse'), [
                'rider_id' => $this->rider2->id,
                'amount' => '1000.00',
            ]);

        $crossFleetResponse->assertStatus(404);
    }

    /**
     * Test 8: Vendor Settlement Guard: Vendors Can NEVER Be Disbursed What They Did Not Earn.
     * Cannot disburse until refund window expires, with 0 disputes, and verified Paystack payment.
     */
    public function test_vendor_cannot_be_disbursed_what_they_did_not_earn_or_before_refund_window(): void
    {
        $service = app(OrderSettlementService::class);

        $order = Order::create([
            'order_number' => 'VM-ORD-PROOF-008',
            'customer_id' => $this->customerUser->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_reference' => Order::generateUniquePaymentReference(),
            'received_at' => Carbon::now(),
            'refund_window_expires_at' => Carbon::now()->addHours(24), // 24-hour refund window active!
            'vendor_settlement_status' => 'held',
            'merchandise_subtotal' => '10000.00',
            'grand_total' => '10000.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
        ]);

        // 1. Attempt disbursement during refund window -> strictly rejected
        $disburseResult1 = $service->disburseVendorSettlement($order, $this->superAdmin, 'storage/proofs/test.png');
        $this->assertFalse($disburseResult1['success']);
        $this->assertEquals('Order is not eligible for disbursement (current state: held).', $disburseResult1['message']);

        // 2. Advance time past 24 hours, but file an active dispute
        Carbon::setTestNow(Carbon::now()->addHours(25));
        DisputeAndReturn::create([
            'order_id' => $order->id,
            'customer_id' => $this->customerUser->id,
            'reason' => 'materially_defective',
            'customer_description' => 'Defective merchandise component missing.',
            'status' => 'under_review',
        ]);

        $service->evaluateMaturation($order);
        $order->refresh();

        $this->assertEquals('disputed', $order->vendor_settlement_status);

        // Attempt disbursement on disputed order -> strictly rejected
        $disburseResult2 = $service->disburseVendorSettlement($order, $this->superAdmin, 'storage/proofs/test.png');
        $this->assertFalse($disburseResult2['success']);
        $this->assertEquals('Order is not eligible for disbursement (current state: disputed).', $disburseResult2['message']);

        // Reset test time
        Carbon::setTestNow();
    }
}
