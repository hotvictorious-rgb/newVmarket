<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\FinancialJournalEntry;
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
use App\Services\DemoAccountRegistry;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FinancialCrashRecoveryAndAdversarialTest extends TestCase
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
            'business_name' => 'Adversarial Test Merchant',
            'status' => 'approved',
            'bank_name' => 'Zenith Bank',
            'bank_code' => '057',
            'account_number' => '1012345678',
            'account_name' => 'Adversarial Test Merchant',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Flagship Test Shop',
            'slug' => 'flagship-test-shop-' . uniqid(),
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyoLga->id,
            'is_active' => true,
            'address' => '12 Oron Road, Uyo',
        ]);

        $this->customer = User::factory()->create([
            'user_type' => 'customer',
            'status' => 'active',
        ]);
    }

    /**
     * P0 Test: Webhook Inbox recovers after a crashed worker and completes on retry.
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

    public function test_webhook_inbox_recovers_after_crash_and_retries_successfully(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '10000.00',
            'status' => 'approved',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Test Vendor',
            'settlement_proof_path' => 'paystack_transfer:TRF_CRASH_RECOVER_99',
            'paystack_transfer_code' => 'TRF_CRASH_RECOVER_99',
            'transfer_reference' => 'VND-CRASH-REF-01',
            'admin_notes' => 'Automated Paystack NUBAN Transfer (TRF_CRASH_RECOVER_99)',
        ]);

        // Simulate an earlier crashed attempt that left event in 'processing' state 2 minutes ago
        $crashedEvent = ProcessedWebhookEvent::create([
            'event_id' => 'evt_crash_test_999',
            'event_type' => 'transfer.success',
            'payload_hash' => hash('sha256', 'mock_payload'),
            'status' => 'processing',
            'attempts' => 1,
        ]);
        $crashedEvent->updated_at = Carbon::now()->subMinutes(2);
        $crashedEvent->save();

        $payloadData = [
            'id' => 'evt_crash_test_999',
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'VND-CRASH-REF-01',
                'transfer_code' => 'TRF_CRASH_RECOVER_99',
                'amount' => 1000000,
            ],
        ];

        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);

        // Paystack retries delivery
        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Assert Inbox marked completed and attempts incremented
        $crashedEvent->refresh();
        $this->assertEquals('completed', $crashedEvent->status);
        $this->assertEquals(2, $crashedEvent->attempts);

        // Payout delivery confirmed
        $payout->refresh();
        $this->assertNotNull($payout->disbursed_at);
        $this->assertEquals('success', $payout->gateway_status);
    }

    /**
     * P0 Test: Concurrent in-flight delivery (< 60s) is throttled with 429 backoff.
     */
    public function test_webhook_inbox_throttles_concurrent_in_flight_delivery(): void
    {
        // An active worker is currently processing this event (touched 10 seconds ago)
        $activeEvent = ProcessedWebhookEvent::create([
            'event_id' => 'evt_concurrent_active_01',
            'event_type' => 'transfer.success',
            'payload_hash' => hash('sha256', 'mock_payload'),
            'status' => 'processing',
            'attempts' => 1,
        ]);

        $payloadData = [
            'id' => 'evt_concurrent_active_01',
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'REF-CONCURRENT-01',
                'transfer_code' => 'TRF-CONCURRENT-01',
            ],
        ];

        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(429);
        $response->assertJson(['status' => false]);
    }

    /**
     * P0 Test: Durable Gateway Timeout Lock strictly blocks second payout approval.
     */
    public function test_gateway_timeout_hold_durably_blocks_subsequent_admin_approval(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '10000.00',
            'status' => 'pending',
            'processing_token' => 'HOLD_TIMEOUT_RECONCILIATION',
            'processing_started_at' => Carbon::now(),
            'gateway_status' => 'processing',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Test Vendor',
            'admin_notes' => 'GATEWAY_TIMEOUT: Transfer outcome uncertain. Locked in HOLD_TIMEOUT_RECONCILIATION awaiting gateway verification',
        ]);

        // Admin attempts to approve the locked payout again
        $response = $this->actingAs($this->superAdmin)->post(route('admin.disburse.vendor_payout'), [
            'payout_id' => $payout->id,
            'notes' => 'Duplicate attempt',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('GATEWAY TIMEOUT', session('error'));

        // Payout remains locked
        $payout->refresh();
        $this->assertEquals('HOLD_TIMEOUT_RECONCILIATION', $payout->processing_token);
        $this->assertEquals('pending', $payout->status);
    }

    /**
     * P0/P1 Test: Gateway reconciliation finalizes payout when Paystack verifies transfer success.
     */
    public function test_admin_reconcile_vendor_payout_finalizes_on_gateway_success(): void
    {
        $order = $this->createTestOrder([
            'order_number' => 'ORD-RECONCILE-01',
            'merchandise_subtotal' => '10000.00',
            'vendor_net_90' => '9000.00',
            'grand_total' => '10000.00',
            'vendor_settlement_status' => 'eligible',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '9000.00',
            'status' => 'pending',
            'processing_token' => 'HOLD_TIMEOUT_RECONCILIATION',
            'transfer_reference' => 'VND-TIMEOUT-REF-77',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Test Vendor',
            'admin_notes' => 'GATEWAY_TIMEOUT: Transfer outcome uncertain.',
        ]);

        // Mock Paystack returning verified success
        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('verifyTransfer')
            ->with('VND-TIMEOUT-REF-77')
            ->andReturn([
                'success' => true,
                'status' => 'success',
                'transfer_code' => 'TRF_RECONCILED_SUCCESS_77',
            ]);
        $this->app->instance(PaystackService::class, $mock);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.reconcile.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);

        $response->assertSessionHas('status');

        $payout->refresh();
        $this->assertEquals('approved', $payout->status);
        $this->assertNull($payout->processing_token);
        $this->assertEquals('success', $payout->gateway_status);
        $this->assertNotNull($payout->disbursed_at);

        // Order is settled with allocation record
        $order->refresh();
        $this->assertEquals('settled', $order->vendor_settlement_status);

        $allocation = VendorPayoutAllocation::where('vendor_payout_request_id', $payout->id)->first();
        $this->assertNotNull($allocation);
        $this->assertEquals('allocated', $allocation->status);
        $this->assertEquals('9000.00', $allocation->allocated_amount);
    }

    /**
     * P0/P1 Test: Gateway reconciliation safely unlocks payout when Paystack confirms transfer was not executed.
     */
    public function test_admin_reconcile_vendor_payout_safely_unlocks_on_gateway_not_found(): void
    {
        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '9000.00',
            'status' => 'pending',
            'processing_token' => 'HOLD_TIMEOUT_RECONCILIATION',
            'transfer_reference' => 'VND-TIMEOUT-FAIL-00',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Test Vendor',
            'admin_notes' => 'GATEWAY_TIMEOUT: Transfer outcome uncertain.',
        ]);

        // Mock Paystack returning failed / not found
        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('verifyTransfer')
            ->with('VND-TIMEOUT-FAIL-00')
            ->andReturn([
                'success' => false,
                'status' => 'failed',
                'message' => 'Transfer reference not found at gateway',
            ]);
        $this->app->instance(PaystackService::class, $mock);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.reconcile.vendor_payout'), [
            'payout_id' => $payout->id,
        ]);

        $response->assertSessionHas('status');

        $payout->refresh();
        $this->assertEquals('pending', $payout->status);
        $this->assertNull($payout->processing_token);
        $this->assertEquals('failed', $payout->gateway_status);
        $this->assertStringContainsString('was not executed at Paystack. Safely unlocked for review', session('status'));
    }

    /**
     * P1 Test: Allocation lifecycle records 'reversed' status and timestamp on failure.
     */
    public function test_allocation_lifecycle_tracks_reversed_status_and_timestamp(): void
    {
        $order = $this->createTestOrder([
            'order_number' => 'ORD-ALLOC-REV-01',
            'merchandise_subtotal' => '15000.00',
            'vendor_net_90' => '13500.00',
            'grand_total' => '15000.00',
            'vendor_settlement_status' => 'settled',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '13500.00',
            'status' => 'approved',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Test Vendor',
            'settlement_proof_path' => 'paystack_transfer:TRF_ALLOC_REV_88',
            'paystack_transfer_code' => 'TRF_ALLOC_REV_88',
            'transfer_reference' => 'VND-ALLOC-REV-88',
        ]);

        $alloc = VendorPayoutAllocation::create([
            'vendor_payout_request_id' => $payout->id,
            'order_id' => $order->id,
            'allocated_amount' => '13500.00',
            'status' => 'allocated',
        ]);

        $payloadData = [
            'event' => 'transfer.failed',
            'data' => [
                'reference' => 'VND-ALLOC-REV-88',
                'transfer_code' => 'TRF_ALLOC_REV_88',
                'reason' => 'Bank system error',
            ],
        ];

        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $signature = hash_hmac('sha512', $payloadJson, $secretKey);

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        // Assert allocation row explicitly marked as reversed with timestamp
        $alloc->refresh();
        $this->assertEquals('reversed', $alloc->status);
        $this->assertNotNull($alloc->reversed_at);

        // Order returned to eligible
        $order->refresh();
        $this->assertEquals('eligible', $order->vendor_settlement_status);
        $this->assertEquals('13500.00', $order->remaining_payable_to_vendor);
    }

    /**
     * P2 Test: Demo account bypass is mathematically impossible in production environment.
     */
    public function test_demo_account_access_fails_closed_in_production_environment(): void
    {
        $this->app['env'] = 'production';

        $demoUser = User::factory()->create([
            'email' => 'demo.vendor@victoriousmarket.ng',
            'password' => bcrypt('RealComplexPassword987!'),
            'user_type' => 'vendor',
            'status' => 'active',
            'is_demo' => true,
        ]);

        // Attempting to use demo universal password in production
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => $demoUser->email,
            'password' => DemoAccountRegistry::DEMO_PASSWORD,
        ]);

        // Must fail closed with 422 Invalid credentials
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['login']);
    }

    /**
     * Audit Finding 1 Resolution:
     * Automatic reconciliation engine must allocate orders and write balanced double-entry financial journal.
     */
    public function test_automatic_reconciliation_engine_allocates_orders_and_writes_balanced_financial_journal(): void
    {
        $order = $this->createTestOrder([
            'order_number' => 'ORD-AUTO-RECON-01',
            'merchandise_subtotal' => '10000.00',
            'vendor_net_90' => '9000.00',
            'grand_total' => '10000.00',
            'vendor_settlement_status' => 'eligible',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '9000.00',
            'status' => 'pending',
            'processing_token' => 'HOLD_TIMEOUT_RECONCILIATION',
            'transfer_reference' => 'VND-AUTO-RECON-REF-99',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Test Vendor',
            'admin_notes' => 'GATEWAY_TIMEOUT: Awaiting scheduled reconciliation.',
        ]);

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('verifyTransfer')
            ->with('VND-AUTO-RECON-REF-99')
            ->andReturn([
                'success' => true,
                'status' => 'success',
                'transfer_code' => 'TRF_AUTO_RECON_99',
            ]);
        $mock->shouldReceive('initiateRefund')->andReturn(['success' => true, 'refund_reference' => 'rf_auto_99']);
        $this->app->instance(PaystackService::class, $mock);

        $engine = app(\App\Services\FinancialReconciliationEngine::class);
        $result = $engine->reconcilePayouts();

        $this->assertEquals(1, $result['vendor_reconciled']);

        $payout->refresh();
        $this->assertEquals('approved', $payout->status);
        $this->assertEquals('success', $payout->gateway_status);
        $this->assertNull($payout->processing_token);
        $this->assertNotNull($payout->disbursed_at);

        // Order is transitioned to settled
        $order->refresh();
        $this->assertEquals('settled', $order->vendor_settlement_status);

        // Exact allocation line item created
        $allocation = VendorPayoutAllocation::where('vendor_payout_request_id', $payout->id)
            ->where('order_id', $order->id)
            ->first();
        $this->assertNotNull($allocation);
        $this->assertEquals('9000.00', $allocation->allocated_amount);
        $this->assertEquals('allocated', $allocation->status);

        // Balanced double-entry financial journal written
        $debitEntry = \App\Models\FinancialJournalEntry::where('beneficiary_id', $this->vendor->id)
            ->where('account_name', 'vendor_payable')
            ->where('entry_type', 'debit')
            ->where('amount', '9000.00')
            ->first();
        $this->assertNotNull($debitEntry);

        $creditEntry = \App\Models\FinancialJournalEntry::where('beneficiary_id', $this->vendor->id)
            ->where('account_name', 'cash_in_escrow')
            ->where('entry_type', 'credit')
            ->where('amount', '9000.00')
            ->first();
        $this->assertNotNull($creditEntry);
    }

    /**
     * Audit Finding 3 Resolution:
     * Webhook transfer.success arriving before admin request finalizes allocations and journal without double counting.
     */
    public function test_webhook_transfer_success_finalizes_allocations_and_journal_if_webhook_arrives_first(): void
    {
        $order = $this->createTestOrder([
            'order_number' => 'ORD-WEBHOOK-RECON-01',
            'merchandise_subtotal' => '10000.00',
            'vendor_net_90' => '9000.00',
            'grand_total' => '10000.00',
            'vendor_settlement_status' => 'eligible',
        ]);

        $payout = VendorPayoutRequest::create([
            'vendor_id' => $this->vendor->id,
            'amount' => '9000.00',
            'status' => 'pending',
            'processing_token' => 'HOLD_TIMEOUT_RECONCILIATION',
            'transfer_reference' => 'VND-WEBHOOK-FAST-REF-88',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Test Vendor',
            'admin_notes' => 'GATEWAY_TIMEOUT: In-flight transfer.',
        ]);

        $payloadData = [
            'id' => 'evt_fast_webhook_88',
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'VND-WEBHOOK-FAST-REF-88',
                'transfer_code' => 'TRF_WEBHOOK_FAST_88',
                'amount' => 900000,
                'status' => 'success',
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
        $this->assertEquals('approved', $payout->status);
        $this->assertEquals('success', $payout->gateway_status);
        $this->assertNotNull($payout->disbursed_at);

        // Order is settled
        $order->refresh();
        $this->assertEquals('settled', $order->vendor_settlement_status);

        // Allocation exists
        $allocation = VendorPayoutAllocation::where('vendor_payout_request_id', $payout->id)->first();
        $this->assertNotNull($allocation);
        $this->assertEquals('9000.00', $allocation->allocated_amount);

        // Calling finalization again is completely idempotent
        $finalizer = app(\App\Services\PayoutFinalizationService::class);
        $secondResult = $finalizer->finalizeVendorPayout($payout, [
            'transfer_code' => 'TRF_WEBHOOK_FAST_88',
            'reference' => 'VND-WEBHOOK-FAST-REF-88',
        ]);

        $this->assertTrue($secondResult['idempotent']);
        $this->assertEquals(1, VendorPayoutAllocation::where('vendor_payout_request_id', $payout->id)->count());
    }

    /**
     * Audit Finding 5 Resolution:
     * Refund reconciliation engine recovers pending refunds.
     */
    public function test_reconciliation_engine_recovers_pending_refunds(): void
    {
        $order = $this->createTestOrder([
            'order_number' => 'ORD-REFUND-PENDING-01',
            'payment_status' => 'paid',
            'order_status' => 'cancelled',
            'paystack_refund_status' => 'pending',
            'paystack_refund_reference' => null,
            'payment_reference' => 'REF-PAY-PENDING-99',
            'grand_total' => '5000.00',
        ]);

        $mock = Mockery::mock(PaystackService::class);
        $mock->shouldReceive('initiateRefund')
            ->with('REF-PAY-PENDING-99', '5000.00', Mockery::any())
            ->andReturn([
                'success' => true,
                'refund_reference' => 'rf_reconciled_99',
                'status' => 'completed',
            ]);
        $this->app->instance(PaystackService::class, $mock);

        $engine = app(\App\Services\FinancialReconciliationEngine::class);
        $reconciledCount = $engine->reconcileRefunds();

        $this->assertEquals(1, $reconciledCount);

        $order->refresh();
        $this->assertEquals('completed', $order->paystack_refund_status);
        $this->assertEquals('rf_reconciled_99', $order->paystack_refund_reference);
    }
}
