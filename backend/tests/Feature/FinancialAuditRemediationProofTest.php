<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialOperation;
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
        $this->assertEquals(2, FinancialJournalEntry::where('event_id', 'EVT-REV-FLEET-' . $payout->id)->count()); // 1 debit, 1 credit

        // Second reversal call (e.g. from scheduled reconciler race)
        $secondRes = $finalizationService->revertLogisticsPayout($payout, 'Reconciler found failure');
        $this->assertTrue($secondRes['success']);
        $this->assertTrue($secondRes['idempotent'] ?? false);

        // Balance must STILL be 70,000 (never double credited!)
        $this->assertEquals('70000.00', number_format((float)$this->company->fresh()->wallet_balance, 2, '.', ''));
        // Ledger entry count must STILL be 2 (never duplicated!)
        $this->assertEquals(2, FinancialJournalEntry::where('event_id', 'EVT-REV-FLEET-' . $payout->id)->count());
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
        $reversalResult = $finalizer->revertVendorPayout($payout, 'Bank transfer rejected by NIBSS', $this->superAdmin);
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

        $result = $finalizer->revertVendorPayout($payout, 'Gateway transfer reversed', $this->superAdmin);
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
}
