<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialOperation;
use App\Models\FinancialReservation;
use App\Models\GatewayAttempt;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use App\Models\ProcessedWebhookEvent;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorPayoutAllocation;
use App\Models\VendorPayoutRequest;
use App\Models\VendorProfile;
use App\Services\FinancialOperationService;
use App\Services\FinancialReconciliationEngine;
use App\Services\PayoutFinalizationService;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FinancialAuditRemediationProofTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected Shop $shop;
    protected Lga $uyoLga;
    protected User $customer;
    protected LogisticsCompanyProfile $company;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::firstOrCreate(['iso_code' => 'NGA'], ['name' => 'Nigeria', 'phone_code' => '234']);
        $state = State::firstOrCreate(['name' => 'Akwa Ibom'], ['country_id' => $country->id]);
        $this->uyoLga = Lga::firstOrCreate(['name' => 'Uyo'], ['state_id' => $state->id]);

        $this->superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        $this->vendorUser = User::factory()->create([
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Audit Proof Merchant',
            'status' => 'approved',
            'marketplace_status' => 'active',
            'bank_name' => 'Zenith Bank',
            'bank_code' => '057',
            'account_number' => '1012345678',
            'account_name' => 'Audit Proof Merchant',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Proof Shop',
            'slug' => 'proof-shop-' . uniqid(),
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'is_active' => true,
            'address' => '50 Aka Road, Uyo',
        ]);

        $this->customer = User::factory()->create([
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $companyUser = User::factory()->create([
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $this->company = LogisticsCompanyProfile::create([
            'user_id' => $companyUser->id,
            'company_name' => 'Swift Courier Ltd',
            'contact_email' => 'swift@example.com',
            'contact_phone' => '08012345678',
            'lga_id' => $this->uyoLga->id,
            'status' => 'active',
            'bank_name' => 'GTBank',
            'bank_code' => '058',
            'account_number' => '0123456789',
            'account_name' => 'Swift Courier Ltd',
        ]);
        $this->company->wallet_balance = '50000.00';
        $this->company->save();
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
            'order_number' => 'ORD-AUD-' . uniqid(),
            'payment_reference' => 'REF-AUD-' . uniqid(),
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

    /**
     * Audit Finding 1 (P0): Payout submission does NOT finalize when gateway returns pending/received.
     */
    public function test_payout_remains_pending_when_gateway_transfer_is_non_terminal_pending(): void
    {
        $this->createTestOrder([
            'merchandise_subtotal' => '20000.00',
            'vendor_net_90' => '18000.00',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '18000.00',
            'status' => 'pending',
            'bank_name' => $this->vendor->bank_name,
            'bank_code' => $this->vendor->bank_code,
            'account_number' => $this->vendor->account_number,
            'account_name' => $this->vendor->account_name,
        ]);

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('createTransferRecipient')->andReturn([
            'success' => true,
            'recipient_code' => 'RCP_MOCK_AUDIT',
        ]);
        // Paystack returns 'received' / 'pending' — non-terminal queued transfer
        $mock->shouldReceive('initiateTransfer')->andReturn([
            'success' => true,
            'status' => 'pending',
            'transfer_code' => 'TRF_QUEUED_123',
            'message' => 'Transfer has been queued',
        ]);
        $this->app->instance(PaystackService::class, $mock);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);

        $response->assertSessionHas('status');

        $payout->refresh();
        // Payout must remain pending in status with gateway_status 'processing'
        $this->assertEquals('pending', $payout->status);
        $this->assertEquals('processing', $payout->gateway_status);
        $this->assertNull($payout->disbursed_at);
        $this->assertEquals('TRF_QUEUED_123', $payout->paystack_transfer_code);

        // Order allocation is authoritatively reserved to protect treasury against double-withdrawal
        $this->assertEquals(1, VendorPayoutAllocation::where('vendor_payout_request_id', $payout->id)->where('status', 'allocated')->count());

        // Zero financial journal entries created prematurely (until gateway confirmation)
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-VND-PAYOUT-' . $payout->id)->exists());
    }

    /**
     * Audit Finding 2 (P0): Refund reconciliation authoritatively queries Paystack gateway and never fabricates references.
     */
    public function test_refund_reconciliation_verifies_against_gateway_and_does_not_manufacture_synthetic_ids(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '15000.00',
            'paystack_refund_reference' => null,
            'paystack_refund_status' => 'pending', // Explicit refund instruction
        ]);

        $mock = Mockery::mock(PaystackService::class);
        // Gateway accepts refund with authentic reference and status pending
        $mock->shouldReceive('initiateRefund')->once()->andReturn([
            'success' => true,
            'refund_reference' => 'rf_official_gateway_999',
            'status' => 'pending',
            'message' => 'Refund initiated with bank',
        ]);
        $this->app->instance(PaystackService::class, $mock);

        $engine = app(FinancialReconciliationEngine::class);
        $reconciled = $engine->reconcileRefunds();

        // Initiation did not falsely mark completed because gateway status is pending
        $this->assertEquals(0, $reconciled);
        $order->refresh();
        $this->assertEquals('rf_official_gateway_999', $order->paystack_refund_reference);
        $this->assertEquals('pending', $order->paystack_refund_status);

        // Second pass: Now mock gateway refund verification confirming 'processed'
        $mock2 = Mockery::mock(PaystackService::class);
        $mock2->shouldReceive('verifyRefund')->with('rf_official_gateway_999')->once()->andReturn([
            'success' => true,
            'refund_reference' => 'rf_official_gateway_999',
            'status' => 'processed',
            'message' => 'Refund verified processed by bank',
        ]);
        $this->app->instance(PaystackService::class, $mock2);

        $engine2 = app(FinancialReconciliationEngine::class);
        $reconciled2 = $engine2->reconcileRefunds();

        $this->assertEquals(1, $reconciled2);
        $order->refresh();
        $this->assertEquals('completed', $order->paystack_refund_status);
    }

    /**
     * Audit Finding 3 (P0): Reversal processing is strictly idempotent and prevents double-crediting wallets.
     */
    public function test_reversal_processing_is_strictly_idempotent_and_does_not_double_credit_logistics(): void
    {
        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '20000.00',
            'status' => 'approved',
            'gateway_status' => 'processing',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);

        $initialBalance = $this->company->fresh()->wallet_balance; // 50000.00

        $finalizationService = app(PayoutFinalizationService::class);

        // First reversal call (e.g. from Webhook)
        $firstRes = $finalizationService->revertLogisticsPayout($payout, 'Bank rejected account number');
        $this->assertTrue($firstRes['success']);
        $this->assertFalse($firstRes['idempotent'] ?? false);

        // Balance restored once: 50,000 + 20,000 = 70,000
        $this->assertEquals('70000.00', number_format((float)$this->company->fresh()->wallet_balance, 2, '.', ''));
        $this->assertEquals(2, FinancialJournalEntry::where('event_id', 'EVT-REV-FLEET-REQUEST-' . $payout->id)->count()); // reverse request liability and restore wallet
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-REV-FLEET-' . $payout->id)->exists(), 'No disbursement journal exists to reverse.');

        // Second reversal call (e.g. from scheduled reconciler race)
        $secondRes = $finalizationService->revertLogisticsPayout($payout, 'Reconciler found failure');
        $this->assertTrue($secondRes['success']);
        $this->assertTrue($secondRes['idempotent'] ?? false);

        // Balance must STILL be 70,000 (never double credited!)
        $this->assertEquals('70000.00', number_format((float)$this->company->fresh()->wallet_balance, 2, '.', ''));
        // Ledger entry count must STILL be 2 (never duplicated!)
        $this->assertEquals(2, FinancialJournalEntry::where('event_id', 'EVT-REV-FLEET-REQUEST-' . $payout->id)->count());
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-REV-FLEET-' . $payout->id)->exists());
    }

    /**
     * An unfinalized logistics transfer failure reverses the request journal,
     * restoring wallet funds without fabricating a cash disbursement reversal.
     */
    public function test_unfinalized_logistics_failure_reverses_request_journal_not_cash_disbursement(): void
    {
        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '12000.00',
            'status' => 'pending',
            'gateway_status' => 'processing',
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);

        $this->company->debitWallet('12000.00');
        FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'FLEET-REQ-TEST-' . $payout->id,
            'event_id' => 'EVT-FLEET-REQ-' . $payout->id,
            'beneficiary_type' => LogisticsCompanyProfile::class,
            'beneficiary_id' => $this->company->id,
            'account_name' => 'logistics_company_wallet',
            'entry_type' => 'debit',
            'amount' => '12000.00',
            'currency' => 'NGN',
            'description' => 'Test withdrawal request wallet debit',
        ]);
        FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'FLEET-REQ-TEST-' . $payout->id,
            'event_id' => 'EVT-FLEET-REQ-' . $payout->id,
            'beneficiary_type' => LogisticsCompanyProfile::class,
            'beneficiary_id' => $this->company->id,
            'account_name' => 'logistics_payout_payable',
            'entry_type' => 'credit',
            'amount' => '12000.00',
            'currency' => 'NGN',
            'description' => 'Test withdrawal request liability credit',
        ]);

        $result = app(PayoutFinalizationService::class)->revertLogisticsPayout(
            $payout,
            'Transfer failed before finalization'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('50000.00', number_format((float)$this->company->fresh()->wallet_balance, 2, '.', ''));
        $entries = FinancialJournalEntry::where('event_id', 'EVT-REV-FLEET-REQUEST-' . $payout->id)->get();
        $this->assertCount(2, $entries);
        $this->assertTrue($entries->contains(fn ($entry) => $entry->account_name === 'logistics_payout_payable' && $entry->entry_type === 'debit'));
        $this->assertTrue($entries->contains(fn ($entry) => $entry->account_name === 'logistics_company_wallet' && $entry->entry_type === 'credit'));
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-REV-FLEET-' . $payout->id)->exists());
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-FLEET-PAYOUT-' . $payout->id)->exists());
    }

    /**
     * A reversed, previously finalized logistics transfer reverses both the
     * disbursement journal and the original wallet withdrawal-request journal.
     */
    public function test_finalized_logistics_reversal_restores_wallet_and_balances_both_reversal_events(): void
    {
        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '12000.00',
            'status' => 'pending',
            'gateway_status' => 'processing',
            'transfer_reference' => 'FLEET-REQ-REVERSAL-' . uniqid(),
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);

        $this->company->debitWallet('12000.00');
        FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'FLEET-REQ-TEST-' . $payout->id,
            'event_id' => 'EVT-FLEET-REQ-' . $payout->id,
            'beneficiary_type' => LogisticsCompanyProfile::class,
            'beneficiary_id' => $this->company->id,
            'account_name' => 'logistics_company_wallet',
            'entry_type' => 'debit',
            'amount' => '12000.00',
            'currency' => 'NGN',
            'description' => 'Test withdrawal request wallet debit',
        ]);
        FinancialJournalEntry::create([
            'order_id' => null,
            'transaction_reference' => 'FLEET-REQ-TEST-' . $payout->id,
            'event_id' => 'EVT-FLEET-REQ-' . $payout->id,
            'beneficiary_type' => LogisticsCompanyProfile::class,
            'beneficiary_id' => $this->company->id,
            'account_name' => 'logistics_payout_payable',
            'entry_type' => 'credit',
            'amount' => '12000.00',
            'currency' => 'NGN',
            'description' => 'Test withdrawal request liability credit',
        ]);

        $finalizer = app(PayoutFinalizationService::class);
        $finalized = $finalizer->finalizeLogisticsPayout($payout, [
            'status' => 'success',
            'reference' => $payout->transfer_reference,
            'transfer_code' => 'TRF_FLEET_REVERSAL_' . $payout->id,
        ]);
        $this->assertTrue($finalized['success']);
        $this->assertEquals(2, FinancialJournalEntry::where('event_id', 'EVT-FLEET-PAYOUT-' . $payout->id)->count());

        $reverted = $finalizer->revertLogisticsPayout($payout, 'Gateway confirmed transfer reversal', null, true);
        $this->assertTrue($reverted['success']);
        $this->assertEquals('50000.00', number_format((float)$this->company->fresh()->wallet_balance, 2, '.', ''));

        foreach ([
            'EVT-REV-FLEET-' . $payout->id,
            'EVT-REV-FLEET-REQUEST-' . $payout->id,
        ] as $eventId) {
            $entries = FinancialJournalEntry::where('event_id', $eventId)->get();
            $this->assertCount(2, $entries, $eventId . ' should contain a balanced debit/credit pair');
            $debits = (float) $entries->where('entry_type', 'debit')->sum('amount');
            $credits = (float) $entries->where('entry_type', 'credit')->sum('amount');
            $this->assertEqualsWithDelta(12000.00, $debits, 0.001, $eventId . ' debits');
            $this->assertEqualsWithDelta(12000.00, $credits, 0.001, $eventId . ' credits');
        }

        $requestReversal = FinancialJournalEntry::where('event_id', 'EVT-REV-FLEET-REQUEST-' . $payout->id)->get();
        $this->assertTrue($requestReversal->contains(fn ($entry) => $entry->account_name === 'logistics_payout_payable' && $entry->entry_type === 'debit'));
        $this->assertTrue($requestReversal->contains(fn ($entry) => $entry->account_name === 'logistics_company_wallet' && $entry->entry_type === 'credit'));
    }

    /**
     * The service-level row-lock guard closes the race where a stale failure
     * result reaches reversal after another worker finalized the payout.
     */
    public function test_stale_logistics_failure_cannot_reverse_payout_after_success_was_finalized(): void
    {
        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '12000.00',
            'status' => 'approved',
            'gateway_status' => 'success',
            'disbursed_at' => Carbon::now(),
            'transfer_reference' => 'FLEET-STALE-FAIL-' . uniqid(),
            'bank_name' => $this->company->bank_name,
            'bank_code' => $this->company->bank_code,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);

        $initialBalance = number_format((float) $this->company->fresh()->wallet_balance, 2, '.', '');
        $result = app(PayoutFinalizationService::class)->revertLogisticsPayout(
            $payout,
            'Stale reconciler observed failure before success finalization'
        );

        $this->assertFalse($result['success']);
        $this->assertTrue($result['protected']);
        $this->assertTrue($result['idempotent']);
        $this->assertEquals('approved', $payout->fresh()->status);
        $this->assertEquals('success', $payout->fresh()->gateway_status);
        $this->assertEquals($initialBalance, number_format((float) $this->company->fresh()->wallet_balance, 2, '.', ''));
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-REV-FLEET-' . $payout->id)->exists());
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-REV-FLEET-REQUEST-' . $payout->id)->exists());
    }

    /**
     * The service-level row-lock guard also protects vendor payouts from a
     * stale failed-transfer reconciliation racing with successful finalization.
     */
    public function test_stale_vendor_failure_cannot_reverse_payout_after_success_was_finalized(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '12000.00',
            'status' => 'approved',
            'gateway_status' => 'success',
            'disbursed_at' => Carbon::now(),
            'transfer_reference' => 'VND-STALE-FAIL-' . uniqid(),
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'Zenith Bank',
        ]);

        $result = app(PayoutFinalizationService::class)->revertVendorPayout(
            $payout,
            'Stale reconciler observed failure before success finalization'
        );

        $this->assertFalse($result['success']);
        $this->assertTrue($result['protected']);
        $this->assertTrue($result['idempotent']);
        $this->assertEquals('approved', $payout->fresh()->status);
        $this->assertEquals('success', $payout->fresh()->gateway_status);
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-REV-VND-' . $payout->id)->exists());
    }

    /**
     * Audit Finding 4 (P0): Webhook transfer.failed delegates cleanly to centralized finalization service.
     */
    public function test_webhook_transfer_failed_delegates_to_central_service_and_reverses_safely(): void
    {
        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '15000.00',
            'status' => 'approved',
            'gateway_status' => 'processing',
            'transfer_reference' => 'FLEET-AUD-FAIL-01',
            'paystack_transfer_code' => 'TRF_FLEET_FAIL_01',
            'bank_name' => $this->company->bank_name,
            'account_number' => $this->company->account_number,
            'account_name' => $this->company->account_name,
        ]);

        $payloadData = [
            'event' => 'transfer.failed',
            'id' => 'evt_fleet_fail_' . uniqid(),
            'data' => [
                'reference' => 'FLEET-AUD-FAIL-01',
                'transfer_code' => 'TRF_FLEET_FAIL_01',
                'reason' => 'Invalid NUBAN recipient',
            ],
        ];

        $signature = hash_hmac('sha512', json_encode($payloadData), app(PaystackService::class)->getSecretKey());

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        $payout->refresh();
        $this->assertEquals('rejected', $payout->status);
        $this->assertEquals('failed', $payout->gateway_status);
        $this->assertStringContainsString('Invalid NUBAN recipient', (string)$payout->rejection_reason);

        // Wallet balance refunded cleanly
        $this->assertEquals('65000.00', number_format((float)$this->company->fresh()->wallet_balance, 2, '.', ''));
    }

    /**
     * Audit Finding 5 (P0): Allocation Conservation prevents oversettlement and accounts for discrepancies.
     */
    public function test_allocation_conservation_blocks_payout_when_eligible_orders_are_insufficient(): void
    {
        // Merchant has only ₦9,000 eligible delivered orders
        $this->createTestOrder([
            'merchandise_subtotal' => '10000.00',
            'vendor_net_90' => '9000.00',
        ]);

        // But vendor requested ₦18,000
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '18000.00',
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
        $this->assertStringContainsString('cannot cover requested payout amount', session('error'));
        $this->assertEquals('pending', $payout->fresh()->status);
    }

    /**
     * Audit Finding 6 (P1): Unmatched webhook events are flagged as unmatched and never silently completed.
     */
    public function test_webhook_unmatched_event_is_flagged_with_unmatched_status(): void
    {
        $payloadData = [
            'event' => 'transfer.success',
            'id' => 'evt_unmatched_' . uniqid(),
            'data' => [
                'reference' => 'NON_EXISTENT_REF_9999',
                'transfer_code' => 'TRF_NON_EXISTENT_9999',
                'amount' => 500000,
            ],
        ];

        $signature = hash_hmac('sha512', json_encode($payloadData), app(PaystackService::class)->getSecretKey());

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        $event = ProcessedWebhookEvent::where('event_id', $payloadData['id'])->first();
        $this->assertNotNull($event);
        $this->assertEquals('unmatched', $event->status);
        $this->assertStringContainsString('No matching vendor or logistics payout', (string)$event->error_reason);
    }

    /**
     * Audit Finding P0-1: A processing payout cannot be submitted again.
     */
    public function test_processing_payout_cannot_be_re_submitted(): void
    {
        $this->createTestOrder([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'eligible',
            'vendor_net_90' => '25000.00',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '20000.00',
            'status' => 'pending',
            'gateway_status' => 'processing',
            'paystack_transfer_code' => 'TRF_IN_FLIGHT_999',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'Access Bank',
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Disbursement is already in-flight with Paystack gateway', session('error'));
        $this->assertEquals('pending', $payout->fresh()->status);
        $this->assertEquals('TRF_IN_FLIGHT_999', $payout->fresh()->paystack_transfer_code);
    }

    /**
     * Audit Finding P0-1 (Race Condition): Monotonic guard prevents older pending response from overwriting approved payout.
     */
    public function test_monotonic_guard_prevents_late_pending_response_from_overwriting_approved_payout(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'eligible',
            'vendor_net_90' => '15000.00',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '10000.00',
            'status' => 'pending',
            'gateway_status' => 'processing',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'GTBank',
        ]);

        // Background webhook already finalized payout to approved
        app(PayoutFinalizationService::class)->finalizeVendorPayout($payout, [
            'transfer_code' => 'TRF_FAST_WEBHOOK',
            'reference' => 'VND-PAYOUT-' . $payout->id,
            'status' => 'success',
        ], $this->superAdmin);

        $this->assertEquals('approved', $payout->fresh()->status);

        // Late response attempting to write pending/processing state
        \Illuminate\Support\Facades\DB::transaction(function () use ($payout) {
            $locked = VendorPayoutRequest::where('id', $payout->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['approved', 'completed', 'rejected'], true)) {
                return; // Monotonic guard protects state
            }
            $locked->status = 'pending';
            $locked->gateway_status = 'processing';
            $locked->save();
        });

        // Payout must remain approved and not be downgraded
        $this->assertEquals('approved', $payout->fresh()->status);
        $this->assertEquals('success', $payout->fresh()->gateway_status);
    }

    /**
     * Audit Finding P0-4: Vendor reversal restores exact vendor_payable liability, perfectly mirroring disbursement debit.
     */
    public function test_reversal_restores_exact_vendor_payable_liability_mirroring_disbursement(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'eligible',
            'vendor_net_90' => '50000.00',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '50000.00',
            'status' => 'pending',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'First Bank',
        ]);

        $finalizer = app(PayoutFinalizationService::class);
        $finalizer->finalizeVendorPayout($payout, [
            'transfer_code' => 'TRF_TO_REVERSE_123',
            'reference' => 'VND-DISBURSE-' . $payout->id,
            'status' => 'success',
        ], $this->superAdmin);

        // Verify original disbursement debited vendor_payable
        $debitEntry = FinancialJournalEntry::where('event_id', 'EVT-VND-PAYOUT-' . $payout->id)
            ->where('account_name', 'vendor_payable')
            ->first();
        $this->assertNotNull($debitEntry);
        $this->assertEquals('debit', $debitEntry->entry_type);
        $this->assertEquals(0, bccomp('50000.00', (string)$debitEntry->amount, 2));

        // Now reverse the payout
        $reversalResult = $finalizer->revertVendorPayout($payout, 'Gateway confirmed transfer reversal', $this->superAdmin, true);
        $this->assertTrue($reversalResult['success']);

        // Verify reversal credited vendor_payable (NOT vendor_escrow_holding)
        $creditEntry = FinancialJournalEntry::where('event_id', 'EVT-REV-VND-' . $payout->id)
            ->where('account_name', 'vendor_payable')
            ->first();
        $this->assertNotNull($creditEntry, 'Reversal must credit vendor_payable to restore exact merchant liability');
        $this->assertEquals('credit', $creditEntry->entry_type);
        $this->assertEquals(0, bccomp('50000.00', (string)$creditEntry->amount, 2));

        // Zero entries should exist for vendor_escrow_holding on this event
        $this->assertFalse(
            FinancialJournalEntry::where('event_id', 'EVT-REV-VND-' . $payout->id)
                ->where('account_name', 'vendor_escrow_holding')
                ->exists()
        );
    }

    /**
     * A failed queued vendor transfer must release reserved allocations without
     * inventing a cash/payable reversal when no successful payout journal exists.
     */
    public function test_failed_pending_vendor_payout_releases_allocations_without_fabricating_reversal_journal(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'eligible',
            'vendor_net_90' => '50000.00',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '50000.00',
            'status' => 'pending',
            'gateway_status' => 'processing',
            'paystack_transfer_code' => 'TRF_PENDING_FAIL_NO_JOURNAL',
            'transfer_reference' => 'VND-PENDING-FAIL-NO-JOURNAL',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'First Bank',
        ]);

        $allocation = VendorPayoutAllocation::create([
            'vendor_payout_request_id' => $payout->id,
            'order_id' => $order->id,
            'allocated_amount' => '50000.00',
            'status' => 'allocated',
        ]);

        $result = app(PayoutFinalizationService::class)->revertVendorPayout(
            $payout,
            'Paystack transfer.failed before payout finalization',
            $this->superAdmin
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('rejected', $payout->fresh()->status);
        $this->assertEquals('reversed', $allocation->fresh()->status);
        $this->assertEquals('eligible', $order->fresh()->vendor_settlement_status);
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-VND-PAYOUT-' . $payout->id)->exists());
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-REV-VND-' . $payout->id)->exists());
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-REV-VND-SUSPENSE-' . $payout->id)->exists());
    }

    /**
     * Ledger invariant: reversing a payout with an allocation discrepancy balances
     * the reversal event by crediting the same suspense account used at disbursement.
     */
    public function test_vendor_payout_reversal_journal_event_balances_when_suspense_is_reversed(): void
    {
        $this->createTestOrder([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'eligible',
            'vendor_net_90' => '50000.00',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '60000.00',
            'status' => 'pending',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'First Bank',
        ]);

        $finalizer = app(PayoutFinalizationService::class);
        $finalizer->finalizeVendorPayout($payout, [
            'transfer_code' => 'TRF_SUSPENSE_REVERSAL_TEST',
            'reference' => 'VND-DISBURSE-SUSPENSE-REV-' . $payout->id,
            'status' => 'success',
        ], $this->superAdmin);

        $result = $finalizer->revertVendorPayout($payout, 'Gateway transfer reversed', $this->superAdmin, true);
        $this->assertTrue($result['success']);

        $eventId = 'EVT-REV-VND-' . $payout->id;
        $entries = FinancialJournalEntry::where('event_id', $eventId)->get();
        $this->assertCount(3, $entries, 'All reversal legs, including suspense, must share one event.');
        $this->assertTrue($entries->contains(fn ($entry) => $entry->account_name === 'treasury_reconciliation_suspense' && $entry->entry_type === 'credit' && bccomp((string)$entry->amount, '10000.00', 2) === 0));

        $debits = '0.00';
        $credits = '0.00';
        foreach ($entries as $entry) {
            if ($entry->entry_type === 'debit') {
                $debits = bcadd($debits, (string)$entry->amount, 2);
            } elseif ($entry->entry_type === 'credit') {
                $credits = bcadd($credits, (string)$entry->amount, 2);
            }
        }

        $this->assertSame(0, bccomp($debits, $credits, 2), 'Reversal event must have equal total debits and credits.');
        $this->assertSame('60000.00', $credits);
        $this->assertSame('60000.00', $debits);
    }

    /**
     * Ledger invariant: a payout with an allocation discrepancy still balances
     * within the payout journal event by debiting the discrepancy to treasury suspense.
     */
    public function test_vendor_payout_journal_event_balances_when_discrepancy_is_booked_to_suspense(): void
    {
        $this->createTestOrder([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'eligible',
            'vendor_net_90' => '50000.00',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '60000.00',
            'status' => 'pending',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'First Bank',
        ]);

        $result = app(PayoutFinalizationService::class)->finalizeVendorPayout($payout, [
            'transfer_code' => 'TRF_SUSPENSE_BALANCE_TEST',
            'reference' => 'VND-DISBURSE-SUSPENSE-' . $payout->id,
            'status' => 'success',
        ], $this->superAdmin);

        $this->assertTrue($result['success']);
        $eventId = 'EVT-VND-PAYOUT-' . $payout->id;
        $entries = FinancialJournalEntry::where('event_id', $eventId)->get();
        $this->assertCount(3, $entries);
        $this->assertTrue($entries->contains(fn ($entry) => $entry->account_name === 'treasury_reconciliation_suspense' && $entry->entry_type === 'debit' && bccomp((string)$entry->amount, '10000.00', 2) === 0));

        $debits = '0.00';
        $credits = '0.00';
        foreach ($entries as $entry) {
            if ($entry->entry_type === 'debit') {
                $debits = bcadd($debits, (string)$entry->amount, 2);
            } elseif ($entry->entry_type === 'credit') {
                $credits = bcadd($credits, (string)$entry->amount, 2);
            }
        }

        $this->assertSame(0, bccomp($debits, $credits, 2), 'Payout event must have equal total debits and credits.');
        $this->assertSame('60000.00', $credits);
        $this->assertSame('60000.00', $debits);
    }

    /**
     * Audit Finding P0-3: Webhook rejects amount and currency tampering.
     */
    public function test_webhook_rejects_amount_and_currency_tampering(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '40000.00', // 40,000 NGN = 4,000,000 Kobo
            'status' => 'pending',
            'gateway_status' => 'processing',
            'transfer_reference' => 'VND-PAYOUT-TAMPER-TEST',
            'paystack_transfer_code' => 'TRF_TAMPER_TEST',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'Zenith Bank',
        ]);

        // Attempt webhook with underpaid amount (e.g. 4,000 NGN = 400,000 Kobo)
        $payloadData = [
            'event' => 'transfer.success',
            'id' => 'evt_tamper_' . uniqid(),
            'data' => [
                'reference' => 'VND-PAYOUT-TAMPER-TEST',
                'transfer_code' => 'TRF_TAMPER_TEST',
                'amount' => 400000, // Underpayment: only 10% of payout
                'currency' => 'NGN',
            ],
        ];

        $signature = hash_hmac('sha512', json_encode($payloadData), app(PaystackService::class)->getSecretKey());

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(400);
        $this->assertEquals('pending', $payout->fresh()->status);

        $event = ProcessedWebhookEvent::where('event_id', $payloadData['id'])->first();
        $this->assertNotNull($event);
        $this->assertEquals('failed', $event->status);
        $this->assertStringContainsString('AMOUNT_MISMATCH', (string)$event->error_reason);
    }

    public function test_successful_transfer_webhook_requires_amount_and_currency(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '40000.00',
            'status' => 'pending',
            'gateway_status' => 'processing',
            'transfer_reference' => 'VND-PAYOUT-MISSING-FIELDS',
            'paystack_transfer_code' => 'TRF_MISSING_FIELDS',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'Zenith Bank',
        ]);

        $cases = [
            [
                'id' => 'evt-missing-transfer-amount',
                'data' => [
                    'reference' => 'VND-PAYOUT-MISSING-FIELDS',
                    'transfer_code' => 'TRF_MISSING_FIELDS',
                    'currency' => 'NGN',
                ],
                'reason' => 'MISSING_AMOUNT',
            ],
            [
                'id' => 'evt-missing-transfer-currency',
                'data' => [
                    'reference' => 'VND-PAYOUT-MISSING-FIELDS',
                    'transfer_code' => 'TRF_MISSING_FIELDS',
                    'amount' => 4000000,
                ],
                'reason' => 'MISSING_CURRENCY',
            ],
        ];

        foreach ($cases as $case) {
            $payloadData = [
                'event' => 'transfer.success',
                'id' => $case['id'],
                'data' => $case['data'],
            ];
            $signature = hash_hmac(
                'sha512',
                json_encode($payloadData),
                app(PaystackService::class)->getSecretKey()
            );

            $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
                'x-paystack-signature' => $signature,
            ]);

            $response->assertStatus(400);
            $event = ProcessedWebhookEvent::where('event_id', $case['id'])->first();
            $this->assertNotNull($event);
            $this->assertEquals('failed', $event->status);
            $this->assertStringContainsString($case['reason'], (string) $event->error_reason);
            $this->assertEquals('pending', $payout->fresh()->status);
        }
    }

    /**
     * A delayed transfer.success event must not reopen a payout already reversed as failed.
     */
    public function test_delayed_transfer_success_does_not_reopen_terminal_failed_vendor_payout(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '40000.00',
            'status' => 'rejected',
            'gateway_status' => 'failed',
            'transfer_reference' => 'VND-TERMINAL-FAIL-SUCCESS',
            'paystack_transfer_code' => 'TRF_TERMINAL_FAIL_SUCCESS',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'Zenith Bank',
        ]);

        $payloadData = [
            'event' => 'transfer.success',
            'id' => 'evt-success-after-terminal-failure',
            'data' => [
                'reference' => 'VND-TERMINAL-FAIL-SUCCESS',
                'transfer_code' => 'TRF_TERMINAL_FAIL_SUCCESS',
                'amount' => 4000000,
                'currency' => 'NGN',
            ],
        ];
        $signature = hash_hmac(
            'sha512',
            json_encode($payloadData),
            app(PaystackService::class)->getSecretKey()
        );

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertOk();
        $this->assertEquals('rejected', $payout->fresh()->status);
        $this->assertEquals('failed', $payout->fresh()->gateway_status);
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-VND-PAYOUT-' . $payout->id)->exists());

        $event = ProcessedWebhookEvent::where('event_id', $payloadData['id'])->first();
        $this->assertNotNull($event);
        $this->assertEquals('unmatched', $event->status);
        $this->assertStringContainsString('TERMINAL_PAYOUT_PROTECTED', (string) $event->error_reason);
    }

    public function test_late_transfer_failed_webhook_does_not_reverse_successful_payout(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '40000.00',
            'status' => 'approved',
            'gateway_status' => 'success',
            'transfer_reference' => 'VND-PAYOUT-LATE-FAIL',
            'paystack_transfer_code' => 'TRF_LATE_FAIL',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'Zenith Bank',
        ]);

        $payloadData = [
            'event' => 'transfer.failed',
            'id' => 'evt-late-transfer-failed-after-success',
            'data' => [
                'reference' => 'VND-PAYOUT-LATE-FAIL',
                'transfer_code' => 'TRF_LATE_FAIL',
                'amount' => 4000000,
                'currency' => 'NGN',
                'reason' => 'Delayed failure notification',
            ],
        ];
        $signature = hash_hmac(
            'sha512',
            json_encode($payloadData),
            app(PaystackService::class)->getSecretKey()
        );

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertOk();
        $this->assertEquals('approved', $payout->fresh()->status);
        $this->assertEquals('success', $payout->fresh()->gateway_status);

        $event = ProcessedWebhookEvent::where('event_id', $payloadData['id'])->first();
        $this->assertNotNull($event);
        $this->assertEquals('unmatched', $event->status);
        $this->assertStringContainsString('LATE_TRANSFER_FAILED_AFTER_SUCCESS', (string) $event->error_reason);
    }

    public function test_initiated_refund_is_not_dispatched_twice_after_uncertain_attempt(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-REFUND-RETRY-GUARD',
            'paystack_refund_status' => 'pending',
        ]);

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldNotReceive('initiateRefund');
        $this->app->instance(PaystackService::class, $mock);

        $service = app(FinancialOperationService::class);
        $operation = $service->createRefundOperation(
            $order,
            '500.00',
            'retry protection proof',
            null,
            'IDEMPOTENCY-REFUND-RETRY-GUARD'
        );

        $operation->update(['status' => 'initiated']);
        GatewayAttempt::create([
            'financial_operation_id' => $operation->id,
            'gateway_provider' => 'paystack',
            'endpoint' => '/refund',
            'attempt_reference' => 'ATT-RFD-RETRY-GUARD',
            'request_payload' => [
                'transaction' => $order->payment_reference,
                'amount_kobo' => 50000,
                'amount_naira' => '500.00',
            ],
            'outcome' => 'uncertain_timeout',
            'attempted_at' => Carbon::now(),
        ]);

        $result = $service->dispatchRefund($operation);

        $this->assertFalse($result['success']);
        $this->assertEquals('initiated', $result['status']);
        $this->assertStringContainsString('duplicate dispatch suppressed', $result['message']);
        $this->assertEquals(1, GatewayAttempt::where('financial_operation_id', $operation->id)->count());
        $this->assertEquals('initiated', $operation->fresh()->status);
    }

    /**
     * Audit Finding P1-5: Pre-transfer allocation reservation holds orders before transfer initiation.
     */
    public function test_pre_allocation_reservation_holds_orders_before_transfer_initiation(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'eligible',
            'vendor_net_90' => '30000.00',
        ]);

        $this->assertEquals('30000.00', $order->remaining_payable_to_vendor);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '30000.00',
            'status' => 'pending',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'Access Bank',
        ]);

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('createTransferRecipient')->once()->andReturn([
            'success' => true,
            'recipient_code' => 'RCP_PRE_ALLOC_TEST',
        ]);
        $mock->shouldReceive('initiateTransfer')->once()->andReturn([
            'success' => true,
            'status' => 'pending',
            'transfer_code' => 'TRF_HELD_123',
        ]);
        $this->app->instance(PaystackService::class, $mock);

        $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);

        // Order payable balance is immediately reserved/held (0.00 available) even though transfer is still pending
        $this->assertEquals('0.00', $order->fresh()->remaining_payable_to_vendor);
        $this->assertEquals(1, VendorPayoutAllocation::where('vendor_payout_request_id', $payout->id)->count());
    }

    /**
     * Audit Finding P1-6: Refund reconciliation ignores unauthorized cancelled orders.
     */
    public function test_refund_processed_webhook_requires_exact_amount_and_currency_before_finalization(): void
    {
        $cases = [
            [
                'id' => 'evt-refund-amount-mismatch',
                'refund_reference' => 'rf-amount-mismatch',
                'amount' => 49999,
                'currency' => 'NGN',
                'reason' => 'REFUND_AMOUNT_MISMATCH',
            ],
            [
                'id' => 'evt-refund-currency-mismatch',
                'refund_reference' => 'rf-currency-mismatch',
                'amount' => 50000,
                'currency' => 'USD',
                'reason' => 'REFUND_CURRENCY_MISMATCH',
            ],
            [
                'id' => 'evt-refund-transaction-reference-mismatch',
                'refund_reference' => 'rf-transaction-reference-mismatch',
                'amount' => 50000,
                'currency' => 'NGN',
                'transaction_reference' => 'DIFFERENT-ORIGINAL-PAYMENT',
                'reason' => 'REFUND_TRANSACTION_REFERENCE_MISMATCH',
            ],
            [
                'id' => 'evt-refund-missing-amount',
                'refund_reference' => 'rf-missing-amount',
                'currency' => 'NGN',
                'reason' => 'REFUND_MISSING_AMOUNT',
            ],
            [
                'id' => 'evt-refund-missing-currency',
                'refund_reference' => 'rf-missing-currency',
                'amount' => 50000,
                'reason' => 'REFUND_CURRENCY_MISMATCH',
            ],
        ];

        foreach ($cases as $case) {
            $order = $this->createTestOrder([
                'order_status' => 'cancelled',
                'payment_status' => 'paid',
                'grand_total' => '22000.00',
                'payment_reference' => 'REF-'.strtoupper(str_replace('-', '', $case['id'])),
                'paystack_refund_status' => 'pending',
            ]);

            $operation = app(FinancialOperationService::class)->createRefundOperation(
                $order,
                '500.00',
                'webhook amount/currency validation proof',
                null,
                'IDEMPOTENCY-'.$case['id']
            );
            $metadata = $operation->metadata ?? [];
            $metadata['refund_reference'] = $case['refund_reference'];
            $operation->update(['metadata' => $metadata]);

            $refundData = [
                'refund_reference' => $case['refund_reference'],
                'transaction_reference' => $case['transaction_reference'] ?? $order->payment_reference,
                'status' => 'processed',
            ];
            foreach (['amount', 'currency'] as $field) {
                if (array_key_exists($field, $case)) {
                    $refundData[$field] = $case[$field];
                }
            }

            $payloadData = [
                'id' => $case['id'],
                'event' => 'refund.processed',
                'data' => $refundData,
            ];
            $payload = json_encode($payloadData, JSON_THROW_ON_ERROR);
            $signature = hash_hmac('sha512', $payload, app(PaystackService::class)->getSecretKey());

            $response = $this->call('POST', route('payment.paystack.webhook'), [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ], $payload);

            $response->assertStatus(400);
            $event = ProcessedWebhookEvent::where('event_id', $case['id'])->first();
            $this->assertNotNull($event);
            $this->assertEquals('failed', $event->status);
            $this->assertStringContainsString($case['reason'], (string) $event->error_reason);
            $this->assertNotEquals('confirmed', $operation->fresh()->status);
            $this->assertEquals(1, \App\Models\FinancialReservation::where('financial_operation_id', $operation->id)
                ->where('status', 'active')->count());
            $this->assertEquals('pending', $order->fresh()->paystack_refund_status);
        }
    }

    /**
     * A refund.processed webhook must not mark an order refunded when there is no
     * matching FinancialOperation to reconcile the reservation and ledger.
     */
    public function test_unmatched_refund_processed_webhook_does_not_mark_order_completed(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-UNMATCHED-REFUND-01',
            'paystack_refund_reference' => null,
            'paystack_refund_status' => 'pending',
        ]);

        $payloadData = [
            'id' => 'evt-unmatched-refund-01',
            'event' => 'refund.processed',
            'data' => [
                'refund_reference' => 'rf-unmatched-01',
                'transaction_reference' => $order->payment_reference,
                'status' => 'processed',
            ],
        ];
        $payload = json_encode($payloadData);
        $signature = hash_hmac('sha512', $payload, app(PaystackService::class)->getSecretKey());

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertOk();
        $this->assertEquals('pending', $order->fresh()->paystack_refund_status);
        $this->assertNull($order->fresh()->paystack_refund_reference);

        $event = ProcessedWebhookEvent::where('event_id', 'evt-unmatched-refund-01')->first();
        $this->assertNotNull($event);
        $this->assertEquals('unmatched', $event->status);
    }

    /**
     * A refund.failed webhook without an exact operation match must not change order state.
     */
    public function test_unmatched_refund_failed_webhook_does_not_fail_order_refund(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-UNMATCHED-FAILED-01',
            'paystack_refund_reference' => null,
            'paystack_refund_status' => 'pending',
        ]);

        $payloadData = [
            'id' => 'evt-unmatched-failed-01',
            'event' => 'refund.failed',
            'data' => [
                'refund_reference' => 'rf-not-linked-01',
                'transaction_reference' => $order->payment_reference,
                'status' => 'failed',
            ],
        ];
        $payload = json_encode($payloadData);
        $signature = hash_hmac('sha512', $payload, app(PaystackService::class)->getSecretKey());

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertOk();
        $this->assertEquals('pending', $order->fresh()->paystack_refund_status);
        $event = ProcessedWebhookEvent::where('event_id', 'evt-unmatched-failed-01')->first();
        $this->assertNotNull($event);
        $this->assertEquals('unmatched', $event->status);
    }

    public function test_paystack_webhook_rejects_invalid_signature_without_processing_event(): void
    {
        $payload = json_encode([
            'id' => 'evt-invalid-signature-proof',
            'event' => 'charge.success',
            'data' => ['reference' => 'forged-reference'],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call('POST', route('payment.paystack.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => 'invalid-signature',
        ], $payload);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('processed_webhook_events', [
            'event_id' => 'evt-invalid-signature-proof',
        ]);
    }

    public function test_paystack_webhook_rejects_missing_signature_without_processing_event(): void
    {
        $payload = json_encode([
            'id' => 'evt-missing-signature-proof',
            'event' => 'charge.success',
            'data' => ['reference' => 'forged-reference'],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call('POST', route('payment.paystack.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('processed_webhook_events', [
            'event_id' => 'evt-missing-signature-proof',
        ]);
    }

    /**
     * A gateway-confirmed refund without a financial operation must not bypass the ledger.
     */
    public function test_refund_reconciliation_does_not_complete_refund_without_financial_operation(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-VERIFIED-NO-OP-01',
            'paystack_refund_reference' => 'rf-verified-no-op-01',
            'paystack_refund_status' => 'pending',
        ]);

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('verifyRefund')->once()->with('rf-verified-no-op-01')->andReturn([
            'success' => true,
            'refund_reference' => 'rf-verified-no-op-01',
            'status' => 'processed',
        ]);
        $this->app->instance(PaystackService::class, $mock);

        $reconciled = app(FinancialReconciliationEngine::class)->reconcileRefunds();

        $this->assertEquals(0, $reconciled);
        $this->assertEquals('pending', $order->fresh()->paystack_refund_status);
    }

    public function test_refund_reconciliation_ignores_unauthorized_cancelled_orders(): void
    {
        // Cancelled order without explicit refund status or return case
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'paystack_refund_reference' => null,
            'paystack_refund_status' => null,
        ]);

        $mock = Mockery::mock(PaystackService::class);
        // initiateRefund must NEVER be called for arbitrary unapproved cancelled order
        $mock->shouldNotReceive('initiateRefund');
        $this->app->instance(PaystackService::class, $mock);

        $engine = app(FinancialReconciliationEngine::class);
        $reconciled = $engine->reconcileRefunds();

        $this->assertEquals(0, $reconciled);
        $this->assertNull($order->fresh()->paystack_refund_reference);
        $this->assertNull($order->fresh()->paystack_refund_status);
    }

    /**
     * Terminal rejected payouts must not be selected again just because a stale
     * reconciliation hold token remains on the row.
     */
    public function test_payout_reconciliation_skips_terminal_rejected_payouts_with_stale_hold_tokens(): void
    {
        VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '40000.00',
            'status' => 'rejected',
            'gateway_status' => 'failed',
            'processing_token' => 'HOLD_TIMEOUT_RECONCILIATION',
            'processing_started_at' => Carbon::now()->subMinutes(20),
            'transfer_reference' => 'VND-TERMINAL-STALE-HOLD',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'Zenith Bank',
        ]);

        LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '12000.00',
            'status' => 'rejected',
            'gateway_status' => 'failed',
            'processing_token' => 'HOLD_TIMEOUT_RECONCILIATION',
            'processing_started_at' => Carbon::now()->subMinutes(20),
            'transfer_reference' => 'FLEET-TERMINAL-STALE-HOLD',
            'account_number' => '0123456789',
            'account_name' => 'Swift Courier Ltd',
            'bank_name' => 'GTBank',
        ]);

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldNotReceive('verifyTransfer');
        $this->app->instance(PaystackService::class, $mock);

        $result = app(FinancialReconciliationEngine::class)->reconcilePayouts();

        $this->assertEquals(0, $result['vendor_reconciled']);
        $this->assertEquals(0, $result['logistics_reconciled']);
        $this->assertEquals('rejected', VendorPayoutRequest::where('transfer_reference', 'VND-TERMINAL-STALE-HOLD')->firstOrFail()->status);
        $this->assertEquals('rejected', LogisticsPayoutRequest::where('transfer_reference', 'FLEET-TERMINAL-STALE-HOLD')->firstOrFail()->status);
    }


    /**
     * Lease ownership is rechecked inside the locked finalization transaction,
     * closing the race between the reconciler's pre-check and ledger mutation.
     */
    public function test_payout_finalizers_reject_a_reconciliation_worker_with_a_lost_lease(): void
    {
        $vendorPayout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '40000.00',
            'status' => 'pending',
            'gateway_status' => 'processing',
            'processing_token' => 'RECON-VND-NEW-OWNER',
            'processing_started_at' => Carbon::now(),
            'transfer_reference' => 'VND-LEASE-FENCE-01',
            'account_number' => '0123456789',
            'account_name' => 'Audit Merchant Store',
            'bank_name' => 'Zenith Bank',
        ]);

        $logisticsPayout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $this->company->id,
            'amount' => '12000.00',
            'status' => 'pending',
            'gateway_status' => 'processing',
            'processing_token' => 'RECON-FLEET-NEW-OWNER',
            'processing_started_at' => Carbon::now(),
            'transfer_reference' => 'FLEET-LEASE-FENCE-01',
            'account_number' => '0123456789',
            'account_name' => 'Swift Courier Ltd',
            'bank_name' => 'GTBank',
        ]);

        $service = app(PayoutFinalizationService::class);
        $vendorResult = $service->finalizeVendorPayout($vendorPayout, [
            'reference' => 'VND-LEASE-FENCE-01',
            'status' => 'success',
        ], null, 'RECON-VND-OLD-OWNER');
        $logisticsResult = $service->finalizeLogisticsPayout($logisticsPayout, [
            'reference' => 'FLEET-LEASE-FENCE-01',
            'status' => 'success',
        ], null, 'RECON-FLEET-OLD-OWNER');

        $this->assertTrue($vendorResult['protected']);
        $this->assertTrue($logisticsResult['protected']);
        $this->assertEquals('pending', $vendorPayout->fresh()->status);
        $this->assertEquals('RECON-VND-NEW-OWNER', $vendorPayout->fresh()->processing_token);
        $this->assertEquals('pending', $logisticsPayout->fresh()->status);
        $this->assertEquals('RECON-FLEET-NEW-OWNER', $logisticsPayout->fresh()->processing_token);
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-VND-PAYOUT-' . $vendorPayout->id)->exists());
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-FLEET-PAYOUT-' . $logisticsPayout->id)->exists());
    }


    public function test_stale_webhook_recovery_uses_stale_generation_and_enforces_attempt_limit(): void
    {
        $oldTime = Carbon::now()->subMinutes(10);

        $retryable = ProcessedWebhookEvent::create([
            'event_id' => 'evt-stale-recovery-retryable',
            'event_type' => 'transfer.success',
            'payload_hash' => hash('sha256', 'retryable'),
            'payload' => '{}',
            'status' => 'processing',
            'attempts' => 1,
        ]);
        $exhausted = ProcessedWebhookEvent::create([
            'event_id' => 'evt-stale-recovery-exhausted',
            'event_type' => 'transfer.success',
            'payload_hash' => hash('sha256', 'exhausted'),
            'payload' => '{}',
            'status' => 'processing',
            'attempts' => 3,
        ]);

        \Illuminate\Support\Facades\DB::table('processed_webhook_events')
            ->whereIn('id', [$retryable->id, $exhausted->id])
            ->update(['updated_at' => $oldTime]);

        $recovered = app(FinancialReconciliationEngine::class)->reconcileStaleWebhooks();

        $this->assertEquals(1, $recovered);
        $this->assertEquals('failed', $retryable->fresh()->status);
        $this->assertEquals(2, $retryable->fresh()->attempts);
        $this->assertStringContainsString('TIMEOUT_STALE_WORKER', (string) $retryable->fresh()->error_reason);
        $this->assertEquals('failed', $exhausted->fresh()->status);
        $this->assertEquals(3, $exhausted->fresh()->attempts);
        $this->assertStringContainsString('MAX_ATTEMPTS_EXCEEDED', (string) $exhausted->fresh()->error_reason);
    }

    public function test_refund_reconciliation_keeps_claim_when_another_worker_reclaims_order(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '15000.00',
            'payment_reference' => 'pay-original-lease-race',
            'paystack_refund_reference' => 'rf-lease-race-01',
            'paystack_refund_status' => 'pending',
        ]);

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('verifyRefund')->once()->with('rf-lease-race-01')->andReturnUsing(function () use ($order) {
            \Illuminate\Support\Facades\DB::table('orders')->where('id', $order->id)->update([
                'refund_reconciliation_token' => 'NEW-LEASE-OWNER',
                'refund_reconciliation_claimed_at' => Carbon::now(),
                'paystack_refund_status' => 'processing',
            ]);
            return ['success' => true, 'refund_reference' => 'rf-lease-race-01', 'status' => 'failed'];
        });
        $this->app->instance(PaystackService::class, $mock);

        $result = app(FinancialReconciliationEngine::class)->reconcileRefunds();

        $this->assertEquals(0, $result);
        $order->refresh();
        $this->assertEquals('processing', $order->paystack_refund_status);
        $this->assertEquals('NEW-LEASE-OWNER', $order->refund_reconciliation_token);
    }

    public function test_webhook_confirmation_revokes_stale_reconciler_claim_before_gateway_failure_can_reset_order(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-REFUND-WEBHOOK-RECONCILER-RACE',
            'paystack_refund_status' => 'pending',
            'paystack_refund_reference' => 'rf-webhook-reconciler-race',
        ]);

        $operation = app(FinancialOperationService::class)->createRefundOperation(
            $order, '500.00', 'webhook versus reconciliation race', null,
            'IDEMPOTENCY-WEBHOOK-RECONCILER-RACE'
        );

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('verifyRefund')->once()->with('rf-webhook-reconciler-race')
            ->andReturnUsing(function () use ($order, $operation) {
                // The scheduled worker has claimed the order by the time it verifies.
                $freshOrder = $order->fresh();
                $this->assertNotEmpty($freshOrder->refund_reconciliation_token);

                // A successful webhook wins the race and revokes that worker's claim.
                $result = app(FinancialOperationService::class)->finalizeRefundConfirmation(
                    $operation,
                    ['status' => 'processed', 'refund_reference' => 'rf-webhook-reconciler-race']
                );
                $this->assertTrue($result['success']);

                // Simulate a stale/contradictory gateway result arriving back to the worker.
                return ['success' => true, 'refund_reference' => 'rf-webhook-reconciler-race', 'status' => 'failed'];
            });
        $this->app->instance(PaystackService::class, $mock);

        $result = app(FinancialReconciliationEngine::class)->reconcileRefunds();

        $this->assertSame(0, $result);
        $this->assertSame('confirmed', $operation->fresh()->status);
        $this->assertSame('completed', $order->fresh()->paystack_refund_status);
        $this->assertNull($order->fresh()->refund_reconciliation_token);
        $this->assertNull($order->fresh()->refund_reconciliation_claimed_at);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $operation->id)
            ->where('status', 'committed')->count());
    }



    public function test_reconciliation_matches_each_partial_refund_to_its_own_operation_and_reservation(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-MULTI-PARTIAL-' . uniqid(),
            'paystack_refund_status' => 'pending',
            'paystack_refund_reference' => 'rf-partial-second',
        ]);

        $service = app(FinancialOperationService::class);
        $first = $service->createRefundOperation(
            $order, '500.00', 'first partial refund', null, 'IDEMPOTENCY-PARTIAL-FIRST-' . uniqid()
        );
        $second = $service->createRefundOperation(
            $order, '700.00', 'second partial refund', null, 'IDEMPOTENCY-PARTIAL-SECOND-' . uniqid()
        );
        $first->update(['status' => 'initiated']);
        $second->update(['status' => 'initiated']);

        foreach ([
            [$first, 'rf-partial-first', 'ATT-PARTIAL-FIRST'],
            [$second, 'rf-partial-second', 'ATT-PARTIAL-SECOND'],
        ] as [$operation, $gatewayReference, $attemptReference]) {
            GatewayAttempt::create([
                'financial_operation_id' => $operation->id,
                'gateway_provider' => 'paystack',
                'endpoint' => '/refund',
                'attempt_reference' => $attemptReference,
                'gateway_reference' => $gatewayReference,
                'request_payload' => [
                    'transaction' => $order->payment_reference,
                    'amount_kobo' => $operation->amount_kobo,
                    'amount_naira' => $operation->amount_naira,
                ],
                'outcome' => 'success',
                'attempted_at' => Carbon::now(),
            ]);
        }

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('verifyRefund')->once()->with('rf-partial-second')->andReturn([
            'success' => true,
            'refund_reference' => 'rf-partial-second',
            'status' => 'processed',
        ]);
        $mock->shouldReceive('verifyRefund')->once()->with('rf-partial-first')->andReturn([
            'success' => true,
            'refund_reference' => 'rf-partial-first',
            'status' => 'processed',
        ]);
        $this->app->instance(PaystackService::class, $mock);

        $engine = app(FinancialReconciliationEngine::class);
        $this->assertSame(1, $engine->reconcileRefunds());
        $this->assertSame('initiated', $first->fresh()->status, 'The first refund must not be finalized with the second refund reference.');
        $this->assertSame('confirmed', $second->fresh()->status);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $first->id)->where('status', 'active')->count());
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $second->id)->where('status', 'committed')->count());

        // The order-level summary is completed after one refund, but the remaining
        // operation must still be discoverable and reconciled by its own reference.
        $this->assertSame('completed', $order->fresh()->paystack_refund_status);
        $this->assertSame(1, $engine->reconcileRefunds());
        $this->assertSame('confirmed', $first->fresh()->status);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $first->id)->where('status', 'committed')->count());
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $second->id)->where('status', 'committed')->count());
    }


    public function test_late_refund_success_cannot_resurrect_failed_operation_after_reservation_release(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-LATE-SUCCESS-AFTER-FAIL-' . uniqid(),
            'paystack_refund_status' => 'failed',
        ]);

        $service = app(FinancialOperationService::class);
        $operation = $service->createRefundOperation(
            $order,
            '500.00',
            'late success after failed refund',
            null,
            'IDEMPOTENCY-LATE-SUCCESS-AFTER-FAIL-' . uniqid()
        );

        $operation->update(['status' => 'failed', 'failed_at' => Carbon::now()]);
        FinancialReservation::where('financial_operation_id', $operation->id)
            ->where('status', 'active')
            ->update(['status' => 'released', 'released_at' => Carbon::now()]);

        $result = $service->finalizeRefundConfirmation($operation, [
            'status' => 'processed',
            'refund_reference' => 'rf-late-success-after-fail',
        ]);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['protected']);
        $this->assertSame('failed', $operation->fresh()->status);
        $this->assertSame('failed', $order->fresh()->paystack_refund_status);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $operation->id)
            ->where('status', 'released')->count());
        $this->assertSame(0, FinancialReservation::where('financial_operation_id', $operation->id)
            ->where('status', 'committed')->count());
        $this->assertSame(0, FinancialJournalEntry::where('financial_operation_id', $operation->id)->count());
    }

    public function test_failed_partial_refund_keeps_order_pending_while_another_refund_is_outstanding(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-PARTIAL-FAIL-SUMMARY-' . uniqid(),
            'paystack_refund_status' => 'processing',
        ]);

        $service = app(FinancialOperationService::class);
        $failedOperation = $service->createRefundOperation(
            $order, '500.00', 'first partial refund fails', null, 'IDEMPOTENCY-PARTIAL-FAIL-' . uniqid()
        );
        $pendingOperation = $service->createRefundOperation(
            $order, '700.00', 'second partial refund remains pending', null, 'IDEMPOTENCY-PARTIAL-PENDING-' . uniqid()
        );

        $failedMetadata = $failedOperation->metadata ?? [];
        $failedMetadata['refund_reference'] = 'rf-partial-failed-summary';
        $failedOperation->update(['status' => 'initiated', 'metadata' => $failedMetadata]);
        $pendingMetadata = $pendingOperation->metadata ?? [];
        $pendingMetadata['refund_reference'] = 'rf-partial-still-pending';
        $pendingOperation->update(['status' => 'initiated', 'metadata' => $pendingMetadata]);

        $payloadData = [
            'id' => 'evt-partial-failed-summary-' . uniqid(),
            'event' => 'refund.failed',
            'data' => [
                'refund_reference' => 'rf-partial-failed-summary',
                'transaction_reference' => $order->payment_reference,
                'status' => 'failed',
            ],
        ];
        $payload = json_encode($payloadData);
        $signature = hash_hmac('sha512', $payload, app(PaystackService::class)->getSecretKey());

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertOk();
        $this->assertSame('failed', $failedOperation->fresh()->status);
        $this->assertSame('initiated', $pendingOperation->fresh()->status);
        $this->assertSame('pending', $order->fresh()->paystack_refund_status);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $failedOperation->id)
            ->where('status', 'released')->count());
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $pendingOperation->id)
            ->where('status', 'active')->count());
    }

    public function test_refund_failed_webhook_with_conflicting_original_payment_reference_does_not_fail_operation(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-EXPECTED-ORIGINAL-PAYMENT-' . uniqid(),
            'paystack_refund_status' => 'pending',
        ]);

        $operation = app(FinancialOperationService::class)->createRefundOperation(
            $order,
            '500.00',
            'failed webhook identity mismatch',
            null,
            'IDEMPOTENCY-FAILED-WEBHOOK-IDENTITY-' . uniqid()
        );
        $metadata = $operation->metadata ?? [];
        $metadata['refund_reference'] = 'rf-failed-identity-mismatch';
        $metadata['payment_reference'] = $order->payment_reference;
        $operation->update(['status' => 'initiated', 'metadata' => $metadata]);

        $payloadData = [
            'id' => 'evt-failed-identity-mismatch-' . uniqid(),
            'event' => 'refund.failed',
            'data' => [
                'refund_reference' => 'rf-failed-identity-mismatch',
                'transaction_reference' => 'SOME-OTHER-PAYMENT-REFERENCE',
                'status' => 'failed',
            ],
        ];
        $payload = json_encode($payloadData);
        $signature = hash_hmac('sha512', $payload, app(PaystackService::class)->getSecretKey());

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertOk();
        $this->assertSame('initiated', $operation->fresh()->status);
        $this->assertSame('pending', $order->fresh()->paystack_refund_status);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $operation->id)
            ->where('status', 'active')->count());
        $event = ProcessedWebhookEvent::where('event_id', $payloadData['id'])->first();
        $this->assertNotNull($event);
        $this->assertSame('unmatched', $event->status);
        $this->assertStringContainsString('TRANSACTION_REFERENCE_MISMATCH', $event->error_reason);
    }


    public function test_refund_failed_webhook_with_conflicting_amount_or_currency_does_not_release_reservation(): void
    {
        $cases = [
            ['suffix' => 'amount', 'amount' => 49999, 'currency' => 'NGN', 'reason' => 'REFUND_AMOUNT_MISMATCH'],
            ['suffix' => 'currency', 'amount' => 50000, 'currency' => 'USD', 'reason' => 'REFUND_CURRENCY_MISMATCH'],
        ];

        foreach ($cases as $case) {
            $order = $this->createTestOrder([
                'order_status' => 'cancelled',
                'payment_status' => 'paid',
                'grand_total' => '22000.00',
                'payment_reference' => 'REF-FAILED-FINANCIAL-EVIDENCE-' . strtoupper($case['suffix']) . '-' . uniqid(),
                'paystack_refund_status' => 'pending',
            ]);

            $refundReference = 'rf-failed-financial-evidence-' . $case['suffix'] . '-' . uniqid();
            $operation = app(FinancialOperationService::class)->createRefundOperation(
                $order,
                '500.00',
                'failed webhook financial evidence mismatch',
                null,
                'IDEMPOTENCY-FAILED-FINANCIAL-EVIDENCE-' . uniqid()
            );
            $metadata = $operation->metadata ?? [];
            $metadata['refund_reference'] = $refundReference;
            $metadata['payment_reference'] = $order->payment_reference;
            $operation->update(['status' => 'initiated', 'metadata' => $metadata]);

            $payloadData = [
                'id' => 'evt-failed-financial-evidence-' . $case['suffix'] . '-' . uniqid(),
                'event' => 'refund.failed',
                'data' => [
                    'refund_reference' => $refundReference,
                    'transaction_reference' => $order->payment_reference,
                    'amount' => $case['amount'],
                    'currency' => $case['currency'],
                    'status' => 'failed',
                ],
            ];
            $payload = json_encode($payloadData);
            $signature = hash_hmac('sha512', $payload, app(PaystackService::class)->getSecretKey());

            $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
                'x-paystack-signature' => $signature,
            ]);

            $response->assertOk();
            $this->assertSame('initiated', $operation->fresh()->status, $case['suffix']);
            $this->assertSame('pending', $order->fresh()->paystack_refund_status, $case['suffix']);
            $this->assertSame(1, FinancialReservation::where('financial_operation_id', $operation->id)
                ->where('status', 'active')->count(), $case['suffix']);

            $event = ProcessedWebhookEvent::where('event_id', $payloadData['id'])->first();
            $this->assertNotNull($event, $case['suffix']);
            $this->assertSame('unmatched', $event->status, $case['suffix']);
            $this->assertStringContainsString($case['reason'], $event->error_reason, $case['suffix']);
        }
    }

}
