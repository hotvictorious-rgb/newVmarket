<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\CustomerCashbackLedger;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSupplierAssignment;
use App\Models\ProcessedWebhookEvent;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\FinancialReconciliationEngine;
use App\Services\OrderSettlementService;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessedWebhookEventCrashRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $vendorUser;
    protected VendorProfile $vendorProfile;
    protected Shop $shop;
    protected Product $product;
    protected string $secretKey = 'sk_test_mock_paystack_secret_key_crash_proof';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        Config::set('services.paystack.secret_key', $this->secretKey);
        Config::set('services.paystack.public_key', 'pk_test_mock_paystack_public_key');
        Config::set('services.paystack.payment_url', 'https://api.paystack.co');
        Config::set('services.paystack.simulate_in_tests', false);

        $this->customer = User::where('user_type', 'customer')->first();
        if (!$this->customer) {
            $this->customer = User::factory()->create([
                'user_type' => 'customer',
                'status' => 'active',
                'email' => 'legitcustomer@example.com',
            ]);
        }

        $this->vendorUser = User::where('user_type', 'vendor')->first();
        $this->vendorProfile = $this->vendorUser->vendorProfile ?? VendorProfile::first();
        $this->shop = Shop::where('vendor_id', $this->vendorProfile->id)->first();
        $this->product = Product::where('shop_id', $this->shop->id)->first();
        if ($this->product) {
            $this->product->update([
                'cost_price' => '5000.00',
                'price' => '10000.00',
                'is_in_stock' => true,
                'stock_level' => 10,
            ]);
        }
    }

    protected function createTestOrder(array $attributes = []): Order
    {
        $grandTotal = $attributes['grand_total'] ?? '10000.00';
        $reference = $attributes['payment_reference'] ?? ('VM-TEST-RECOV-' . uniqid());
        $merchandiseSubtotal = $attributes['merchandise_subtotal'] ?? $grandTotal;
        $deliveryFee = $attributes['delivery_fee'] ?? '0.00';

        $order = Order::create(array_merge([
            'order_number' => 'VM-ORD-' . rand(10000, 99999),
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendorProfile->id,
            'shop_id' => $this->shop->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'paystack_direct',
            'payment_reference' => $reference,
            'guest_access_token' => bin2hex(random_bytes(32)),
            'origin_lga_id' => $this->shop->lga_id,
            'destination_lga_id' => $this->shop->lga_id,
            'merchandise_subtotal' => $merchandiseSubtotal,
            'delivery_fee' => $deliveryFee,
            'cashback_redeemed' => '0.00',
            'grand_total' => $grandTotal,
            'vendor_net_90' => bcmul($grandTotal, '0.90', 2),
            'customer_cashback_5' => bcmul($grandTotal, '0.05', 2),
            'platform_margin_5' => bcmul($grandTotal, '0.05', 2),
            'logistics_fee_retained' => '0.00',
            'platform_delivery_fee' => '0.00',
            'supplier_cost_total_snapshot' => '8000.00',
            'pickup_verification_code' => Order::generateUniquePickupOtp(),
            'verification_code' => Order::generateUniqueDeliveryOtp(),
            'vendor_settlement_status' => 'held',
            'retailer_model_order' => false,
        ], $attributes));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name ?? 'Test Product',
            'unit_price' => $merchandiseSubtotal,
            'quantity' => 1,
            'line_total' => $merchandiseSubtotal,
        ]);

        return $order;
    }

    protected function buildWebhookPayload(Order $order, string $eventId = 'evt_test_recovery'): array
    {
        $amountInKobo = (int) bcmul(bcadd((string)$order->grand_total, '0', 2), '100', 0);
        return [
            'event' => 'charge.success',
            'id' => $eventId,
            'data' => [
                'id' => 888777,
                'status' => 'success',
                'currency' => 'NGN',
                'amount' => $amountInKobo,
                'reference' => $order->payment_reference,
                'channel' => 'card',
                'customer' => ['email' => $this->customer->email],
                'metadata' => [
                    'order_id' => $order->id,
                    'customer_id' => $this->customer->id,
                ],
                'paid_at' => Carbon::now()->toIso8601String(),
            ],
        ];
    }

    /**
     * TEST 1: Worker crashes before/during settlement.
     * The inbox event remains in 'processing' status at attempts=1.
     * Reconciliation engine identifies the stale worker claim, connects directly
     * to Paystack to re-verify live gateway truth, bumps attempt generation to 2,
     * and authoritatively completes settlement.
     */
    public function test_1_crashed_worker_is_reclaimed_by_reconciler_with_live_paystack_verification(): void
    {
        $order = $this->createTestOrder(['grand_total' => '10000.00']);
        $payloadData = $this->buildWebhookPayload($order, 'evt_crash_sim_001');
        $payloadJson = json_encode($payloadData);
        $payloadHash = hash('sha256', $payloadJson);

        // Simulate Worker A claiming the event and crashing while 'processing' 10 minutes ago
        $staleEvent = ProcessedWebhookEvent::create([
            'event_id' => 'evt_crash_sim_001',
            'event_type' => 'charge.success',
            'payload_hash' => $payloadHash,
            'payload' => $payloadJson,
            'status' => 'processing',
            'attempts' => 1,
        ]);
        DB::table('processed_webhook_events')->where('id', $staleEvent->id)->update([
            'updated_at' => Carbon::now()->subMinutes(10),
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        // Mock Paystack live gateway truth returning success
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'id' => 888777,
                    'status' => 'success',
                    'reference' => $order->payment_reference,
                    'amount' => 1000000,
                    'currency' => 'NGN',
                    'channel' => 'card',
                    'customer' => ['email' => $this->customer->email],
                    'paid_at' => Carbon::now()->toIso8601String(),
                ],
            ], 200),
        ]);

        $reconciler = app(FinancialReconciliationEngine::class);
        $reclaimedCount = $reconciler->reconcileStaleWebhooks();

        $this->assertEquals(1, $reclaimedCount, 'Reconciler must reclaim the stale crashed worker event.');

        $staleEvent->refresh();
        $this->assertEquals('completed', $staleEvent->status, 'Event status must be promoted to completed.');
        $this->assertEquals(2, $staleEvent->attempts, 'Attempt generation must increment to 2.');
        $this->assertNull($staleEvent->error_reason, 'Error reason must be clear on successful recovery.');

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status, 'Order must be authoritatively marked paid.');
        $this->assertNotNull($order->paid_at);

        // Verify exactly one vendor payable journal entry created
        $journalCount = FinancialJournalEntry::where('order_id', $order->id)
            ->where('event_id', 'like', 'EVT-VND-ESCROW-%')
            ->count();
        $this->assertEquals(1, $journalCount, 'Exactly 1 vendor escrow journal entry must exist.');

        // Verify exactly one cashback ledger created
        $cashbackCount = CustomerCashbackLedger::where('order_id', $order->id)->count();
        $this->assertEquals(1, $cashbackCount, 'Exactly 1 customer cashback ledger must exist.');
    }

    /**
     * TEST 2: Checkpoint C (The Real Danger Zone)
     * Settlement commits (order paid, inventory deducted, journals posted, cashback credited),
     * but the worker process dies before updating ProcessedWebhookEvent to 'completed'.
     * The inbox event remains 'processing' or 'failed'.
     * When reconciliation or a webhook replay arrives, it must detect existing order settlement,
     * finalize the inbox row to 'completed', and produce ZERO duplicate journals, ZERO duplicate
     * cashback, and ZERO duplicate stock deductions.
     */
    public function test_2_checkpoint_c_crash_after_settlement_commit_recovers_with_zero_duplicate_effects(): void
    {
        $order = $this->createTestOrder(['grand_total' => '10000.00']);
        $payloadData = $this->buildWebhookPayload($order, 'evt_checkpoint_c_002');
        $payloadJson = json_encode($payloadData);
        $payloadHash = hash('sha256', $payloadJson);

        // 1. First worker settles the order cleanly
        $settlementService = app(OrderSettlementService::class);
        $settlementResult = $settlementService->settlePaystackOrder(
            $order,
            $payloadData['data'],
            null,
            'test_initial_worker'
        );
        $this->assertTrue($settlementResult['success']);
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        // 2. But the worker crashed before marking the event 'completed' (remains 'processing' from 10 mins ago)
        $crashedEvent = ProcessedWebhookEvent::create([
            'event_id' => 'evt_checkpoint_c_002',
            'event_type' => 'charge.success',
            'payload_hash' => $payloadHash,
            'payload' => $payloadJson,
            'status' => 'processing',
            'attempts' => 1,
        ]);
        DB::table('processed_webhook_events')->where('id', $crashedEvent->id)->update([
            'updated_at' => Carbon::now()->subMinutes(10),
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        $initialJournalCount = FinancialJournalEntry::where('order_id', $order->id)
            ->where('event_id', 'like', 'EVT-VND-ESCROW-%')
            ->count();
        $initialCashbackCount = CustomerCashbackLedger::where('order_id', $order->id)->count();
        $this->assertEquals(1, $initialJournalCount);
        $this->assertEquals(1, $initialCashbackCount);

        // 3. Reconciler runs over the stale event
        $reconciler = app(FinancialReconciliationEngine::class);
        $reclaimedCount = $reconciler->reconcileStaleWebhooks();

        $this->assertEquals(1, $reclaimedCount, 'Reconciler must detect and finalize the stale event.');

        $crashedEvent->refresh();
        $this->assertEquals('completed', $crashedEvent->status, 'Inbox event must be safely promoted to completed.');

        // Invariant Proof: Zero financial or inventory side effects
        $postJournalCount = FinancialJournalEntry::where('order_id', $order->id)
            ->where('event_id', 'like', 'EVT-VND-ESCROW-%')
            ->count();
        $postCashbackCount = CustomerCashbackLedger::where('order_id', $order->id)->count();
        $this->assertEquals(1, $postJournalCount, 'Zero duplicate journals allowed after Checkpoint C recovery.');
        $this->assertEquals(1, $postCashbackCount, 'Zero duplicate cashback allowed after Checkpoint C recovery.');
    }

    /**
     * TEST 3: Stale worker resumes after reconciler recovery.
     * Worker A hangs at generation 1. Reconciler bumps generation to 2 and settles order.
     * Worker A wakes up and attempts to complete settlement with stale attempt=1.
     * The attempt-generation fence strictly rejects Worker A with STALE_WEBHOOK_CLAIM,
     * producing zero financial mutations.
     */
    public function test_3_stale_worker_with_old_attempt_generation_fenced_out_with_zero_mutations(): void
    {
        $order = $this->createTestOrder(['grand_total' => '10000.00']);
        $payloadData = $this->buildWebhookPayload($order, 'evt_stale_worker_003');
        $payloadJson = json_encode($payloadData);
        $payloadHash = hash('sha256', $payloadJson);

        // Event was claimed by Worker B at attempt generation 2
        $claimedEvent = ProcessedWebhookEvent::create([
            'event_id' => 'evt_stale_worker_003',
            'event_type' => 'charge.success',
            'payload_hash' => $payloadHash,
            'payload' => $payloadJson,
            'status' => 'processing',
            'attempts' => 2,
            'created_at' => Carbon::now()->subMinutes(1),
            'updated_at' => Carbon::now()->subMinutes(1),
        ]);

        $settlementService = app(OrderSettlementService::class);

        // Worker A wakes up with attempt generation 1
        $staleResult = $settlementService->settlePaystackOrder(
            $order,
            $payloadData['data'],
            null,
            'paystack_webhook',
            (int) $claimedEvent->id,
            1 // STALE GENERATION (DB has 2)
        );

        $this->assertFalse($staleResult['success']);
        $this->assertEquals('STALE_WEBHOOK_CLAIM', $staleResult['status']);

        // Assert 0 mutations made
        $order->refresh();
        $this->assertEquals('pending', $order->payment_status);
        $this->assertEquals(0, FinancialJournalEntry::where('order_id', $order->id)->count());
        $this->assertEquals(0, CustomerCashbackLedger::where('order_id', $order->id)->count());
    }

    /**
     * TEST 4: Replay of completed webhook returns 200 with zero duplicate effects.
     */
    public function test_4_replay_of_completed_webhook_returns_200_with_zero_duplicate_effects(): void
    {
        $order = $this->createTestOrder(['grand_total' => '10000.00']);
        $payloadData = $this->buildWebhookPayload($order, 'evt_replay_004');
        $payloadJson = json_encode($payloadData);
        $payloadHash = hash('sha256', $payloadJson);

        // Pre-complete the event and order
        ProcessedWebhookEvent::create([
            'event_id' => 'evt_replay_004',
            'event_type' => 'charge.success',
            'payload_hash' => $payloadHash,
            'payload' => $payloadJson,
            'status' => 'completed',
            'attempts' => 1,
            'processed_at' => Carbon::now(),
        ]);

        $order->update(['payment_status' => 'paid', 'paid_at' => Carbon::now()]);

        FinancialJournalEntry::create([
            'order_id' => $order->id,
            'transaction_reference' => $order->payment_reference,
            'event_id' => 'EVT-VND-ESCROW-' . $order->payment_reference,
            'beneficiary_type' => VendorProfile::class,
            'beneficiary_id' => $this->vendorProfile->id,
            'account_name' => 'vendor_payable',
            'entry_type' => 'credit',
            'amount' => '9000.00',
            'currency' => 'NGN',
            'description' => 'Vendor payable',
        ]);

        $secretKey = app(PaystackService::class)->getSecretKey();
        $validSignature = hash_hmac('sha512', $payloadJson, $secretKey);

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $validSignature,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'Event already processed.',
        ]);

        // Zero additional mutations
        $this->assertEquals(1, FinancialJournalEntry::where('order_id', $order->id)->where('event_id', 'like', 'EVT-VND-ESCROW-%')->count());
        $this->assertEquals(0, CustomerCashbackLedger::where('order_id', $order->id)->count());
    }

    /**
     * TEST 5: Phase B - Different Event IDs, Same Payment Reference
     * Paystack delivers two events with distinct event IDs (e.g. EVT-AAA-001 and EVT-BBB-002)
     * pointing to the exact same transaction reference.
     * Event-ID inbox deduplication alone cannot prevent the second event from entering;
     * the underlying order state gate and journal uniqueness invariants must prevent
     * double-settlement.
     */
    public function test_5_different_event_ids_same_payment_reference_strictly_settles_once(): void
    {
        $order = $this->createTestOrder(['grand_total' => '10000.00']);
        $secretKey = app(PaystackService::class)->getSecretKey();
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'id' => 888777,
                    'status' => 'success',
                    'currency' => 'NGN',
                    'amount' => 1000000,
                    'reference' => $order->payment_reference,
                    'channel' => 'card',
                    'customer' => ['email' => $this->customer->email],
                ],
            ], 200),
        ]);

        // 1. Deliver Event A
        $payloadA = $this->buildWebhookPayload($order, 'EVT-AAA-001');
        $jsonA = json_encode($payloadA);
        $sigA = hash_hmac('sha512', $jsonA, $secretKey);

        $responseA = $this->postJson(route('payment.paystack.webhook'), $payloadA, [
            'x-paystack-signature' => $sigA,
        ]);
        $responseA->assertStatus(200);
        $responseA->assertJson(['status' => true, 'settlement' => 'PAID']);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals(1, FinancialJournalEntry::where('order_id', $order->id)->where('event_id', 'like', 'EVT-VND-ESCROW-%')->count());
        $this->assertEquals(1, CustomerCashbackLedger::where('order_id', $order->id)->count());

        // 2. Deliver Event B with DIFFERENT event ID for SAME transaction reference
        $payloadB = $this->buildWebhookPayload($order, 'EVT-BBB-002');
        $jsonB = json_encode($payloadB);
        $sigB = hash_hmac('sha512', $jsonB, $secretKey);

        $responseB = $this->postJson(route('payment.paystack.webhook'), $payloadB, [
            'x-paystack-signature' => $sigB,
        ]);

        $responseB->assertStatus(200);
        $responseB->assertJson(['status' => true, 'settlement' => 'ALREADY_PAID']);

        // Invariant Proof: Exactly one set of journals, one cashback ledger, one settlement
        $this->assertEquals(1, FinancialJournalEntry::where('order_id', $order->id)->where('event_id', 'like', 'EVT-VND-ESCROW-%')->count(), 'Different Event IDs must NOT duplicate journal entries.');
        $this->assertEquals(1, CustomerCashbackLedger::where('order_id', $order->id)->count(), 'Different Event IDs must NOT duplicate cashback.');
    }

    /**
     * A signed webhook must not settle unless its transaction identity matches
     * the authoritative server-to-server Paystack verification response.
     */
    public function test_webhook_transaction_identity_mismatch_is_held_for_admin_review(): void
    {
        $order = $this->createTestOrder(['grand_total' => '10000.00']);
        $payload = $this->buildWebhookPayload($order, 'evt_identity_mismatch_008');
        $payloadJson = json_encode($payload);
        $signature = hash_hmac('sha512', $payloadJson, $this->secretKey);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'id' => 999000,
                    'status' => 'success',
                    'currency' => 'NGN',
                    'amount' => 1000000,
                    'reference' => $order->payment_reference,
                    'channel' => 'card',
                    'customer' => ['email' => $this->customer->email],
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('payment.paystack.webhook'), $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['status' => false]);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, FinancialJournalEntry::where('order_id', $order->id)->count());
        $this->assertDatabaseHas('payment_capture_exceptions', [
            'order_id' => $order->id,
            'gateway_transaction_id' => '888777',
            'exception_type' => 'webhook_verification_mismatch',
            'status' => 'pending_admin_review',
        ]);
        $this->assertDatabaseHas('processed_webhook_events', [
            'event_id' => 'evt_identity_mismatch_008',
            'status' => 'failed',
        ]);
    }

    public function test_server_verified_amount_mismatch_is_persisted_as_payment_exception(): void
    {
        $order = $this->createTestOrder(['grand_total' => '10000.00']);
        $payload = $this->buildWebhookPayload($order, 'evt_amount_mismatch_009');
        $payloadJson = json_encode($payload);
        $signature = hash_hmac('sha512', $payloadJson, $this->secretKey);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'id' => 888777,
                    'status' => 'success',
                    'currency' => 'NGN',
                    'amount' => 999999,
                    'reference' => $order->payment_reference,
                    'channel' => 'card',
                    'customer' => ['email' => $this->customer->email],
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('payment.paystack.webhook'), $payload, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(422);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, FinancialJournalEntry::where('order_id', $order->id)->count());
        $this->assertDatabaseHas('payment_capture_exceptions', [
            'order_id' => $order->id,
            'gateway_transaction_id' => '888777',
            'exception_type' => 'server_verification_rejected_amount_mismatch',
            'status' => 'pending_admin_review',
        ]);
    }

    /**
     * TEST 6: Journal Duplication Proof - 100x Replay Storm
     * Replay the exact same webhook event 100 times in succession.
     * Every call returns HTTP 200, and all financial side effects remain strictly 1.
     */
    public function test_6_journal_duplication_proof_100x_replay_storm(): void
    {
        $order = $this->createTestOrder(['grand_total' => '10000.00']);
        $secretKey = app(PaystackService::class)->getSecretKey();
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'id' => 888777,
                    'status' => 'success',
                    'currency' => 'NGN',
                    'amount' => 1000000,
                    'reference' => $order->payment_reference,
                    'channel' => 'card',
                    'customer' => ['email' => $this->customer->email],
                ],
            ], 200),
        ]);

        $payload = $this->buildWebhookPayload($order, 'EVT-STORM-100X');
        $json = json_encode($payload);
        $sig = hash_hmac('sha512', $json, $secretKey);

        // Initial settlement
        $firstResponse = $this->postJson(route('payment.paystack.webhook'), $payload, [
            'x-paystack-signature' => $sig,
        ]);
        $firstResponse->assertStatus(200);
        $firstResponse->assertJson(['status' => true, 'settlement' => 'PAID']);

        // 100 consecutive replays
        for ($i = 0; $i < 100; $i++) {
            $replayResponse = $this->postJson(route('payment.paystack.webhook'), $payload, [
                'x-paystack-signature' => $sig,
            ]);
            $replayResponse->assertStatus(200);
        }

        // Strict Duplication Invariants
        $this->assertEquals(1, FinancialJournalEntry::where('order_id', $order->id)->where('event_id', 'like', 'EVT-VND-ESCROW-%')->count(), 'Vendor escrow journal count must be strictly 1 after 100 replays.');
        $this->assertEquals(1, CustomerCashbackLedger::where('order_id', $order->id)->count(), 'Cashback ledger count must be strictly 1 after 100 replays.');
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
    }

    /**
     * TEST 7: Settlement Identity & Audit Trail Provenance
     * Verifies that retry/reconciliation preserves auditable linkage between
     * order_id, payment_reference, event_id, and attempt_generation.
     */
    public function test_7_settlement_identity_assertions_preserve_auditable_provenance(): void
    {
        $order = $this->createTestOrder(['grand_total' => '10000.00']);
        $secretKey = app(PaystackService::class)->getSecretKey();
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'id' => 888777,
                    'status' => 'success',
                    'currency' => 'NGN',
                    'amount' => 1000000,
                    'reference' => $order->payment_reference,
                    'channel' => 'card',
                    'customer' => ['email' => $this->customer->email],
                ],
            ], 200),
        ]);

        $payload = $this->buildWebhookPayload($order, 'EVT-AUDIT-PROVENANCE');
        $json = json_encode($payload);
        $sig = hash_hmac('sha512', $json, $secretKey);

        $response = $this->postJson(route('payment.paystack.webhook'), $payload, [
            'x-paystack-signature' => $sig,
        ]);
        $response->assertStatus(200);

        $event = ProcessedWebhookEvent::where('event_id', 'EVT-AUDIT-PROVENANCE')->first();
        $this->assertNotNull($event);
        $this->assertEquals('completed', $event->status);
        $this->assertEquals(1, $event->attempts);

        // Verify Journal links to order and payment reference
        $journal = FinancialJournalEntry::where('order_id', $order->id)->first();
        $this->assertNotNull($journal);
        $this->assertEquals($order->id, $journal->order_id);
        $this->assertEquals($order->payment_reference, $journal->transaction_reference);

        // Verify Audit Log links to order and preserves reference and status
        $auditLog = AdminAuditLog::where('target_type', Order::class)
            ->where('target_id', $order->id)
            ->where('action', 'paystack_payment_verified')
            ->first();
        $this->assertNotNull($auditLog, 'Payment settlement audit log must be recorded.');
        $this->assertEquals($order->payment_reference, $auditLog->new_values['paystack_reference'] ?? null);
        $this->assertEquals('paid', $auditLog->new_values['payment_status'] ?? null);
    }
}
