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
use App\Models\ProcessedWebhookEvent;
use App\Services\PayoutFinalizationService;
use App\Models\VendorProfile;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VendorPayoutLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $vendorUser;
    protected VendorProfile $vendor;
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
                'status' => 'success',
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

        // The payout must match the whole eligible earning record, not a partial ₦12,000 slice.
        $partialResponse = $this->actingAs($this->vendorUser)->post(route('vendor.payout.request'), [
            'amount' => '12000.00',
        ]);
        $partialResponse->assertSessionHas('error');
        $this->assertEquals(0, VendorPayoutRequest::where('vendor_id', $this->vendor->id)->count());
        $this->assertEquals('20000.00', $this->vendor->fresh()->getWithdrawableBalance());

        // The full ₦20,000 earning record is an acceptable request.
        $response = $this->actingAs($this->vendorUser)->post(route('vendor.payout.request'), [
            'amount' => '20000.00',
        ]);
        $response->assertSessionHas('status');

        $payout = VendorPayoutRequest::where('vendor_id', $this->vendor->id)->first();
        $this->assertNotNull($payout);
        $this->assertEquals('20000.00', number_format((float)$payout->amount, 2, '.', ''));
        $this->assertEquals('pending', $payout->status);
        $this->assertEquals('1012345678', $payout->account_number);

        // Funds are locked in full while the payout request is unresolved.
        $this->assertEquals('0.00', $this->vendor->fresh()->getWithdrawableBalance());

        // A second request is rejected while the first request remains active.
        $secondResponse = $this->actingAs($this->vendorUser)->post(route('vendor.payout.request'), [
            'amount' => '5000.00',
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

    public function test_admin_cannot_reject_vendor_payout_while_paystack_outcome_is_unresolved(): void
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
            'gateway_status' => 'processing',
            'paystack_transfer_code' => 'TRF_ALREADY_SUBMITTED_01',
            'transfer_reference' => 'VND-UNRESOLVED-01',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        $this->assertEquals('3000.00', $this->vendor->fresh()->getWithdrawableBalance());

        $response = $this->actingAs($this->superAdmin)->post(route('admin.reject.vendor_payout'), [
            'payout_id' => $payout->id,
            'rejection_reason' => 'Bank account details require correction before retry.',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals('pending', $payout->fresh()->status);
        $this->assertEquals('3000.00', $this->vendor->fresh()->getWithdrawableBalance());
        $this->assertDatabaseMissing('financial_journal_entries', [
            'event_id' => 'EVT-MERCHANT-REJECT-' . $payout->id,
        ]);

        // "pending" is also unresolved, even if a delayed callback has not
        // persisted transfer identifiers yet.
        $payout->gateway_status = 'pending';
        $payout->paystack_transfer_code = null;
        $payout->transfer_reference = null;
        $payout->save();

        $pendingGatewayResponse = $this->actingAs($this->superAdmin)->post(route('admin.reject.vendor_payout'), [
            'payout_id' => $payout->id,
            'rejection_reason' => 'Bank account details require correction before retry.',
        ]);

        $pendingGatewayResponse->assertSessionHas('error');
        $this->assertEquals('pending', $payout->fresh()->status);
        $this->assertEquals('3000.00', $this->vendor->fresh()->getWithdrawableBalance());
        $this->assertDatabaseMissing('financial_journal_entries', [
            'event_id' => 'EVT-MERCHANT-REJECT-' . $payout->id,
        ]);
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
    | VENDOR PAYOUT SAFETY TESTS
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



    public function test_valid_transfer_reversed_reconciles_only_with_original_payout_and_request_journals(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-VALID-REVERSAL-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_type' => 'delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'settled',
            'merchandise_subtotal' => '5555.56',
            'delivery_fee' => '0.00',
            'grand_total' => '5555.56',
            'total_amount' => '5555.56',
            'vendor_net_90' => '5000.00',
            'customer_cashback_5' => '277.78',
            'platform_margin_5' => '277.78',
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '5000.00',
            'status' => 'approved',
            'gateway_status' => 'success',
            'disbursed_at' => Carbon::now(),
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Test Vendor',
            'transfer_reference' => 'VND-PAYOUT-VALID-REVERSAL-01',
            'paystack_transfer_code' => 'TRF_VALID_REVERSAL_01',
        ]);

        \App\Models\VendorPayoutAllocation::create([
            'vendor_payout_request_id' => $payout->id,
            'order_id' => $order->id,
            'allocated_amount' => '5000.00',
            'status' => 'allocated',
        ]);

        foreach ([
            ['event_id' => 'EVT-MERCHANT-REQ-' . $payout->id, 'account_name' => 'vendor_payable', 'entry_type' => 'debit'],
            ['event_id' => 'EVT-MERCHANT-REQ-' . $payout->id, 'account_name' => 'vendor_payout_payable', 'entry_type' => 'credit'],
            ['event_id' => 'EVT-VND-PAYOUT-' . $payout->id, 'account_name' => 'vendor_payout_payable', 'entry_type' => 'debit'],
            ['event_id' => 'EVT-VND-PAYOUT-' . $payout->id, 'account_name' => 'cash_in_escrow', 'entry_type' => 'credit'],
        ] as $entry) {
            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => 'VND-PAYOUT-VALID-REVERSAL-01',
                'event_id' => $entry['event_id'],
                'beneficiary_type' => VendorProfile::class,
                'beneficiary_id' => $this->vendor->id,
                'account_name' => $entry['account_name'],
                'entry_type' => $entry['entry_type'],
                'amount' => '5000.00',
                'currency' => 'NGN',
                'description' => 'Original payout journal evidence for reversal test',
            ]);
        }

        $payloadData = [
            'id' => 'evt_valid_transfer_reversed_01',
            'event' => 'transfer.reversed',
            'data' => [
                'reference' => 'VND-PAYOUT-VALID-REVERSAL-01',
                'transfer_code' => 'TRF_VALID_REVERSAL_01',
                'amount' => 500000,
                'currency' => 'NGN',
                'reason' => 'Provider confirmed successful transfer reversal',
            ],
        ];
        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);
        $journalCountBefore = FinancialJournalEntry::count();

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);
        $payout->refresh();
        $this->assertSame('rejected', $payout->status);
        $this->assertSame('failed', $payout->gateway_status);
        $this->assertSame('eligible', $order->fresh()->vendor_settlement_status);
        $this->assertSame($journalCountBefore + 2, FinancialJournalEntry::count());
        $this->assertDatabaseHas('processed_webhook_events', [
            'event_id' => 'evt_valid_transfer_reversed_01',
            'status' => 'completed',
        ]);
    }

    public function test_stale_transfer_success_generation_cannot_finalize_logistics_payout(): void
    {
        $logisticsUser = User::factory()->create([
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);
        $company = LogisticsCompanyProfile::create([
            'user_id' => $logisticsUser->id,
            'company_name' => 'Stale Success Fleet Ltd',
            'lga_id' => $this->uyoLga->id,
            'status' => 'active',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Stale Success Fleet Ltd',
        ]);
        $company->wallet_balance = '25000.00';
        $company->save();

        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $company->id,
            'amount' => '5000.00',
            'status' => 'approved',
            'gateway_status' => 'pending',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Stale Success Fleet Ltd',
            'transfer_reference' => 'FLEET-PAYOUT-STALE-SUCCESS-01',
            'paystack_transfer_code' => 'TRF_FLEET_STALE_SUCCESS_01',
        ]);
        $event = ProcessedWebhookEvent::create([
            'event_id' => 'evt_fleet_stale_success_generation_01',
            'event_type' => 'transfer.success',
            'payload_hash' => hash('sha256', '{"event":"transfer.success"}'),
            'payload' => '{"event":"transfer.success"}',
            'status' => 'processing',
            'attempts' => 2,
        ]);
        $journalCountBefore = FinancialJournalEntry::count();
        $walletBefore = (string) $company->fresh()->wallet_balance;

        $result = app(PayoutFinalizationService::class)->finalizeLogisticsPayout(
            $payout,
            [
                'reference' => 'FLEET-PAYOUT-STALE-SUCCESS-01',
                'transfer_code' => 'TRF_FLEET_STALE_SUCCESS_01',
                'status' => 'success',
                'amount' => 500000,
                'currency' => 'NGN',
            ],
            null,
            null,
            (int) $event->id,
            1
        );

        $this->assertFalse($result['success']);
        $this->assertTrue($result['stale_webhook_claim'] ?? false);
        $this->assertSame('approved', $payout->fresh()->status);
        $this->assertSame('pending', $payout->fresh()->gateway_status);
        $this->assertSame($walletBefore, (string) $company->fresh()->wallet_balance);
        $this->assertSame('processing', $event->fresh()->status);
        $this->assertSame(2, (int) $event->fresh()->attempts);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
    }

    public function test_stale_transfer_reversed_generation_cannot_mutate_logistics_payout(): void
    {
        $logisticsUser = User::factory()->create([
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);
        $company = LogisticsCompanyProfile::create([
            'user_id' => $logisticsUser->id,
            'company_name' => 'Stale Generation Fleet Ltd',
            'lga_id' => $this->uyoLga->id,
            'status' => 'active',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Stale Generation Fleet Ltd',
        ]);
        $company->wallet_balance = '25000.00';
        $company->save();

        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $company->id,
            'amount' => '5000.00',
            'status' => 'approved',
            'gateway_status' => 'success',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Stale Generation Fleet Ltd',
            'transfer_reference' => 'FLEET-PAYOUT-STALE-GENERATION-01',
            'paystack_transfer_code' => 'TRF_FLEET_STALE_GENERATION_01',
        ]);
        $event = ProcessedWebhookEvent::create([
            'event_id' => 'evt_fleet_stale_generation_01',
            'event_type' => 'transfer.reversed',
            'payload_hash' => hash('sha256', '{"event":"transfer.reversed"}'),
            'payload' => '{"event":"transfer.reversed"}',
            'status' => 'processing',
            'attempts' => 2,
        ]);
        $journalCountBefore = FinancialJournalEntry::count();
        $walletBefore = (string) $company->fresh()->wallet_balance;

        $result = app(PayoutFinalizationService::class)->revertLogisticsPayout(
            $payout,
            'Stale webhook generation regression',
            null,
            true,
            null,
            (int) $event->id,
            1
        );

        $this->assertFalse($result['success']);
        $this->assertTrue($result['stale_webhook_claim'] ?? false);
        $this->assertSame('approved', $payout->fresh()->status);
        $this->assertSame('success', $payout->fresh()->gateway_status);
        $this->assertSame($walletBefore, (string) $company->fresh()->wallet_balance);
        $this->assertSame('processing', $event->fresh()->status);
        $this->assertSame(2, (int) $event->fresh()->attempts);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
    }

    public function test_stale_transfer_success_generation_cannot_finalize_vendor_payout(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '5000.00',
            'status' => 'approved',
            'gateway_status' => 'pending',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Test Vendor',
            'transfer_reference' => 'VND-PAYOUT-STALE-GENERATION-01',
            'paystack_transfer_code' => 'TRF_STALE_GENERATION_01',
        ]);
        $event = ProcessedWebhookEvent::create([
            'event_id' => 'evt_transfer_stale_generation_01',
            'event_type' => 'transfer.success',
            'payload_hash' => hash('sha256', '{"event":"transfer.success"}'),
            'payload' => '{"event":"transfer.success"}',
            'status' => 'processing',
            'attempts' => 2,
        ]);
        $journalCountBefore = FinancialJournalEntry::count();

        $result = app(PayoutFinalizationService::class)->finalizeVendorPayout(
            $payout,
            [
                'reference' => 'VND-PAYOUT-STALE-GENERATION-01',
                'transfer_code' => 'TRF_STALE_GENERATION_01',
                'status' => 'success',
                'amount' => 500000,
                'currency' => 'NGN',
            ],
            null,
            null,
            (int) $event->id,
            1
        );

        $this->assertFalse($result['success']);
        $this->assertTrue($result['stale_webhook_claim'] ?? false);
        $this->assertSame('approved', $payout->fresh()->status);
        $this->assertSame('pending', $payout->fresh()->gateway_status);
        $this->assertSame('processing', $event->fresh()->status);
        $this->assertSame(2, (int) $event->fresh()->attempts);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
    }

    public function test_transfer_reversed_without_confirmed_disbursement_journal_is_quarantined(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '5000.00',
            'status' => 'approved',
            'gateway_status' => 'success',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Test Vendor',
            'transfer_reference' => 'VND-PAYOUT-REVERSAL-NO-JOURNAL-01',
            'paystack_transfer_code' => 'TRF_REVERSAL_NO_JOURNAL_01',
        ]);

        $payloadData = [
            'id' => 'evt_reversal_no_journal_01',
            'event' => 'transfer.reversed',
            'data' => [
                'reference' => 'VND-PAYOUT-REVERSAL-NO-JOURNAL-01',
                'transfer_code' => 'TRF_REVERSAL_NO_JOURNAL_01',
                'amount' => 500000,
                'currency' => 'NGN',
                'reason' => 'Reversal arrived without confirmed disbursement journal',
            ],
        ];

        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);
        $journalCountBefore = FinancialJournalEntry::count();

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);
        $payout->refresh();
        $this->assertSame('approved', $payout->status);
        $this->assertSame('success', $payout->gateway_status);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
        $this->assertDatabaseHas('processed_webhook_events', [
            'event_id' => 'evt_reversal_no_journal_01',
            'status' => 'unmatched',
        ]);
    }

    public function test_logistics_transfer_reversed_without_confirmed_disbursement_journal_is_quarantined(): void
    {
        $logisticsUser = User::factory()->create([
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $company = LogisticsCompanyProfile::create([
            'user_id' => $logisticsUser->id,
            'company_name' => 'Reversal Evidence Fleet Ltd',
            'lga_id' => $this->uyoLga->id,
            'status' => 'active',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Reversal Evidence Fleet Ltd',
        ]);
        $company->wallet_balance = '25000.00';
        $company->save();

        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $company->id,
            'amount' => '5000.00',
            'status' => 'approved',
            'gateway_status' => 'success',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Reversal Evidence Fleet Ltd',
            'transfer_reference' => 'FLEET-PAYOUT-REVERSAL-NO-JOURNAL-01',
            'paystack_transfer_code' => 'TRF_FLEET_REVERSAL_NO_JOURNAL_01',
        ]);

        $payloadData = [
            'id' => 'evt_fleet_reversal_no_journal_01',
            'event' => 'transfer.reversed',
            'data' => [
                'reference' => 'FLEET-PAYOUT-REVERSAL-NO-JOURNAL-01',
                'transfer_code' => 'TRF_FLEET_REVERSAL_NO_JOURNAL_01',
                'amount' => 500000,
                'currency' => 'NGN',
                'reason' => 'Fleet reversal arrived without confirmed disbursement journal',
            ],
        ];

        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);
        $journalCountBefore = FinancialJournalEntry::count();
        $walletBefore = (string) $company->fresh()->wallet_balance;

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);
        $payout->refresh();
        $this->assertSame('approved', $payout->status);
        $this->assertSame('success', $payout->gateway_status);
        $this->assertSame($walletBefore, (string) $company->fresh()->wallet_balance);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
        $this->assertDatabaseHas('processed_webhook_events', [
            'event_id' => 'evt_fleet_reversal_no_journal_01',
            'status' => 'unmatched',
        ]);
    }

    public function test_transfer_reversed_requires_matching_explicit_amount_and_currency(): void
    {
        $cases = [
            'missing_amount' => [],
            'missing_currency' => ['amount' => 500000],
            'wrong_amount' => ['amount' => 499999, 'currency' => 'NGN'],
            'wrong_currency' => ['amount' => 500000, 'currency' => 'USD'],
        ];

        $journalCountBefore = FinancialJournalEntry::count();

        foreach ($cases as $case => $financialFields) {
            $suffix = strtoupper($case);
            $reference = 'VND-PAYOUT-REVERSAL-EVIDENCE-' . $suffix;
            $transferCode = 'TRF_REVERSAL_EVIDENCE_' . $suffix;
            $eventId = 'evt_reversal_evidence_' . $case;

            $payout = VendorPayoutRequest::create([
                'vendor_id' => $this->vendor->id,
                'amount' => '5000.00',
                'status' => 'approved',
                'gateway_status' => 'success',
                'bank_name' => 'Zenith Bank',
                'account_number' => '1012345678',
                'account_name' => 'Test Vendor',
                'transfer_reference' => $reference,
                'paystack_transfer_code' => $transferCode,
            ]);

            $transferData = [
                'reference' => $reference,
                'transfer_code' => $transferCode,
                'reason' => 'Test reversal evidence validation',
            ];
            $payloadData = [
                'id' => $eventId,
                'event' => 'transfer.reversed',
                'data' => array_merge($transferData, $financialFields),
            ];

            $payloadJson = json_encode($payloadData);
            $secretKey = app(PaystackService::class)->getSecretKey();
            $signature = hash_hmac('sha512', $payloadJson, $secretKey);

            $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
                'x-paystack-signature' => $signature,
            ]);

            $response->assertStatus(400);
            $payout->refresh();
            $this->assertSame('approved', $payout->status, "Payout state changed for {$case}.");
            $this->assertSame('success', $payout->gateway_status, "Gateway state changed for {$case}.");
            $this->assertDatabaseHas('processed_webhook_events', [
                'event_id' => $eventId,
                'status' => 'failed',
            ]);
        }

        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
    }

    public function test_transfer_reversed_rejects_malformed_kobo_amount_without_mutating_payout(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '5000.00',
            'status' => 'approved',
            'gateway_status' => 'success',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Test Vendor',
            'transfer_reference' => 'VND-PAYOUT-REVERSAL-MALFORMED-01',
            'paystack_transfer_code' => 'TRF_REVERSAL_MALFORMED_01',
        ]);

        $payloadData = [
            'id' => 'evt_reversal_malformed_amount_01',
            'event' => 'transfer.reversed',
            'data' => [
                'reference' => 'VND-PAYOUT-REVERSAL-MALFORMED-01',
                'transfer_code' => 'TRF_REVERSAL_MALFORMED_01',
                'amount' => 500000.5,
                'currency' => 'NGN',
                'reason' => 'Test malformed reversal amount',
            ],
        ];

        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);
        $journalCountBefore = FinancialJournalEntry::count();

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(400);
        $payout->refresh();
        $this->assertSame('approved', $payout->status);
        $this->assertSame('success', $payout->gateway_status);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
    }

    public function test_paystack_webhook_transfer_success_confirms_payout(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '15000.00',
            'status' => 'approved',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Test Vendor',
            'settlement_proof_path' => 'paystack_transfer:TRF_CONFIRM_123',
            'admin_notes' => 'Automated Paystack NUBAN Transfer (TRF_CONFIRM_123)',
        ]);

        $payloadData = [
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'VND-PAYOUT-CONFIRM-01',
                'transfer_code' => 'TRF_CONFIRM_123',
                'amount' => 1500000,
                'currency' => 'NGN',
            ],
        ];

        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => true]);

        $payout->refresh();
        $this->assertStringContainsString('Paystack Gateway Delivery Confirmed', (string)$payout->admin_notes);
    }

    /**
     * Webhook Test: transfer.failed restores logistics company wallet balance and reverses ledger.
     */

    public function test_paystack_webhook_transfer_failed_restores_merchant_orders_and_ledger(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-VND-REVERSE-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_type' => 'delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'settled',
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '21000.00',
            'total_amount' => '21000.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'logistics_fee_retained' => '900.00',
            'platform_delivery_fee' => '100.00',
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '18000.00',
            'status' => 'approved',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Victorious Electronics Ltd',
            'settlement_proof_path' => 'paystack_transfer:TRF_VND_FAIL_88',
            'admin_notes' => 'Automated Paystack NUBAN Transfer (TRF_VND_FAIL_88)',
        ]);

        \App\Models\VendorPayoutAllocation::create([
            'vendor_payout_request_id' => $payout->id,
            'order_id' => $order->id,
            'allocated_amount' => '18000.00',
        ]);

        // Simulate the original confirmed disbursement journal; reversal must only
        // restore cash when a matching original payout event exists.
        foreach ([
            ['account_name' => 'vendor_payable', 'entry_type' => 'debit'],
            ['account_name' => 'cash_in_escrow', 'entry_type' => 'credit'],
        ] as $entry) {
            FinancialJournalEntry::create([
                'order_id' => null,
                'transaction_reference' => 'VND-PAYOUT-FAIL-01',
                'event_id' => 'EVT-VND-PAYOUT-' . $payout->id,
                'beneficiary_type' => VendorProfile::class,
                'beneficiary_id' => $this->vendor->id,
                'account_name' => $entry['account_name'],
                'entry_type' => $entry['entry_type'],
                'amount' => '18000.00',
                'currency' => 'NGN',
                'description' => 'Original vendor payout disbursement journal',
            ]);
        }

        $payloadData = [
            'event' => 'transfer.failed',
            'data' => [
                'reference' => 'VND-PAYOUT-FAIL-01',
                'transfer_code' => 'TRF_VND_FAIL_88',
                'reason' => 'Account number does not exist on NIBSS',
                'amount' => 1800000,
                'currency' => 'NGN',
            ],
        ];

        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);
        $payout->refresh();
        $this->assertEquals('rejected', $payout->status);
        $this->assertStringContainsString('Account number does not exist on NIBSS', (string)$payout->rejection_reason);

        // Order is restored back to eligible so merchant withdrawable balance reflects it!
        $order->refresh();
        $this->assertEquals('eligible', $order->vendor_settlement_status);

        // Financial journal reversal entries exist
        $this->assertTrue(FinancialJournalEntry::where('description', 'like', '%Merchant payable liability restored after Paystack transfer failure%')->orWhere('description', 'like', '%Merchant escrow holding restored%')->exists());
        $this->assertTrue(FinancialJournalEntry::where('description', 'like', '%Escrow cash restored after Paystack transfer failure%')->exists());
    }

    /**
     * Test P0: Vendor payout records immutable allocations and guarantees zero over-settlement.
     */
    public function test_vendor_payout_records_immutable_allocations_and_prevents_oversettlement(): void
    {
        // Order A: 18,000 net
        $orderA = Order::create([
            'order_number' => 'ORD-ALLOC-A',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_type' => 'delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'eligible',
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '21000.00',
            'total_amount' => '21000.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
        ]);

        // Order B: 27,000 net
        $orderB = Order::create([
            'order_number' => 'ORD-ALLOC-B',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_type' => 'delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'eligible',
            'merchandise_subtotal' => '30000.00',
            'delivery_fee' => '1000.00',
            'grand_total' => '31000.00',
            'total_amount' => '31000.00',
            'vendor_net_90' => '27000.00',
            'customer_cashback_5' => '1500.00',
            'platform_margin_5' => '1500.00',
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
        ]);

        // Vendor requests payout of exactly 18,000.00
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '18000.00',
            'status' => 'pending',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Victorious Electronics Ltd',
        ]);

        $mockPaystack = Mockery::mock(PaystackService::class);
        $mockPaystack->shouldReceive('createTransferRecipient')
            ->once()
            ->andReturn([
                'success' => true,
                'recipient_code' => 'RCP_MOCK_ALLOC_123',
            ]);
        $mockPaystack->shouldReceive('initiateTransfer')
            ->once()
            ->andReturn([
                'success' => true,
                'transfer_code' => 'TRF_MOCK_ALLOC_456',
                'status' => 'success',
            ]);
        $this->app->instance(PaystackService::class, $mockPaystack);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
            'notes' => 'Disbursing 18k payout test',
        ]);

        $response->assertSessionHas('status');

        $payout->refresh();
        $this->assertEquals('approved', $payout->status);
        $this->assertEquals('TRF_MOCK_ALLOC_456', $payout->paystack_transfer_code);

        // Verify Order A is settled
        $this->assertEquals('settled', $orderA->fresh()->vendor_settlement_status);

        // Invariant: Order B is NOT over-settled; remains eligible
        $this->assertEquals('eligible', $orderB->fresh()->vendor_settlement_status);

        // Verify allocation record exists
        $allocations = \App\Models\VendorPayoutAllocation::where('vendor_payout_request_id', $payout->id)->get();
        $this->assertCount(1, $allocations);
        $this->assertEquals($orderA->id, $allocations->first()->order_id);
        $this->assertEquals('18000.00', (string)$allocations->first()->allocated_amount);
    }

    /**
     * Test P0: Webhook reversal restores ONLY the exact orders allocated to that payout.
     */
    public function test_vendor_payout_reversal_restores_only_allocated_orders_and_not_prior_settled(): void
    {
        // Prior Order X: Settled weeks ago in a previous payout
        $orderX = Order::create([
            'order_number' => 'ORD-PRIOR-X',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_type' => 'delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'settled',
            'merchandise_subtotal' => '50000.00',
            'delivery_fee' => '0.00',
            'grand_total' => '50000.00',
            'total_amount' => '50000.00',
            'vendor_net_90' => '45000.00',
            'customer_cashback_5' => '2500.00',
            'platform_margin_5' => '2500.00',
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
        ]);

        // Order Y: Belongs to the current payout
        $orderY = Order::create([
            'order_number' => 'ORD-CURRENT-Y',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'order_type' => 'delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'settled',
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '0.00',
            'grand_total' => '20000.00',
            'total_amount' => '20000.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '18000.00',
            'status' => 'approved',
            'transfer_reference' => 'VND-PAYOUT-TEST-EXACT-01',
            'paystack_transfer_code' => 'TRF_EXACT_999',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Victorious Electronics Ltd',
        ]);

        // Explicit immutable allocation linking payout to Order Y only
        \App\Models\VendorPayoutAllocation::create([
            'vendor_payout_request_id' => $payout->id,
            'order_id' => $orderY->id,
            'allocated_amount' => '18000.00',
        ]);

        $payloadData = [
            'event' => 'transfer.failed',
            'data' => [
                'reference' => 'VND-PAYOUT-TEST-EXACT-01',
                'transfer_code' => 'TRF_EXACT_999',
                'reason' => 'Bank destination account frozen',
            ],
        ];

        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Order Y is restored back to eligible
        $this->assertEquals('eligible', $orderY->fresh()->vendor_settlement_status);

        // Invariant: Prior Order X is untouched and strictly remains 'settled'
        $this->assertEquals('settled', $orderX->fresh()->vendor_settlement_status);
    }

    /**
     * Test P1: Webhook idempotency ignores duplicate events.
     */
    public function test_webhook_idempotency_blocks_duplicate_event_processing(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '5000.00',
            'status' => 'approved',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Victorious Electronics Ltd',
            'settlement_proof_path' => 'paystack_transfer:TRF_IDEM_001',
            'admin_notes' => 'Automated Paystack NUBAN Transfer (TRF_IDEM_001)',
        ]);

        $payloadData = [
            'event' => 'transfer.success',
            'id' => 'evt_unique_test_12345',
            'data' => [
                'reference' => 'VND-IDEM-001',
                'transfer_code' => 'TRF_IDEM_001',
                'amount' => 500000,
                'currency' => 'NGN',
            ],
        ];

        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);

        // First call
        $first = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);
        $first->assertStatus(200);

        // Second call (replay)
        $second = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);
        $second->assertStatus(200);
        $second->assertJson(['status' => true, 'message' => 'Event already processed.']);
    }

    /**
     * Approved rule: payout requests and allocations must use whole earning records in strict FIFO order.
     */
    public function test_payout_does_not_split_an_earning_record_and_accepts_whole_record_total(): void
    {
        $this->mockPaystackTransferSuccess('TRF_WHOLE_RECORD_01');

        $order = $this->createTestOrder([
            'merchandise_subtotal' => '10000.00',
            'vendor_net_90' => '9000.00',
            'grand_total' => '10000.00',
            'vendor_settlement_status' => 'eligible',
        ]);

        $this->assertEquals('9000.00', $this->vendor->getWithdrawableBalance());

        // The web portal must reject fractional-kobo precision rather than silently rounding it.
        $invalidPrecision = $this->actingAs($this->vendorUser)->post(route('vendor.payout.request'), [
            'amount' => '5000.001',
        ]);
        $invalidPrecision->assertSessionHasErrors('amount');
        $this->assertSame(0, VendorPayoutRequest::where('vendor_id', $this->vendor->id)->count());

        // A request that would split the oldest earning record is rejected before reserving funds.
        $partial = $this->actingAs($this->vendorUser)->post(route('vendor.payout.request'), [
            'amount' => '5000.00',
        ]);
        $partial->assertSessionHas('error');
        $this->assertSame(0, VendorPayoutRequest::where('vendor_id', $this->vendor->id)->count());
        $this->assertEquals('9000.00', $this->vendor->fresh()->getWithdrawableBalance());
        $this->assertEquals('eligible', $order->fresh()->vendor_settlement_status);

        // A whole-record request is accepted.
        $this->actingAs($this->vendorUser)->post(route('vendor.payout.request'), [
            'amount' => '9000.00',
        ]);
        $wholeRequest = VendorPayoutRequest::where('vendor_id', $this->vendor->id)->first();
        $this->assertNotNull($wholeRequest);

        // One active request prevents a second request while the first is unresolved.
        $secondRequest = $this->actingAs($this->vendorUser)->post(route('vendor.payout.request'), [
            'amount' => '5000.00',
        ]);
        $secondRequest->assertSessionHas('error');
        $this->assertSame(1, VendorPayoutRequest::where('vendor_id', $this->vendor->id)->count());

        $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $wholeRequest->id,
            'notes' => 'Whole earning record settlement',
        ]);

        $order->refresh();
        $this->assertEquals('settled', $order->vendor_settlement_status);
        $this->assertEquals('0.00', $order->remaining_payable_to_vendor);
        $this->assertEquals('0.00', $this->vendor->fresh()->getWithdrawableBalance());
        $this->assertSame(1, $wholeRequest->allocations()->count());
        $this->assertEquals('9000.00', $wholeRequest->allocations()->first()->allocated_amount);
    }

    /**
     * Audit Test D: Reversal of payout without allocation records halts and never mutates arbitrary orders.
     */
    public function test_webhook_reversal_without_allocations_halts_and_does_not_mutate_unrelated_orders(): void
    {
        $unrelatedSettledOrder = $this->createTestOrder([
            'merchandise_subtotal' => '10000.00',
            'vendor_net_90' => '9000.00',
            'grand_total' => '10000.00',
            'vendor_settlement_status' => 'settled',
        ]);

        $unallocatedPayout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '9000.00',
            'status' => 'approved',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Victorious Electronics Ltd',
            'settlement_proof_path' => 'paystack_transfer:TRF_UNALLOC_999',
            'admin_notes' => 'Automated Paystack NUBAN Transfer (TRF_UNALLOC_999)',
        ]);

        $payloadData = [
            'event' => 'transfer.failed',
            'data' => [
                'reference' => 'UNALLOC-PAYOUT-FAIL-01',
                'transfer_code' => 'TRF_UNALLOC_999',
                'reason' => 'Bank technical error',
            ],
        ];

        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Audit Finding D Invariant: The unrelated settled order must NOT have been guessed and un-settled!
        $unrelatedSettledOrder->refresh();
        $this->assertEquals('settled', $unrelatedSettledOrder->vendor_settlement_status);

        // Payout flagged for manual review
        $unallocatedPayout->refresh();
        $this->assertEquals('rejected', $unallocatedPayout->status);
        $this->assertStringContainsString('Requires treasury audit', (string)$unallocatedPayout->admin_notes);
    }
}