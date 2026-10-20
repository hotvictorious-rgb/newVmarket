<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserRole;
use App\Models\Country;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use App\Models\ReturnCase;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorPayoutRequest;
use App\Models\VendorProfile;
use App\Models\VendorRecoveryLedger;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminTransferHardeningAndWalletIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $regularAdmin;
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

        // Geographic reference setup
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

        // Admins
        $this->superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);
        $this->regularAdmin = User::factory()->create([
            'user_type' => 'admin_employee',
            'status' => 'active',
        ]);

        // Customer
        $this->customer = User::factory()->create([
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        // Vendor
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
            'bank_updated_at' => Carbon::now()->subDays(5),
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

        // Logistics Company
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
            'bank_updated_at' => Carbon::now()->subDays(5),
        ]);

        // Mock Paystack
        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('createTransferRecipient')->andReturn([
            'success' => true,
            'recipient_code' => 'RCP_MOCK_123',
        ]);
        $mock->shouldReceive('initiateTransfer')->andReturn([
            'success' => true,
            'status' => 'success',
            'transfer_code' => 'TRF_MOCK_777',
            'message' => 'Transfer queued for execution',
        ]);
        $this->app->instance(PaystackService::class, $mock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

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

    public function test_vendor_payout_disbursement_blocked_if_vendor_is_suspended_or_trashed()
    {
        $this->vendor->update(['marketplace_status' => 'suspended']);

        $this->createTestOrder();

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '9000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Merchant store is inactive, suspended, or archived', session('error'));
        $this->assertEquals('pending', $payout->fresh()->status);
    }

    public function test_vendor_payout_disbursement_blocked_if_vendor_user_account_is_suspended()
    {
        $this->vendorUser->update(['status' => 'suspended']);

        $this->createTestOrder();

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '9000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Merchant user account is inactive or suspended', session('error'));
        $this->assertEquals('pending', $payout->fresh()->status);
    }

    public function test_vendor_payout_disbursement_blocked_if_vendor_has_never_sold_or_delivered()
    {
        // No orders created for vendor
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '9000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Merchant has no verified delivered sales records', session('error'));
        $this->assertEquals('pending', $payout->fresh()->status);
    }

    public function test_vendor_withdrawable_balance_does_not_automatically_offset_unrecovered_debts()
    {
        $order = $this->createTestOrder([
            'merchandise_subtotal' => '100000.00',
            'vendor_net_90' => '90000.00',
            'grand_total' => '100000.00',
        ]);

        $this->assertEquals('90000.00', $this->vendor->getWithdrawableBalance());

        // Create resolved return case as parent reference
        $returnCase = ReturnCase::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'case_number' => 'RET-CASE-999',
            'return_reason' => 'defective_product',
            'status' => 'refunded',
            'total_refundable_amount' => '25000.00',
        ]);

        // Incur unrecovered debt in VendorRecoveryLedger: ₦25,000.00
        VendorRecoveryLedger::create([
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'return_case_id' => $returnCase->id,
            'amount' => '25000.00',
            'status' => 'pending_recovery',
            'notes' => 'Damaged return compensation',
        ]);

        // Recovery debt remains a separate receivable; no automatic offset without authorised approval.
        $this->assertEquals('90000.00', $this->vendor->getWithdrawableBalance());
    }

    public function test_vendor_withdrawable_balance_quarantines_orders_with_active_return_cases()
    {
        $order = $this->createTestOrder([
            'merchandise_subtotal' => '50000.00',
            'vendor_net_90' => '45000.00',
            'grand_total' => '50000.00',
        ]);

        $this->assertEquals('45000.00', $this->vendor->getWithdrawableBalance());

        // Create an active ReturnCase
        ReturnCase::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'case_number' => 'RET-ORD-002',
            'return_reason' => 'damaged_in_transit',
            'status' => 'requested',
            'total_refundable_amount' => '20000.00',
        ]);

        // Order is immediately quarantined from withdrawable balance!
        $this->assertEquals('0.00', $this->vendor->fresh()->getWithdrawableBalance());
    }

    public function test_vendor_payout_rejection_records_balanced_double_entry_reversal()
    {
        $this->createTestOrder([
            'merchandise_subtotal' => '33333.33',
            'vendor_net_90' => '30000.00',
            'grand_total' => '33333.33',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '30000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.reject.vendor_payout'), [
            'payout_id' => $payout->id,
            'rejection_reason' => 'Invalid BVN details provided.',
        ]);

        $response->assertSessionHas('status');
        $this->assertEquals('rejected', $payout->fresh()->status);
        $this->assertEquals('Invalid BVN details provided.', $payout->fresh()->rejection_reason);

        // Verify balanced journal reversal entries
        $debitEntry = FinancialJournalEntry::where('beneficiary_id', $this->vendor->id)
            ->where('account_name', 'vendor_payable')
            ->where('entry_type', 'debit')
            ->latest('id')
            ->first();

        $creditEntry = FinancialJournalEntry::where('beneficiary_id', $this->vendor->id)
            ->where('account_name', 'vendor_escrow_holding')
            ->where('entry_type', 'credit')
            ->latest('id')
            ->first();

        $this->assertNotNull($debitEntry);
        $this->assertNotNull($creditEntry);
        $this->assertEquals('30000.00', number_format((float)$debitEntry->amount, 2, '.', ''));
        $this->assertEquals('30000.00', number_format((float)$creditEntry->amount, 2, '.', ''));
    }

    public function test_logistics_payout_disbursement_blocked_if_company_or_user_suspended()
    {
        $this->logisticsUser->update(['status' => 'suspended']);

        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '20000.00',
            'status' => 'pending',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.logistics_payout'), [
            'payout_id' => $payout->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Logistics company user account is inactive or suspended', session('error'));
        $this->assertEquals('pending', $payout->fresh()->status);
    }

    public function test_logistics_payout_rejection_records_balanced_double_entry_reversal()
    {
        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '15000.00',
            'status' => 'pending',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.reject.logistics_payout'), [
            'payout_id' => $payout->id,
            'rejection_reason' => 'Discrepancy in dispatch records.',
        ]);

        $response->assertSessionHas('status');
        $this->assertEquals('rejected', $payout->fresh()->status);

        // Verify balanced journal reversal entries
        $debitEntry = FinancialJournalEntry::where('beneficiary_id', $this->company->id)
            ->where('account_name', 'logistics_payout_payable')
            ->where('entry_type', 'debit')
            ->latest('id')
            ->first();

        $creditEntry = FinancialJournalEntry::where('beneficiary_id', $this->company->id)
            ->where('account_name', 'logistics_company_wallet')
            ->where('entry_type', 'credit')
            ->latest('id')
            ->first();

        $this->assertNotNull($debitEntry);
        $this->assertNotNull($creditEntry);
        $this->assertEquals('15000.00', number_format((float)$debitEntry->amount, 2, '.', ''));
        $this->assertEquals('15000.00', number_format((float)$creditEntry->amount, 2, '.', ''));
    }

    public function test_high_value_vendor_payout_strictly_requires_super_admin()
    {
        $this->createTestOrder([
            'merchandise_subtotal' => '15000000.00',
            'vendor_net_90' => '13500000.00',
            'grand_total' => '15000000.00',
        ]);

        // Payout of ₦12,000,000 exceeds single transfer threshold (₦10m)
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '12000000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        // Regular admin (even if bypassed past route middleware) is rejected at controller level
        $response = $this->withoutMiddleware(EnsureUserRole::class)
            ->actingAs($this->regularAdmin)
            ->post(route('admin.disburse.vendor_payout'), [
                'payout_id' => $payout->id,
            ]);
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Disbursement ceiling exceeded', session('error'));

        // SuperAdmin succeeds
        $responseSuccess = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);
        $responseSuccess->assertSessionHas('status');
        $this->assertEquals('approved', $payout->fresh()->status);
    }
}