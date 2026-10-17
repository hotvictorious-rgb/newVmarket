<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorPayoutRequest;
use App\Models\VendorProfile;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VendorAndLogisticsUnifiedPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected User $logisticsUser;
    protected LogisticsCompanyProfile $company;
    protected Shop $shop;
    protected Lga $uyoLga;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // 0. Geographic Reference Records
        $country = Country::firstOrCreate(
            ['iso_code' => 'NG'],
            ['name' => 'Nigeria', 'phone_code' => '+234', 'currency_code' => 'NGN', 'is_active' => true]
        );
        $state = State::firstOrCreate(
            ['code' => 'AK'],
            ['country_id' => $country->id, 'name' => 'Akwa Ibom', 'is_active' => true]
        );
        $this->uyoLga = Lga::firstOrCreate(
            ['code' => 'UYO'],
            ['state_id' => $state->id, 'name' => 'Uyo', 'is_active' => true]
        );

        // 1. Super Admin
        $this->superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        // 2. Customer
        $this->customer = User::factory()->create([
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        // 3. Vendor Setup
        $this->vendorUser = User::factory()->create([
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Victorious Electronics Ltd',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
            'bank_name' => 'Zenith Bank PLC',
            'bank_code' => '057',
            'account_number' => '1012345678',
            'account_name' => 'Victorious Electronics Ltd',
            'bank_updated_at' => Carbon::now()->subDays(5), // Past 48h cooldown
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Victorious Electronics Main Shop',
            'slug' => 'victorious-electronics-main-shop',
            'address' => '12 Oron Road, Uyo',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'is_active' => true,
        ]);

        // 4. Logistics Company Setup
        $this->logisticsUser = User::factory()->create([
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $this->company = LogisticsCompanyProfile::create([
            'user_id' => $this->logisticsUser->id,
            'company_name' => 'Swift Courier Express',
            'status' => 'active',
            'wallet_balance' => '50000.00',
            'bank_name' => 'Access Bank PLC',
            'bank_code' => '044',
            'account_number' => '0123456789',
            'account_name' => 'Swift Courier Express Ltd',
            'bank_updated_at' => Carbon::now()->subDays(5), // Past 48h cooldown
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper to mock PaystackService for successful transfer recipient and initiation
     */
    protected function mockPaystackTransferSuccess(string $transferCode = 'TRF_TEST_123'): void
    {
        $mock = Mockery::mock(PaystackService::class);

        $mock->shouldReceive('createTransferRecipient')
            ->andReturn([
                'success' => true,
                'recipient_code' => 'RCP_' . uniqid(),
                'message' => 'Recipient verified on NIBSS',
            ]);

        $mock->shouldReceive('initiateTransfer')
            ->andReturn([
                'success' => true,
                'transfer_code' => $transferCode,
                'message' => 'Transfer queued for execution',
            ]);

        $this->app->instance(PaystackService::class, $mock);
    }

    /**
     * Order Factory Helper enforcing all relational and accounting constraints
     */
    protected function createTestOrder(array $attributes = []): Order
    {
        $amount = $attributes['merchandise_subtotal'] ?? '10000.00';
        $net90 = $attributes['vendor_net_90'] ?? bcmul((string)$amount, '0.90', 2);
        $cb5 = bcmul((string)$amount, '0.05', 2);
        $pm5 = bcmul((string)$amount, '0.05', 2);

        return Order::create(array_merge([
            'customer_id' => $this->customer->id,
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_number' => 'ORD-TST-' . uniqid(),
            'payment_reference' => 'REF-TST-' . uniqid(),
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'vendor_settlement_status' => 'eligible',
            'merchandise_subtotal' => $amount,
            'grand_total' => $amount,
            'delivery_fee' => '0.00',
            'cashback_redeemed' => '0.00',
            'vendor_net_90' => $net90,
            'customer_cashback_5' => $cb5,
            'platform_margin_5' => $pm5,
        ], $attributes));
    }

    /*
    |--------------------------------------------------------------------------
    | VENDOR MERCHANDISE PAYOUT TESTS
    |--------------------------------------------------------------------------
    */

    public function test_vendor_withdrawable_balance_does_not_equal_gross_earnings_due_to_escrow(): void
    {
        // Order 1: Paid, but in escrow (held - unfulfilled / within return window)
        $this->createTestOrder([
            'order_status' => 'out_for_delivery',
            'vendor_settlement_status' => 'held',
            'merchandise_subtotal' => '10000.00',
            'vendor_net_90' => '9000.00',
            'grand_total' => '10000.00',
        ]);

        // Order 2: Delivered, refund window passed (eligible)
        $this->createTestOrder([
            'order_status' => 'delivered',
            'vendor_settlement_status' => 'eligible',
            'merchandise_subtotal' => '20000.00',
            'vendor_net_90' => '18000.00',
            'grand_total' => '20000.00',
        ]);

        $this->vendor->refresh();

        // Gross Sales: 10,000 + 20,000 = 30,000
        $this->assertEquals('30000.00', $this->vendor->getGrossSales());

        // Lifetime Net Earnings: 9,000 + 18,000 = 27,000
        $this->assertEquals('27000.00', $this->vendor->getLifetimeNetEarnings());

        // Escrow Held (In-transit / within return window): 9,000
        $this->assertEquals('9000.00', $this->vendor->getEscrowHeldBalance());

        // Withdrawable Balance: ONLY the cleared 18,000 (NOT the 27,000 lifetime earnings!)
        $this->assertEquals('18000.00', $this->vendor->getWithdrawableBalance());
        $this->assertNotEquals($this->vendor->getLifetimeNetEarnings(), $this->vendor->getWithdrawableBalance());
    }

    public function test_vendor_cannot_request_payout_above_withdrawable_balance(): void
    {
        // Vendor has ₦5,000 eligible cleared balance
        $this->createTestOrder([
            'merchandise_subtotal' => '5555.55',
            'vendor_net_90' => '5000.00',
            'grand_total' => '5555.55',
            'vendor_settlement_status' => 'eligible',
        ]);

        // Attempt to request ₦8,000 (above ₦5,000 withdrawable balance)
        $response = $this->actingAs($this->vendorUser)->post(route('vendor.payout.request'), [
            'amount' => '8000.00',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, VendorPayoutRequest::where('vendor_id', $this->vendor->id)->count());
        $this->assertEquals('5000.00', $this->vendor->fresh()->getWithdrawableBalance());
    }

    public function test_vendor_cannot_request_payout_during_48h_bank_cooldown(): void
    {
        // Put vendor in active 48h bank cooldown
        $this->vendor->update(['bank_updated_at' => Carbon::now()->subHours(2)]);
        $this->assertTrue($this->vendor->isWithinBankCooldown());

        // Eligible balance exists
        $this->createTestOrder([
            'merchandise_subtotal' => '10000.00',
            'vendor_net_90' => '9000.00',
            'grand_total' => '10000.00',
            'vendor_settlement_status' => 'eligible',
        ]);

        $response = $this->actingAs($this->vendorUser)->post(route('vendor.payout.request'), [
            'amount' => '5000.00',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Security Cooldown Active', session('error'));
        $this->assertEquals(0, VendorPayoutRequest::where('vendor_id', $this->vendor->id)->count());
    }

    public function test_vendor_successful_payout_request_atomically_locks_funds(): void
    {
        // ₦20,000 eligible balance
        $this->createTestOrder([
            'merchandise_subtotal' => '22222.22',
            'vendor_net_90' => '20000.00',
            'grand_total' => '22222.22',
            'vendor_settlement_status' => 'eligible',
        ]);

        $this->assertEquals('20000.00', $this->vendor->getWithdrawableBalance());

        // Vendor requests ₦12,000
        $response = $this->actingAs($this->vendorUser)->post(route('vendor.payout.request'), [
            'amount' => '12000.00',
        ]);

        $response->assertSessionHas('status');

        $payout = VendorPayoutRequest::where('vendor_id', $this->vendor->id)->first();
        $this->assertNotNull($payout);
        $this->assertEquals('12000.00', number_format((float)$payout->amount, 2, '.', ''));
        $this->assertEquals('pending', $payout->status);
        $this->assertEquals('1012345678', $payout->account_number);

        // Funds are locked: Withdrawable balance is now ₦8,000!
        $this->assertEquals('8000.00', $this->vendor->fresh()->getWithdrawableBalance());

        // A second request for ₦10,000 must be rejected
        $secondResponse = $this->actingAs($this->vendorUser)->post(route('vendor.payout.request'), [
            'amount' => '10000.00',
        ]);
        $secondResponse->assertSessionHas('error');
        $this->assertEquals(1, VendorPayoutRequest::where('vendor_id', $this->vendor->id)->count());
    }

    public function test_super_admin_can_approve_vendor_payout_via_paystack(): void
    {
        $this->mockPaystackTransferSuccess('TRF_VND_SUCCESS_99');

        $order = $this->createTestOrder([
            'merchandise_subtotal' => '15000.00',
            'vendor_net_90' => '13500.00',
            'grand_total' => '15000.00',
            'vendor_settlement_status' => 'eligible',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '13500.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
            'notes' => 'Weekly merchandise settlement release',
        ]);

        $response->assertSessionHas('status');

        $payout->refresh();
        $this->assertEquals('approved', $payout->status);
        $this->assertEquals($this->superAdmin->id, $payout->approved_by_admin_id);
        $this->assertNotNull($payout->disbursed_at);
        $this->assertEquals('paystack_transfer:TRF_VND_SUCCESS_99', $payout->settlement_proof_path);

        // Order transitioned to settled
        $this->assertEquals('settled', $order->fresh()->vendor_settlement_status);

        // Double-entry journal checks
        $debit = FinancialJournalEntry::where('beneficiary_id', $this->vendor->id)
            ->where('account_name', 'vendor_payable')
            ->where('entry_type', 'debit')
            ->first();
        $this->assertNotNull($debit);
        $this->assertEquals('13500.00', number_format((float)$debit->amount, 2, '.', ''));

        $credit = FinancialJournalEntry::where('beneficiary_id', $this->vendor->id)
            ->where('account_name', 'cash_in_escrow')
            ->where('entry_type', 'credit')
            ->first();
        $this->assertNotNull($credit);
        $this->assertEquals('13500.00', number_format((float)$credit->amount, 2, '.', ''));
    }

    public function test_super_admin_can_reject_vendor_payout_and_funds_are_atomically_restored(): void
    {
        $this->createTestOrder([
            'merchandise_subtotal' => '20000.00',
            'vendor_net_90' => '18000.00',
            'grand_total' => '20000.00',
            'vendor_settlement_status' => 'eligible',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        // While pending, available withdrawable balance is ₦3,000 (18,000 - 15,000)
        $this->assertEquals('3000.00', $this->vendor->fresh()->getWithdrawableBalance());

        // Super Admin rejects the request
        $response = $this->actingAs($this->superAdmin)->post(route('admin.reject.vendor_payout'), [
            'payout_id' => $payout->id,
            'rejection_reason' => 'Bank account holder name mismatch with CAC documentation. Please update bank profile.',
        ]);

        $response->assertSessionHas('status');

        $payout->refresh();
        $this->assertEquals('rejected', $payout->status);
        $this->assertEquals('Bank account holder name mismatch with CAC documentation. Please update bank profile.', $payout->rejection_reason);

        // ATOMIC RESTORATION: Withdrawable balance is immediately restored to ₦18,000!
        $this->assertEquals('18000.00', $this->vendor->fresh()->getWithdrawableBalance());
    }

    /*
    |--------------------------------------------------------------------------
    | LOGISTICS FLEET PAYOUT TESTS
    |--------------------------------------------------------------------------
    */

    public function test_logistics_cannot_request_payout_above_wallet_balance(): void
    {
        $this->company->wallet_balance = '5000.00';
        $this->company->save();

        // Attempt to request ₦10,000 (above ₦5,000 wallet balance)
        $response = $this->actingAs($this->logisticsUser)->post(route('logistics.payout.request'), [
            'amount' => '10000.00',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, LogisticsPayoutRequest::where('logistics_company_id', $this->company->id)->count());
        $this->assertEquals('5000.00', $this->company->fresh()->wallet_balance);
    }

    public function test_logistics_cannot_request_payout_during_48h_bank_cooldown(): void
    {
        $this->company->update(['bank_updated_at' => Carbon::now()->subHours(6)]);
        $this->assertTrue($this->company->isWithinBankCooldown());

        $response = $this->actingAs($this->logisticsUser)->post(route('logistics.payout.request'), [
            'amount' => '5000.00',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Security Cooldown Active', session('error'));
        $this->assertEquals(0, LogisticsPayoutRequest::where('logistics_company_id', $this->company->id)->count());
    }

    public function test_logistics_successful_payout_request_debits_wallet(): void
    {
        $this->company->wallet_balance = '50000.00';
        $this->company->save();

        $response = $this->actingAs($this->logisticsUser)->post(route('logistics.payout.request'), [
            'amount' => '15000.00',
        ]);

        $response->assertSessionHas('status');

        $payout = LogisticsPayoutRequest::where('logistics_company_id', $this->company->id)->first();
        $this->assertNotNull($payout);
        $this->assertEquals('15000.00', number_format((float)$payout->amount, 2, '.', ''));
        $this->assertEquals('pending', $payout->status);

        // Wallet is atomically debited to ₦35,000
        $this->assertEquals('35000.00', $this->company->fresh()->wallet_balance);
    }

    public function test_super_admin_can_approve_logistics_payout_via_paystack(): void
    {
        $this->mockPaystackTransferSuccess('TRF_FLEET_SUCCESS_77');

        $this->company->wallet_balance = '50000.00';
        $this->company->save();

        // Place payout request
        $this->actingAs($this->logisticsUser)->post(route('logistics.payout.request'), [
            'amount' => '15000.00',
        ]);

        $payout = LogisticsPayoutRequest::where('logistics_company_id', $this->company->id)->first();
        $this->assertNotNull($payout);

        // Super Admin approves and disburses
        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.logistics_payout'), [
            'payout_id' => $payout->id,
            'notes' => 'Corporate weekly fleet remittance',
        ]);

        $response->assertSessionHas('status');

        $payout->refresh();
        $this->assertEquals('approved', $payout->status);
        $this->assertEquals($this->superAdmin->id, $payout->approved_by_admin_id);
        $this->assertEquals('paystack_transfer:TRF_FLEET_SUCCESS_77', $payout->settlement_proof_path);

        // Double-entry check
        $debit = FinancialJournalEntry::where('beneficiary_id', $this->company->id)
            ->where('account_name', 'logistics_payout_payable')
            ->where('entry_type', 'debit')
            ->first();
        $this->assertNotNull($debit);
        $this->assertEquals('15000.00', number_format((float)$debit->amount, 2, '.', ''));
    }

    public function test_super_admin_can_reject_logistics_payout_and_wallet_is_atomically_refunded(): void
    {
        $this->company->wallet_balance = '50000.00';
        $this->company->save();

        // Logistics requests ₦20,000 -> debits wallet to ₦30,000
        $this->actingAs($this->logisticsUser)->post(route('logistics.payout.request'), [
            'amount' => '20000.00',
        ]);
        $this->assertEquals('30000.00', $this->company->fresh()->wallet_balance);

        $payout = LogisticsPayoutRequest::where('logistics_company_id', $this->company->id)->first();
        $this->assertNotNull($payout);
        $this->assertEquals('pending', $payout->status);

        // Super Admin rejects the request
        $response = $this->actingAs($this->superAdmin)->post(route('admin.reject.logistics_payout'), [
            'payout_id' => $payout->id,
            'rejection_reason' => 'Active customer parcel dispute under investigation for lost transit item.',
        ]);

        $response->assertSessionHas('status');

        $payout->refresh();
        $this->assertEquals('rejected', $payout->status);
        $this->assertEquals('Active customer parcel dispute under investigation for lost transit item.', $payout->rejection_reason);

        // ATOMIC REFUND: Wallet balance is immediately restored to ₦50,000!
        $this->assertEquals('50000.00', $this->company->fresh()->wallet_balance);

        // Check reversal journal entry
        $reversal = FinancialJournalEntry::where('beneficiary_id', $this->company->id)
            ->where('account_name', 'logistics_payout_payable')
            ->where('entry_type', 'credit')
            ->first();
        $this->assertNotNull($reversal);
        $this->assertEquals('20000.00', number_format((float)$reversal->amount, 2, '.', ''));
    }

    /*
    |--------------------------------------------------------------------------
    | SCREEN RENDERING TESTS
    |--------------------------------------------------------------------------
    */

    public function test_vendor_payout_screen_renders_all_four_metric_cards_and_history(): void
    {
        $response = $this->actingAs($this->vendorUser)->get(route('vendor.payout.index'));

        $response->assertStatus(200);
        $response->assertSee('Gross Merchant Sales');
        $response->assertSee('Lifetime 90% Net Earnings');
        $response->assertSee('In-Transit / Escrow Hold');
        $response->assertSee('AVAILABLE TO WITHDRAW');
        $response->assertSee('Request Payout');
        $response->assertSee('Registered NUBAN Settlement Account');
    }

    public function test_logistics_wallet_screen_renders_earnings_and_withdrawable_cards(): void
    {
        $response = $this->actingAs($this->logisticsUser)->get(route('logistics.wallet.index'));

        $response->assertStatus(200);
        $response->assertSee('Lifetime Delivery Earnings');
        $response->assertSee('In-Transit Custody Escrow');
        $response->assertSee('AVAILABLE CORPORATE WALLET');
        $response->assertSee('Request Bank Withdrawal');
    }

    public function test_admin_settlements_desk_renders_both_vendor_and_logistics_queues(): void
    {
        // Seed a pending request for both
        VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '12500.00',
            'status' => 'pending',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Victorious Electronics Ltd',
        ]);

        LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '7500.00',
            'status' => 'pending',
            'bank_name' => 'Access Bank',
            'account_number' => '0123456789',
            'account_name' => 'Swift Courier Express Ltd',
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.settlements.index'));

        $response->assertStatus(200);
        $response->assertSee('Merchant Payout Requests');
        $response->assertSee('Logistics Fleet Requests');
        $response->assertSee('12,500.00');
        $response->assertSee('7,500.00');
        $response->assertSee('Approve (Paystack)');
        $response->assertSee('Reject');
    }
}
