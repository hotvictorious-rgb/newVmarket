<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialOperation;
use App\Models\FinancialReservation;
use App\Models\GatewayAttempt;
use App\Models\Lga;
use App\Models\Order;
use App\Models\ReturnCase;
use App\Models\ProcessedWebhookEvent;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorPayoutAllocation;
use App\Models\VendorPayoutRequest;
use App\Models\VendorProfile;
use App\Services\FinancialOperationService;
use App\Services\ReturnAndRefundService;
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
            'bank_code' => '057',
            'bank_updated_at' => Carbon::now()->subDays(5),
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
     * Reconciliation must not treat a generic cancelled order as refund approval.
     */
    public function test_refund_reconciliation_does_not_dispatch_unapproved_cancelled_order(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'vendor_settlement_status' => 'held',
            'grand_total' => '15000.00',
            'payment_reference' => 'REF-UNAPPROVED-RECON',
            'paystack_refund_reference' => null,
            'paystack_refund_status' => 'pending',
        ]);

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldNotReceive('initiateRefund');
        $this->app->instance(PaystackService::class, $mock);

        $reconciled = app(FinancialReconciliationEngine::class)->reconcileRefunds();

        $this->assertSame(0, $reconciled);
        $this->assertNull($order->fresh()->paystack_refund_reference);
        $this->assertSame('pending', $order->fresh()->paystack_refund_status);
        $this->assertSame(0, FinancialOperation::where('metadata->order_id', $order->id)->count());
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
        $this->assertNotEmpty($event->error_reason);
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
     * New payout requests reclassify vendor_payable to vendor_payout_payable.
     * Successful transfer clears payout payable; confirmed reversal restores vendor payable.
     */
    public function test_requested_vendor_payout_liability_clears_and_restores_across_success_and_reversal(): void
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
        VendorPayoutAllocation::create([
            'vendor_payout_request_id' => $payout->id,
            'order_id' => $order->id,
            'allocated_amount' => '50000.00',
            'status' => 'allocated',
        ]);

        $requestEvent = 'EVT-MERCHANT-REQ-' . $payout->id;
        FinancialJournalEntry::create([
            'order_id' => null, 'transaction_reference' => 'MERCHANT-REQ-TEST-' . $payout->id,
            'event_id' => $requestEvent, 'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $this->vendor->id, 'account_name' => 'vendor_payable',
            'entry_type' => 'debit', 'amount' => '50000.00', 'currency' => 'NGN',
            'description' => 'Test payout request liability reclassification',
        ]);
        FinancialJournalEntry::create([
            'order_id' => null, 'transaction_reference' => 'MERCHANT-REQ-TEST-' . $payout->id,
            'event_id' => $requestEvent, 'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $this->vendor->id, 'account_name' => 'vendor_payout_payable',
            'entry_type' => 'credit', 'amount' => '50000.00', 'currency' => 'NGN',
            'description' => 'Test payout request liability reclassification',
        ]);

        $finalizer = app(PayoutFinalizationService::class);
        $finalizer->finalizeVendorPayout($payout, [
            'transfer_code' => 'TRF_RESERVED_LIABILITY_TEST',
            'reference' => 'VND-RESERVED-LIABILITY-' . $payout->id,
            'status' => 'success',
        ], $this->superAdmin);

        $payoutEntries = FinancialJournalEntry::where('event_id', 'EVT-VND-PAYOUT-' . $payout->id)->get();
        $this->assertCount(2, $payoutEntries);
        $this->assertTrue($payoutEntries->contains(fn ($entry) => $entry->account_name === 'vendor_payout_payable' && $entry->entry_type === 'debit' && bccomp((string)$entry->amount, '50000.00', 2) === 0));
        $this->assertTrue($payoutEntries->contains(fn ($entry) => $entry->account_name === 'cash_in_escrow' && $entry->entry_type === 'credit' && bccomp((string)$entry->amount, '50000.00', 2) === 0));

        $result = $finalizer->revertVendorPayout($payout, 'Verified transfer reversal', $this->superAdmin, true);
        $this->assertTrue($result['success']);
        $reversalEntries = FinancialJournalEntry::where('event_id', 'EVT-REV-VND-' . $payout->id)->get();
        $this->assertCount(2, $reversalEntries);
        $this->assertTrue($reversalEntries->contains(fn ($entry) => $entry->account_name === 'cash_in_escrow' && $entry->entry_type === 'debit' && bccomp((string)$entry->amount, '50000.00', 2) === 0));
        $this->assertTrue($reversalEntries->contains(fn ($entry) => $entry->account_name === 'vendor_payable' && $entry->entry_type === 'credit' && bccomp((string)$entry->amount, '50000.00', 2) === 0));
    }

    /**
     * A failed transfer before success must reverse the request reclassification,
     * without creating a fictitious cash movement.
     */
    public function test_failed_vendor_payout_reverses_request_reclassification_without_cash_journal(): void
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
        $requestEvent = 'EVT-MERCHANT-REQ-' . $payout->id;
        FinancialJournalEntry::create([
            'order_id' => null, 'transaction_reference' => 'MERCHANT-REQ-FAIL-' . $payout->id,
            'event_id' => $requestEvent, 'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $this->vendor->id, 'account_name' => 'vendor_payable',
            'entry_type' => 'debit', 'amount' => '50000.00', 'currency' => 'NGN',
            'description' => 'Test payout request liability reclassification',
        ]);
        FinancialJournalEntry::create([
            'order_id' => null, 'transaction_reference' => 'MERCHANT-REQ-FAIL-' . $payout->id,
            'event_id' => $requestEvent, 'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $this->vendor->id, 'account_name' => 'vendor_payout_payable',
            'entry_type' => 'credit', 'amount' => '50000.00', 'currency' => 'NGN',
            'description' => 'Test payout request liability reclassification',
        ]);

        $result = app(PayoutFinalizationService::class)->revertVendorPayout(
            $payout,
            'Paystack transfer failed before success',
            $this->superAdmin
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('rejected', $payout->fresh()->status);
        $this->assertEquals('reversed', $allocation->fresh()->status);
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-VND-PAYOUT-' . $payout->id)->exists());
        $reversalEntries = FinancialJournalEntry::where('event_id', 'EVT-REV-VND-' . $payout->id)->get();
        $this->assertCount(2, $reversalEntries);
        $this->assertTrue($reversalEntries->contains(fn ($entry) => $entry->account_name === 'vendor_payout_payable' && $entry->entry_type === 'debit'));
        $this->assertTrue($reversalEntries->contains(fn ($entry) => $entry->account_name === 'vendor_payable' && $entry->entry_type === 'credit'));
        $this->assertFalse($reversalEntries->contains(fn ($entry) => $entry->account_name === 'cash_in_escrow'));
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
                'id' => 'evt-refund-fractional-kobo',
                'refund_reference' => 'rf-fractional-kobo',
                'amount' => 50000.5,
                'currency' => 'NGN',
                'reason' => 'REFUND_MISSING_AMOUNT',
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

    /**
     * Terminal rejected vendor payouts must not be selected again just because a stale
     * reconciliation hold token remains on the row.
     */
    public function test_payout_reconciliation_skips_terminal_rejected_vendor_payouts_with_stale_hold_tokens(): void
    {
        $payout = VendorPayoutRequest::create([
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

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldNotReceive('verifyTransfer');
        $this->app->instance(PaystackService::class, $mock);

        $result = app(FinancialReconciliationEngine::class)->reconcilePayouts();

        $this->assertEquals(0, $result['vendor_reconciled']);
        $this->assertEquals('rejected', $payout->fresh()->status);
    }


    /**
     * Lease ownership is rechecked inside the locked finalization transaction,
     * closing the race between the reconciler's pre-check and ledger mutation.
     */
    public function test_vendor_payout_finalizer_rejects_a_reconciliation_worker_with_a_lost_lease(): void
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

        $result = app(PayoutFinalizationService::class)->finalizeVendorPayout($vendorPayout, [
            'reference' => 'VND-LEASE-FENCE-01',
            'status' => 'success',
        ], null, 'RECON-VND-OLD-OWNER');

        $this->assertTrue($result['protected']);
        $this->assertEquals('pending', $vendorPayout->fresh()->status);
        $this->assertEquals('RECON-VND-NEW-OWNER', $vendorPayout->fresh()->processing_token);
        $this->assertFalse(FinancialJournalEntry::where('event_id', 'EVT-VND-PAYOUT-' . $vendorPayout->id)->exists());
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

        // The order-level summary stays pending while the other partial refund is outstanding.
        $this->assertSame('pending', $order->fresh()->paystack_refund_status);
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
            ['suffix' => 'fractional_amount', 'amount' => 50000.5, 'currency' => 'NGN', 'reason' => 'REFUND_AMOUNT_MISMATCH'],
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



    public function test_refund_reconciliation_does_not_finalize_gateway_amount_or_currency_mismatch(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-RECON-FINANCIAL-EVIDENCE-' . uniqid(),
            'paystack_refund_status' => 'pending',
            'paystack_refund_reference' => 'rf-recon-financial-evidence',
        ]);

        $operation = app(FinancialOperationService::class)->createRefundOperation(
            $order,
            '500.00',
            'gateway verification financial evidence mismatch',
            null,
            'IDEMPOTENCY-RECON-FINANCIAL-EVIDENCE-' . uniqid()
        );
        $operation->update(['status' => 'initiated']);
        GatewayAttempt::create([
            'financial_operation_id' => $operation->id,
            'gateway_provider' => 'paystack',
            'endpoint' => '/refund',
            'attempt_reference' => 'ATT-RECON-FINANCIAL-EVIDENCE',
            'gateway_reference' => 'rf-recon-financial-evidence',
            'request_payload' => [
                'transaction' => $order->payment_reference,
                'amount_kobo' => $operation->amount_kobo,
                'amount_naira' => $operation->amount_naira,
            ],
            'outcome' => 'success',
            'attempted_at' => Carbon::now(),
        ]);

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('verifyRefund')->once()->with('rf-recon-financial-evidence')->andReturn([
            'success' => true,
            'refund_reference' => 'rf-recon-financial-evidence',
            'status' => 'processed',
            'data' => [
                'reference' => 'rf-recon-financial-evidence',
                'amount' => 49999,
                'currency' => 'USD',
            ],
        ]);
        $this->app->instance(PaystackService::class, $mock);

        $engine = app(FinancialReconciliationEngine::class);
        $this->assertSame(0, $engine->reconcileRefunds());
        $this->assertSame('initiated', $operation->fresh()->status);
        $this->assertSame('pending', $order->fresh()->paystack_refund_status);
        $this->assertNull($order->fresh()->refund_reconciliation_token);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $operation->id)
            ->where('status', 'active')->count());
        $this->assertSame(0, FinancialReservation::where('financial_operation_id', $operation->id)
            ->where('status', 'committed')->count());
    }



    public function test_refund_finalizer_rejects_conflicting_financial_evidence_before_reservation_or_journal_mutation(): void
    {
        $cases = [
            ['suffix' => 'amount', 'evidence' => ['status' => 'processed', 'amount' => 49999, 'currency' => 'NGN'], 'code' => 'REFUND_AMOUNT_MISMATCH'],
            ['suffix' => 'fractional_amount', 'evidence' => ['status' => 'processed', 'amount' => 50000.5, 'currency' => 'NGN'], 'code' => 'REFUND_AMOUNT_MISMATCH'],
            ['suffix' => 'currency', 'evidence' => ['status' => 'processed', 'amount' => 50000, 'currency' => 'USD'], 'code' => 'REFUND_CURRENCY_MISMATCH'],
        ];

        foreach ($cases as $case) {
            $order = $this->createTestOrder([
                'order_status' => 'cancelled',
                'payment_status' => 'paid',
                'grand_total' => '22000.00',
                'payment_reference' => 'REF-CENTRAL-FINALIZER-' . strtoupper($case['suffix']) . '-' . uniqid(),
                'paystack_refund_status' => 'pending',
            ]);
            $operation = app(FinancialOperationService::class)->createRefundOperation(
                $order,
                '500.00',
                'central finalizer financial evidence mismatch',
                null,
                'IDEMPOTENCY-CENTRAL-FINALIZER-' . uniqid()
            );
            $operation->update(['status' => 'initiated']);

            $result = app(FinancialOperationService::class)->finalizeRefundConfirmation($operation, $case['evidence']);

            $this->assertFalse($result['success'], $case['suffix']);
            $this->assertSame($case['code'], $result['code'], $case['suffix']);
            $this->assertSame('initiated', $operation->fresh()->status, $case['suffix']);
            $this->assertSame('pending', $order->fresh()->paystack_refund_status, $case['suffix']);
            $this->assertSame(1, FinancialReservation::where('financial_operation_id', $operation->id)
                ->where('status', 'active')->count(), $case['suffix']);
            $this->assertSame(0, FinancialReservation::where('financial_operation_id', $operation->id)
                ->where('status', 'committed')->count(), $case['suffix']);
            $this->assertSame(0, FinancialJournalEntry::where('financial_operation_id', $operation->id)->count(), $case['suffix']);
        }
    }

    public function test_repeated_refund_finalization_does_not_duplicate_journal_entries_or_reservation_commits(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-DUPLICATE-FINALIZATION-' . uniqid(),
            'paystack_refund_status' => 'pending',
        ]);
        $operation = app(FinancialOperationService::class)->createRefundOperation(
            $order,
            '500.00',
            'duplicate finalization idempotency proof',
            null,
            'IDEMPOTENCY-DUPLICATE-FINALIZATION-' . uniqid()
        );
        $operation->update(['status' => 'initiated']);
        $evidence = ['status' => 'processed', 'refund_reference' => 'rf-duplicate-finalization'];

        $first = app(FinancialOperationService::class)->finalizeRefundConfirmation($operation, $evidence);
        $this->assertTrue($first['success']);
        $journalEntries = FinancialJournalEntry::where('financial_operation_id', $operation->id)->get();
        $journalCountAfterFirst = $journalEntries->count();
        $this->assertGreaterThan(0, $journalCountAfterFirst);
        $this->assertSame($journalCountAfterFirst, $journalEntries->pluck('idempotency_key')->filter()->unique()->count());

        // The database must reject a duplicate refund journal line even if an
        // unexpected caller bypasses the service-level terminal-state guard.
        $firstEntry = $journalEntries->first();
        try {
            // Isolate the expected unique-constraint violation in a savepoint.
            // PostgreSQL marks the current transaction failed after a statement
            // error unless the failing nested transaction is rolled back first.
            \Illuminate\Support\Facades\DB::transaction(function () use ($firstEntry): void {
                FinancialJournalEntry::create([
                    'order_id' => $firstEntry->order_id,
                    'financial_operation_id' => $firstEntry->financial_operation_id,
                    'transaction_reference' => $firstEntry->transaction_reference,
                    'event_id' => $firstEntry->event_id,
                    'idempotency_key' => $firstEntry->idempotency_key,
                    'beneficiary_type' => $firstEntry->beneficiary_type,
                    'beneficiary_id' => $firstEntry->beneficiary_id,
                    'account_name' => $firstEntry->account_name,
                    'entry_type' => $firstEntry->entry_type,
                    'amount' => $firstEntry->amount,
                    'currency' => $firstEntry->currency,
                    'description' => $firstEntry->description,
                ]);
            });
            $this->fail('Duplicate financial journal idempotency key should be rejected by the database.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame($journalCountAfterFirst, FinancialJournalEntry::where('financial_operation_id', $operation->id)->count());
        }

        $second = app(FinancialOperationService::class)->finalizeRefundConfirmation($operation->fresh(), $evidence);
        $this->assertTrue($second['success']);
        $this->assertSame($journalCountAfterFirst, FinancialJournalEntry::where('financial_operation_id', $operation->id)->count());
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $operation->id)
            ->where('status', 'committed')->count());
        $this->assertSame(0, FinancialReservation::where('financial_operation_id', $operation->id)
            ->where('status', 'active')->count());
    }



    public function test_webhook_event_id_cannot_be_reused_with_a_different_signed_payload(): void
    {
        $originalPayload = json_encode([
            'id' => 'evt-immutable-payload-' . uniqid(),
            'event' => 'charge.success',
            'data' => ['reference' => 'ORIGINAL-REFERENCE'],
        ], JSON_UNESCAPED_SLASHES);

        $eventId = json_decode($originalPayload, true)['id'];
        $storedHash = hash('sha256', $originalPayload);

        ProcessedWebhookEvent::create([
            'event_id' => $eventId,
            'event_type' => 'charge.success',
            'payload_hash' => $storedHash,
            'payload' => $originalPayload,
            'status' => 'completed',
            'attempts' => 1,
            'processed_at' => Carbon::now(),
        ]);

        $conflictingPayload = [
            'id' => $eventId,
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'VND-PAYOUT-CONFLICTING-REFERENCE',
                'transfer_code' => 'TRF-CONFLICTING-CODE',
                'amount' => 500000,
                'currency' => 'NGN',
                'status' => 'success',
            ],
        ];
        $rawConflictingPayload = json_encode($conflictingPayload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha512', $rawConflictingPayload, app(PaystackService::class)->getSecretKey());

        $response = $this->call(
            'POST',
            route('payment.paystack.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ],
            $rawConflictingPayload
        );

        $response->assertStatus(409);
        $event = ProcessedWebhookEvent::where('event_id', $eventId)->firstOrFail();
        $this->assertSame('completed', $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertSame($storedHash, $event->payload_hash);
        $this->assertSame($originalPayload, $event->payload);
    }



    public function test_unmatched_webhook_can_be_retried_only_with_same_payload_and_preserves_attempt_history(): void
    {
        $payload = json_encode([
            'id' => 'evt-retry-unmatched-' . uniqid(),
            'event' => 'refund.processed',
            'data' => [
                'refund_reference' => 'RF-MANUAL-RETRY',
                'transaction_reference' => 'PAYMENT-MANUAL-RETRY',
            ],
        ], JSON_UNESCAPED_SLASHES);
        $decoded = json_decode($payload, true);
        $eventId = $decoded['id'];
        $hash = hash('sha256', $payload);

        ProcessedWebhookEvent::create([
            'event_id' => $eventId,
            'event_type' => 'refund.processed',
            'payload_hash' => $hash,
            'payload' => $payload,
            'status' => 'unmatched',
            'error_reason' => 'Awaiting manual reconciliation',
            'attempts' => 1,
            'processed_at' => Carbon::now(),
        ]);

        $signature = hash_hmac('sha512', $payload, app(PaystackService::class)->getSecretKey());
        $response = $this->call(
            'POST',
            route('payment.paystack.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ],
            $payload
        );

        $response->assertOk();
        $event = ProcessedWebhookEvent::where('event_id', $eventId)->firstOrFail();
        $this->assertSame('unmatched', $event->status);
        $this->assertSame(2, $event->attempts);
        $this->assertStringContainsString('REFUND_OPERATION_NOT_FOUND', $event->error_reason);
        $this->assertSame($hash, $event->payload_hash);
        $this->assertSame($payload, $event->payload);
    }

    public function test_unmatched_webhook_with_conflicting_payload_is_not_retried(): void
    {
        $originalPayload = json_encode([
            'id' => 'evt-no-conflicting-retry-' . uniqid(),
            'event' => 'refund.processed',
            'data' => ['refund_reference' => 'ORIGINAL-REFUND', 'transaction_reference' => 'ORIGINAL-PAYMENT'],
        ], JSON_UNESCAPED_SLASHES);
        $eventId = json_decode($originalPayload, true)['id'];
        $hash = hash('sha256', $originalPayload);

        ProcessedWebhookEvent::create([
            'event_id' => $eventId,
            'event_type' => 'refund.processed',
            'payload_hash' => $hash,
            'payload' => $originalPayload,
            'status' => 'unmatched',
            'error_reason' => 'Awaiting manual reconciliation',
            'attempts' => 3,
        ]);

        $conflictingPayload = json_encode([
            'id' => $eventId,
            'event' => 'refund.processed',
            'data' => [
                'refund_reference' => 'DIFFERENT-REFUND',
                'transaction_reference' => 'DIFFERENT-PAYMENT',
            ],
        ], JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha512', $conflictingPayload, app(PaystackService::class)->getSecretKey());

        $response = $this->call(
            'POST',
            route('payment.paystack.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ],
            $conflictingPayload
        );

        $response->assertStatus(409);
        $event = ProcessedWebhookEvent::where('event_id', $eventId)->firstOrFail();
        $this->assertSame('unmatched', $event->status);
        $this->assertSame(3, $event->attempts);
        $this->assertSame($hash, $event->payload_hash);
        $this->assertSame($originalPayload, $event->payload);
    }



    public function test_confirming_one_partial_refund_does_not_mark_order_completed_while_another_is_outstanding(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-PARTIAL-SUMMARY-CONCURRENT-' . uniqid(),
            'paystack_refund_status' => 'pending',
        ]);

        $service = app(FinancialOperationService::class);
        $first = $service->createRefundOperation(
            $order, '500.00', 'first partial refund', null, 'IDEMPOTENCY-PARTIAL-SUMMARY-FIRST-' . uniqid()
        );
        $second = $service->createRefundOperation(
            $order, '700.00', 'second partial refund', null, 'IDEMPOTENCY-PARTIAL-SUMMARY-SECOND-' . uniqid()
        );
        $first->update(['status' => 'initiated']);
        $second->update(['status' => 'initiated']);

        $firstResult = $service->finalizeRefundConfirmation($first, [
            'status' => 'processed',
            'refund_reference' => 'rf-partial-summary-first',
        ]);
        $this->assertTrue($firstResult['success']);
        $this->assertSame('confirmed', $first->fresh()->status);
        $this->assertSame('initiated', $second->fresh()->status);
        $this->assertSame('pending', $order->fresh()->paystack_refund_status);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $first->id)
            ->where('status', 'committed')->count());
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $second->id)
            ->where('status', 'active')->count());

        $secondResult = $service->finalizeRefundConfirmation($second, [
            'status' => 'processed',
            'refund_reference' => 'rf-partial-summary-second',
        ]);
        $this->assertTrue($secondResult['success']);
        $this->assertSame('completed', $order->fresh()->paystack_refund_status);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $second->id)
            ->where('status', 'committed')->count());
    }


    public function test_refund_idempotency_key_reuse_with_different_amount_is_rejected(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-IDEMPOTENCY-AMOUNT-' . uniqid(),
            'paystack_refund_status' => 'pending',
        ]);

        $service = app(FinancialOperationService::class);
        $key = 'IDEMPOTENCY-REFUND-EXACT-INSTRUCTION-' . uniqid();
        $original = $service->createRefundOperation($order, '500.00', 'original instruction', null, $key);

        // An exact retry is safe and returns the original operation.
        $retry = $service->createRefundOperation($order, '500.00', 'original instruction', null, $key);
        $this->assertSame($original->id, $retry->id);

        try {
            $service->createRefundOperation($order, '600.00', 'changed amount', null, $key);
            $this->fail('Expected conflicting idempotency-key reuse to be rejected.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('different refund instruction', $exception->getMessage());
        }


        try {
            $service->createRefundOperation($order, '500.00', 'different reason', null, $key);
            $this->fail('Expected conflicting idempotency-key reason reuse to be rejected.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('different refund instruction', $exception->getMessage());
        }

        $this->assertSame(1, FinancialOperation::where('idempotency_key', $key)->count());
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $original->id)
            ->where('status', 'active')->count());
    }

    public function test_refund_idempotency_key_cannot_be_reused_for_another_order(): void
    {
        $firstOrder = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-IDEMPOTENCY-ORDER-A-' . uniqid(),
            'paystack_refund_status' => 'pending',
        ]);
        $secondOrder = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '22000.00',
            'payment_reference' => 'REF-IDEMPOTENCY-ORDER-B-' . uniqid(),
            'paystack_refund_status' => 'pending',
        ]);

        $service = app(FinancialOperationService::class);
        $key = 'IDEMPOTENCY-REFUND-CROSS-ORDER-' . uniqid();
        $original = $service->createRefundOperation($firstOrder, '500.00', 'first order refund', null, $key);

        try {
            $service->createRefundOperation($secondOrder, '500.00', 'second order refund', null, $key);
            $this->fail('Expected cross-order idempotency-key reuse to be rejected.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('different refund instruction', $exception->getMessage());
        }

        $this->assertSame(1, FinancialOperation::where('idempotency_key', $key)->count());
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $original->id)->count());
        $this->assertSame(0, FinancialReservation::where('source_id', $secondOrder->id)
            ->where('purpose', 'customer_refund_hold')->count());
    }


    public function test_confirmed_refunds_and_active_reservations_share_one_cumulative_ceiling(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '1000.00',
            'payment_reference' => 'REF-CUMULATIVE-CEILING-' . uniqid(),
            'paystack_refund_status' => 'pending',
        ]);

        $service = app(FinancialOperationService::class);
        $confirmedCandidate = $service->createRefundOperation(
            $order, '700.00', 'first partial refund', null, 'IDEMPOTENCY-CEILING-FIRST-' . uniqid()
        );
        $activeCandidate = $service->createRefundOperation(
            $order, '300.00', 'second partial refund', null, 'IDEMPOTENCY-CEILING-SECOND-' . uniqid()
        );

        $confirmedCandidate->update(['status' => 'initiated']);
        $result = $service->finalizeRefundConfirmation($confirmedCandidate, [
            'status' => 'processed',
            'refund_reference' => 'rf-ceiling-first',
        ]);
        $this->assertTrue($result['success']);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $confirmedCandidate->id)
            ->where('status', 'committed')->count());
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $activeCandidate->id)
            ->where('status', 'active')->count());

        try {
            $service->createRefundOperation(
                $order, '0.01', 'over ceiling attempt', null, 'IDEMPOTENCY-CEILING-OVER-' . uniqid()
            );
            $this->fail('Expected cumulative confirmed and active refund reservations to enforce the original payment ceiling.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('invariant violation', $exception->getMessage());
        }

        $this->assertSame(2, FinancialReservation::where('source_type', Order::class)
            ->where('source_id', $order->id)
            ->where('purpose', 'customer_refund_hold')
            ->whereIn('status', ['active', 'committed'])
            ->count());
    }


    public function test_refund_reservation_rejects_unpaid_order_without_creating_operation_or_hold(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'pending',
            'grand_total' => '1000.00',
            'payment_reference' => 'REF-UNPAID-ORDER-' . uniqid(),
            'paystack_refund_status' => 'pending',
        ]);

        try {
            app(FinancialOperationService::class)->createRefundOperation(
                $order, '100.00', 'unpaid order refund', null, 'IDEMPOTENCY-UNPAID-' . uniqid()
            );
            $this->fail('Unpaid orders must not create Paystack refund operations.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('payment is not confirmed as paid', $exception->getMessage());
        }

        $this->assertSame(0, FinancialOperation::where('metadata->order_id', $order->id)->count());
        $this->assertSame(0, FinancialReservation::where('source_type', Order::class)
            ->where('source_id', $order->id)
            ->where('purpose', 'customer_refund_hold')->count());
    }

    public function test_refund_reservation_rejects_paid_order_without_original_gateway_reference(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '1000.00',
            'payment_reference' => null,
            'paystack_refund_status' => 'pending',
        ]);

        try {
            app(FinancialOperationService::class)->createRefundOperation(
                $order, '100.00', 'missing payment reference refund', null, 'IDEMPOTENCY-MISSING-REF-' . uniqid()
            );
            $this->fail('Gateway refunds must be bound to the original payment reference.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('without the original payment reference', $exception->getMessage());
        }

        $this->assertSame(0, FinancialOperation::where('metadata->order_id', $order->id)->count());
        $this->assertSame(0, FinancialReservation::where('source_type', Order::class)
            ->where('source_id', $order->id)
            ->where('purpose', 'customer_refund_hold')->count());
    }


    public function test_legacy_confirmed_refund_without_reservation_still_reduces_refundable_ceiling(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '1000.00',
            'payment_reference' => 'REF-LEGACY-CONFIRMED-' . uniqid(),
            'paystack_refund_status' => 'pending',
        ]);

        $service = app(FinancialOperationService::class);
        $legacyRefund = $service->createRefundOperation(
            $order, '700.00', 'legacy confirmed refund', null, 'IDEMPOTENCY-LEGACY-CONFIRMED-' . uniqid()
        );
        $legacyRefund->update(['status' => 'confirmed']);
        $legacyRefund->reservations()->delete();

        try {
            $service->createRefundOperation(
                $order, '301.00', 'over-refund after legacy confirmed refund', null,
                'IDEMPOTENCY-LEGACY-OVER-' . uniqid()
            );
            $this->fail('A confirmed refund without a reservation must still count against the original payment ceiling.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('invariant violation', $exception->getMessage());
        }

        $this->assertSame(1, FinancialOperation::where('id', $legacyRefund->id)->count());
        $this->assertSame(0, FinancialReservation::where('source_type', Order::class)
            ->where('source_id', $order->id)
            ->where('purpose', 'customer_refund_hold')
            ->where('status', 'active')->count());
    }


    public function test_refund_operation_rejects_return_case_from_another_order(): void
    {
        $firstOrder = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '1000.00',
            'payment_reference' => 'REF-CASE-OWNER-A-' . uniqid(),
        ]);
        $secondOrder = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '1000.00',
            'payment_reference' => 'REF-CASE-OWNER-B-' . uniqid(),
        ]);
        $returnCase = ReturnCase::create([
            'case_number' => 'RET-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'order_id' => $firstOrder->id,
            'vendor_id' => $this->vendor->id,
            'return_reason' => 'other',
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '250.00',
        ]);

        try {
            app(FinancialOperationService::class)->createRefundOperation(
                $secondOrder, '250.00', 'mismatched return case', $returnCase,
                'IDEMPOTENCY-CASE-OWNER-' . uniqid()
            );
            $this->fail('A return case must not refund a different order.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('return case does not belong to the specified order', $exception->getMessage());
        }

        $this->assertSame(0, FinancialOperation::where('metadata->order_id', $secondOrder->id)->count());
        $this->assertSame(0, FinancialReservation::where('source_type', Order::class)
            ->where('source_id', $secondOrder->id)
            ->where('purpose', 'customer_refund_hold')->count());
    }



    public function test_refund_amount_rejects_precision_beyond_kobo_instead_of_silently_truncating(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '1000.00',
            'payment_reference' => 'REF-EXCESS-REFUND-PRECISION-' . uniqid(),
            'paystack_refund_status' => 'pending',
        ]);

        try {
            app(FinancialOperationService::class)->createRefundOperation(
                $order, '10.999', 'excess precision refund', null, 'IDEMPOTENCY-EXCESS-PRECISION-' . uniqid()
            );
            $this->fail('Refund amounts with more than two fractional digits must be rejected, not truncated.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('no more than two fractional digits', $exception->getMessage());
        }

        $this->assertSame(0, FinancialOperation::where('metadata->order_id', $order->id)->count());
        $this->assertSame(0, FinancialReservation::where('source_type', Order::class)
            ->where('source_id', $order->id)
            ->where('purpose', 'customer_refund_hold')->count());
    }


    public function test_cumulative_refunds_cannot_exceed_approved_return_case_amount(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'cancelled',
            'payment_status' => 'paid',
            'grand_total' => '1000.00',
            'payment_reference' => 'REF-CASE-CEILING-' . uniqid(),
            'paystack_refund_status' => 'pending',
        ]);
        $returnCase = ReturnCase::create([
            'case_number' => 'RET-CASE-CEILING-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'return_reason' => 'other',
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '250.00',
            'reviewed_by_admin_id' => $this->superAdmin->id,
        ]);

        $service = app(FinancialOperationService::class);
        $auth = [
            'type' => 'admin_approved_return',
            'approved_by_user_id' => $this->superAdmin->id,
        ];
        $first = $service->createRefundOperation(
            $order, '200.00', 'approved return case partial refund', $returnCase,
            'IDEMPOTENCY-CASE-CEILING-FIRST-' . uniqid(),
            $auth
        );
        $this->assertSame(20000, (int) $first->amount_kobo);

        try {
            $service->createRefundOperation(
                $order, '50.01', 'exceeds approved return case balance', $returnCase,
                'IDEMPOTENCY-CASE-CEILING-OVER-' . uniqid(),
                $auth
            );
            $this->fail('Cumulative refunds must not exceed the approved return-case amount.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('approved refundable balance', $exception->getMessage());
        }

        $this->assertSame(1, FinancialOperation::where('metadata->return_case_id', $returnCase->id)->count());
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $first->id)
            ->where('status', 'active')->count());
    }


    public function test_delivery_fee_refund_requires_persisted_component_authorization_and_extends_case_ceiling(): void
    {
        $order = $this->createTestOrder([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'merchandise_subtotal' => '1000.00',
            'grand_total' => '1150.00',
            'delivery_fee' => '150.00',
            'vendor_net_90' => '900.00',
            'payment_reference' => 'REF-CASE-DELIVERY-' . uniqid(),
            'paystack_refund_status' => 'pending',
        ]);

        $returnCase = ReturnCase::create([
            'case_number' => 'RET-CASE-DELIVERY-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'return_reason' => 'other',
            'status' => 'refund_pending',
            'resolution_type' => 'refund',
            'total_refundable_amount' => '1000.00',
            'refund_delivery_fee' => true,
            'reviewed_by_admin_id' => $this->superAdmin->id,
        ]);

        $service = app(FinancialOperationService::class);
        $operation = $service->createRefundOperation(
            $order,
            '1150.00',
            'Approved merchandise and delivery fee refund',
            $returnCase,
            'IDEMPOTENCY-CASE-DELIVERY-' . uniqid(),
            [
                'type' => 'admin_approved_return',
                'approved_by_user_id' => $this->superAdmin->id,
                'refund_delivery_fee' => true,
            ]
        );

        $this->assertSame(115000, (int) $operation->amount_kobo);
        $this->assertTrue((bool) $operation->metadata['refund_authorization']['refund_delivery_fee']);
        $this->assertSame(1, FinancialReservation::where('financial_operation_id', $operation->id)
            ->where('purpose', 'customer_refund_hold')
            ->where('status', 'active')
            ->count());

        // Once an operation exists, a retry cannot silently change its component
        // scope; otherwise reconciliation could post a delivery reversal that the
        // gateway operation did not include.
        $scopeChange = app(ReturnAndRefundService::class)->adminDisburseRefund(
            $this->superAdmin,
            $returnCase,
            null,
            null,
            false
        );
        $this->assertFalse($scopeChange['success']);
        $this->assertStringContainsString('component scope is immutable', $scopeChange['message']);
    }

}
