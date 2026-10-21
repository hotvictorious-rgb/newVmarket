<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\CustomerCashbackLedger;
use App\Models\Lga;
use App\Models\Order;
use App\Models\ProcessedWebhookEvent;
use App\Models\FinancialJournalEntry;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\OrderSettlementService;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackPaymentHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $customer2;
    protected User $vendorUser;
    protected VendorProfile $vendorProfile;
    protected Shop $shop;
    protected Product $product;
    protected string $secretKey = 'sk_test_mock_paystack_secret_key_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        Config::set('services.paystack.secret_key', $this->secretKey);
        Config::set('services.paystack.public_key', 'pk_test_mock_paystack_public_key');
        Config::set('services.paystack.payment_url', 'https://api.paystack.co');

        $this->customer = User::where('user_type', 'customer')->first();
        if (!$this->customer) {
            $this->customer = User::factory()->create([
                'user_type' => 'customer',
                'status' => 'active',
                'email' => 'legitcustomer@example.com',
            ]);
        }

        $this->customer2 = User::factory()->create([
            'user_type' => 'customer',
            'status' => 'active',
            'email' => 'attacker@example.com',
        ]);

        $this->vendorUser = User::where('user_type', 'vendor')->first();
        $this->vendorProfile = $this->vendorUser->vendorProfile ?? VendorProfile::first();
        $this->shop = Shop::where('vendor_id', $this->vendorProfile->id)->first();
        $this->product = Product::where('shop_id', $this->shop->id)->first();
        if ($this->product && $this->product->cost_price === null) {
            $this->product->update(['cost_price' => '5000.00']);
        }
    }

    /**
     * Helper to create a realistic test order with exact BCMath splits.
     */
    protected function createTestOrder(array $attributes = []): Order
    {
        $grandTotal = $attributes['grand_total'] ?? '12500.50';
        $reference = $attributes['payment_reference'] ?? ('VM-TEST-' . uniqid());

        return Order::create(array_merge([
            'order_number' => 'VM-ORD-' . rand(1000, 9999),
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
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '2500.50',
            'cashback_redeemed' => '0.00',
            'grand_total' => $grandTotal,
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '2250.45',
            'platform_delivery_fee' => '250.05',
            'pickup_verification_code' => Order::generateUniquePickupOtp(),
            'verification_code' => Order::generateUniqueDeliveryOtp(),
            'vendor_settlement_status' => 'held',
        ], $attributes));
    }

    /**
     * PROOF 1: Web Storefront Checkout creates order strictly in 'pending' status
     * and redirects to Paystack without prematurely awarding cashback or marking paid.
     */
    public function test_checkout_strictly_creates_order_in_pending_status_and_redirects_to_paystack(): void
    {
        $response = $this->actingAs($this->customer)->post(route('checkout.process'), [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => $this->customer->name,
            'customer_phone' => '08012345678',
            'shop_id' => $this->shop->id,
            'destination_lga_id' => $this->shop->lga_id,
            'delivery_address' => '12 Ikot Ekpene Road, Uyo',
        ]);

        $response->assertRedirect();

        $order = Order::where('customer_id', $this->customer->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals('pending', $order->payment_status, 'Order payment_status MUST be pending at checkout creation.');
        $this->assertEquals('pending', $order->order_status, 'Order order_status MUST be pending before gateway verification.');
        $this->assertNull($order->paid_at, 'paid_at must be null before Paystack verification.');
        $this->assertTrue($this->product->fresh()->is_in_stock, 'Product remains In Stock in the two-status model.');
        $this->assertNotNull($order->expires_at, 'Order must establish a 30-minute payment safety reservation window.');

        // Prove zero premature cashback allocation before payment
        $cashbackCount = CustomerCashbackLedger::where('order_id', $order->id)->count();
        $this->assertEquals(0, $cashbackCount, 'Cashback must NOT be credited before Paystack payment confirmation.');
    }

    /**
     * PROOF 2: Mathematical Impossibility of Marking Order Paid when Integer Kobo Mismatches
     * (Underpayment or Overpayment by even 1 kobo is permanently rejected).
     */
    public function test_it_is_mathematically_impossible_to_mark_order_paid_if_kobo_amount_is_off_by_even_one_kobo(): void
    {
        // ₦12,500.50 => exactly 1,250,050 Kobo
        $order = $this->createTestOrder(['grand_total' => '12500.50']);
        $settlementService = app(OrderSettlementService::class);

        // Case A: Underpayment by 1 single kobo (1,250,049 Kobo)
        $underpaidPayload = [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 1250049,
            'reference' => $order->payment_reference,
            'customer' => ['email' => $this->customer->email],
        ];

        $resultA = $settlementService->settlePaystackOrder($order, $underpaidPayload);
        $this->assertFalse($resultA['success']);
        $this->assertEquals('AMOUNT_MISMATCH', $resultA['status']);
        $this->assertEquals('pending', $order->fresh()->payment_status);

        // Case B: Overpayment by 1 single kobo (1,250,051 Kobo)
        $overpaidPayload = [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 1250051,
            'reference' => $order->payment_reference,
            'customer' => ['email' => $this->customer->email],
        ];

        $resultB = $settlementService->settlePaystackOrder($order, $overpaidPayload);
        $this->assertFalse($resultB['success']);
        $this->assertEquals('AMOUNT_MISMATCH', $resultB['status']);
        $this->assertEquals('pending', $order->fresh()->payment_status);

        // Case C: Drastic underpayment (e.g. 100 kobo / ₦1.00)
        $pennyPayload = [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 100,
            'reference' => $order->payment_reference,
            'customer' => ['email' => $this->customer->email],
        ];

        $resultC = $settlementService->settlePaystackOrder($order, $pennyPayload);
        $this->assertFalse($resultC['success']);
        $this->assertEquals('AMOUNT_MISMATCH', $resultC['status']);
        $this->assertEquals('pending', $order->fresh()->payment_status);
    }

    /**
     * PROOF 3: Currency Invariant Enforcement
     * Transactions in currencies other than NGN (e.g. USD, EUR, GHS) are rejected.
     */
    public function test_currency_mismatch_is_strictly_rejected(): void
    {
        $order = $this->createTestOrder(['grand_total' => '1000.00']);
        $settlementService = app(OrderSettlementService::class);

        $usdPayload = [
            'status' => 'success',
            'currency' => 'USD',
            'amount' => 100000,
            'reference' => $order->payment_reference,
            'customer' => ['email' => $this->customer->email],
        ];

        $result = $settlementService->settlePaystackOrder($order, $usdPayload);
        $this->assertFalse($result['success']);
        $this->assertEquals('CURRENCY_MISMATCH', $result['status']);
        $this->assertEquals('pending', $order->fresh()->payment_status);
    }

    /**
     * PROOF 4: Non-Success Status Invariant Enforcement
     * Only gateway status 'success' can trigger settlement. Statuses like 'failed', 'abandoned',
     * 'pending', or 'queued' are permanently rejected.
     */
    public function test_non_success_gateway_status_is_strictly_rejected(): void
    {
        $order = $this->createTestOrder(['grand_total' => '5000.00']);
        $settlementService = app(OrderSettlementService::class);

        foreach (['failed', 'abandoned', 'pending', 'reversed'] as $badStatus) {
            $payload = [
                'status' => $badStatus,
                'currency' => 'NGN',
                'amount' => 500000,
                'reference' => $order->payment_reference,
                'customer' => ['email' => $this->customer->email],
            ];

            $result = $settlementService->settlePaystackOrder($order, $payload);
            $this->assertFalse($result['success'], "Status '{$badStatus}' must be rejected.");
            $this->assertEquals('STATUS_NOT_SUCCESS', $result['status']);
            $this->assertEquals('pending', $order->fresh()->payment_status);
        }
    }

    /**
     * PROOF 5: Customer Identity Binding ("it must be the user")
     * If the payer email reported by Paystack does not match the order customer email,
     * the transaction is rejected to prevent payment credential hijack.
     */
    public function test_customer_identity_binding_rejects_mismatched_payer_email(): void
    {
        $order = $this->createTestOrder(['grand_total' => '3000.00']);
        $settlementService = app(OrderSettlementService::class);

        $mismatchedPayerPayload = [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 300000,
            'reference' => $order->payment_reference,
            'customer' => ['email' => 'some_stranger@random.com'], // Mismatch!
        ];

        $result = $settlementService->settlePaystackOrder($order, $mismatchedPayerPayload);
        $this->assertFalse($result['success']);
        $this->assertEquals('CUSTOMER_MISMATCH', $result['status']);
        $this->assertEquals('pending', $order->fresh()->payment_status);
    }

    /**
     * PROOF 6: IDOR Protection on Verification Actors
     * An authenticated user cannot trigger payment settlement for an order belonging to another customer.
     */
    public function test_idor_defense_blocks_unauthorized_user_from_triggering_settlement_on_another_customer_order(): void
    {
        $order = $this->createTestOrder(['grand_total' => '4000.00']);
        $settlementService = app(OrderSettlementService::class);

        $validPayload = [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 400000,
            'reference' => $order->payment_reference,
            'customer' => ['email' => $this->customer->email],
        ];

        // Customer 2 attempts to settle Customer 1's order
        $result = $settlementService->settlePaystackOrder($order, $validPayload, $this->customer2);
        $this->assertFalse($result['success']);
        $this->assertEquals('UNAUTHORIZED', $result['status']);
        $this->assertEquals('pending', $order->fresh()->payment_status);
    }

    /**
     * PROOF 7: Gateway Reference Equality Invariant
     * Paystack reference must match order payment_reference via timing-safe hash_equals.
     */
    public function test_forged_reference_not_matching_order_record_is_strictly_rejected(): void
    {
        $order = $this->createTestOrder(['grand_total' => '2500.00']);
        $settlementService = app(OrderSettlementService::class);

        $forgedReferencePayload = [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 250000,
            'reference' => 'FORGED-REF-' . uniqid(),
            'customer' => ['email' => $this->customer->email],
        ];

        $result = $settlementService->settlePaystackOrder($order, $forgedReferencePayload);
        $this->assertFalse($result['success']);
        $this->assertEquals('REFERENCE_MISMATCH', $result['status']);
        $this->assertEquals('pending', $order->fresh()->payment_status);
    }

    /**
     * PROOF 8: Strict Webhook HMAC-SHA512 Cryptographic Signature Verification
     * Webhooks with missing, forged, or altered signatures are immediately rejected with HTTP 401.
     */
    public function test_webhook_strictly_rejects_missing_or_invalid_hmac_sha512_cryptographic_signature(): void
    {
        $order = $this->createTestOrder(['grand_total' => '7500.00']);

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'currency' => 'NGN',
                'amount' => 750000,
                'reference' => $order->payment_reference,
                'customer' => ['email' => $this->customer->email],
            ],
        ]);

        // Sub-test A: No signature header -> 401
        $resA = $this->postJson(route('payment.paystack.webhook'), json_decode($payload, true));
        $resA->assertStatus(401);

        // Sub-test B: Forged / tampered signature -> 401
        $resB = $this->postJson(route('payment.paystack.webhook'), json_decode($payload, true), [
            'x-paystack-signature' => 'invalid_forged_signature_hex_code_1234567890abcdef',
        ]);
        $resB->assertStatus(401);

        // Verify order remains completely pending
        $this->assertEquals('pending', $order->fresh()->payment_status);
    }

    /**
     * PROOF 9: Valid HMAC-SHA512 Signed Webhook Settles Order & Credits Cashback Atomically
     */
    public function test_valid_webhook_with_hmac_sha512_signature_settles_order_and_credits_cashback_atomically(): void
    {
        $order = $this->createTestOrder(['grand_total' => '7500.00']);

        $payloadData = [
            'event' => 'charge.success',
            'data' => [
                'id' => 998877,
                'status' => 'success',
                'currency' => 'NGN',
                'amount' => 750000,
                'reference' => $order->payment_reference,
                'channel' => 'card',
                'customer' => ['email' => $this->customer->email],
                'metadata' => [
                    'order_id' => $order->id,
                    'customer_id' => $this->customer->id,
                ],
            ],
        ];

        $payloadJson = json_encode($payloadData);
        $secretKey = app(PaystackService::class)->getSecretKey();
        $validSignature = hash_hmac('sha512', $payloadJson, $secretKey);

        $response = $this->postJson(route('payment.paystack.webhook'), $payloadData, [
            'x-paystack-signature' => $validSignature,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'settlement' => 'PAID',
        ]);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('confirmed', $order->order_status);
        $this->assertNotNull($order->paid_at);
        $this->assertEquals('card', $order->payment_channel);
        $this->assertEquals('998877', $order->paystack_transaction_id);

        // Cashback liability is recorded at payment, but no maturity/expiry clock
        // starts until verified fulfilment establishes the customer claim-window expiry.
        $cashback = CustomerCashbackLedger::where('order_id', $order->id)->first();
        $this->assertNotNull($cashback);
        $this->assertEquals(0, bccomp((string)$cashback->amount, '500.00', 2));
        $this->assertEquals('pending_maturation', $cashback->status);
        $this->assertNull($cashback->matures_at);
        $this->assertNull($cashback->expires_at);

        // Assert Immutable Audit Log entry recorded
        $auditLog = AdminAuditLog::where('target_id', $order->id)
            ->where('action', 'paystack_payment_verified')
            ->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals('paystack_webhook', $auditLog->new_values['channel_source']);
    }

    /**
     * PROOF 10: Idempotency & Replay Attack Defense
     * Repeated webhook deliveries or callback calls for the same reference return ALREADY_PAID
     * without duplicating cashback or corrupting state.
     */
    public function test_idempotency_guard_prevents_replay_attacks_duplicate_cashback_and_duplicate_settlements(): void
    {
        $order = $this->createTestOrder(['grand_total' => '8000.00']);
        $settlementService = app(OrderSettlementService::class);

        $validPayload = [
            'id' => 112233,
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => 800000,
            'reference' => $order->payment_reference,
            'channel' => 'bank_transfer',
            'customer' => ['email' => $this->customer->email],
        ];

        // Delivery 1: Fresh settlement
        $res1 = $settlementService->settlePaystackOrder($order, $validPayload);
        $this->assertTrue($res1['success']);
        $this->assertEquals('PAID', $res1['status']);
        $this->assertFalse($res1['is_replayed']);
        $this->assertEquals(1, CustomerCashbackLedger::where('order_id', $order->id)->count());

        // Delivery 2: Replay attack / duplicate delivery
        $res2 = $settlementService->settlePaystackOrder($order, $validPayload);
        $this->assertTrue($res2['success']);
        $this->assertEquals('ALREADY_PAID', $res2['status']);
        $this->assertTrue($res2['is_replayed']);
        $this->assertEquals(1, CustomerCashbackLedger::where('order_id', $order->id)->count(), 'Cashback MUST NOT be duplicated on replay.');

        // Delivery 3: Another replay
        $res3 = $settlementService->settlePaystackOrder($order, $validPayload);
        $this->assertTrue($res3['success']);
        $this->assertEquals('ALREADY_PAID', $res3['status']);
        $this->assertTrue($res3['is_replayed']);
        $this->assertEquals(1, CustomerCashbackLedger::where('order_id', $order->id)->count());
    }

    /**
     * Regression proof: gateway response validation is exercised over the HTTP client,
     * rather than short-circuited by the test-only simulated-success path.
     */
    public function test_gateway_verification_rejects_tampered_status_amount_currency_and_reference(): void
    {
        Config::set('services.paystack.simulate_in_tests', false);

        $cases = [
            'status' => ['status' => 'pending', 'currency' => 'NGN', 'amount' => 1500000],
            'amount' => ['status' => 'success', 'currency' => 'NGN', 'amount' => 1499999],
            'currency' => ['status' => 'success', 'currency' => 'USD', 'amount' => 1500000],
            'reference' => ['status' => 'success', 'currency' => 'NGN', 'amount' => 1500000, 'reference' => 'VM-FORGED-REFERENCE'],
        ];
        $responsePayload = [];

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => function ($request) use (&$responsePayload) {
                return Http::response([
                    'status' => true,
                    'message' => 'Verification response',
                    'data' => $responsePayload,
                ], 200);
            },
        ]);

        foreach ($cases as $field => $gatewayData) {
            $order = $this->createTestOrder([
                'grand_total' => '15000.00',
                'payment_reference' => 'VM-VERIFY-' . strtoupper($field) . '-' . uniqid(),
            ]);

            $responsePayload = array_merge([
                'id' => 554433,
                'status' => $gatewayData['status'],
                'currency' => $gatewayData['currency'],
                'amount' => $gatewayData['amount'],
                'reference' => $gatewayData['reference'] ?? $order->payment_reference,
                'channel' => 'card',
                'customer' => ['email' => $this->customer->email],
            ], []);

            $result = app(PaystackService::class)->verifyTransaction($order->payment_reference, $order);

            $expectedCode = match ($field) {
                'status' => 'STATUS_NOT_SUCCESS',
                'amount' => 'AMOUNT_MISMATCH',
                'currency' => 'CURRENCY_MISMATCH',
                'reference' => 'REFERENCE_MISMATCH',
            };

            $this->assertFalse($result['success'], "Tampered {$field} must not verify.");
            $this->assertSame($expectedCode, $result['code'], "Tampered {$field} should fail with its specific validation code.");
        }

        Http::assertSentCount(4);
    }

    /**
     * PROOF 11: Mobile / SPA Authenticated Payment Verification Endpoint
     * Verifies server-to-server with Paystack and settles order atomically.
     */
    public function test_authenticated_api_verify_payment_endpoint_authoritatively_settles_order(): void
    {
        // Force the server-to-server verification path; do not accept the test-only simulated success.
        Config::set('services.paystack.simulate_in_tests', false);
        $order = $this->createTestOrder(['grand_total' => '15000.00']);

        // Mock Paystack server-to-server verify response
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'id' => 554433,
                    'status' => 'success',
                    'currency' => 'NGN',
                    'amount' => 1500000, // 15,000.00 * 100
                    'reference' => $order->payment_reference,
                    'channel' => 'card',
                    'gateway_response' => 'Successful',
                    'paid_at' => now()->toIso8601String(),
                    'customer' => ['email' => $this->customer->email],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/customer/orders/verify-payment', [
            'order_number' => $order->order_number,
            'reference' => $order->payment_reference,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'settlement' => 'PAID',
        ]);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('confirmed', $order->order_status);
    }

    /**
     * PROOF 12: API Verify Payment strictly rejects cross-customer unauthorized access
     */
    public function test_api_verify_payment_strictly_enforces_exact_kobo_and_idor_protection(): void
    {
        $order = $this->createTestOrder(['grand_total' => '5000.00']);

        // Customer 2 attempts to call verify-payment on Customer 1's order
        $response = $this->actingAs($this->customer2, 'sanctum')->postJson('/api/v1/customer/orders/verify-payment', [
            'order_number' => $order->order_number,
            'reference' => $order->payment_reference,
        ]);

        // Must return 404 / unauthorized because order does not belong to Customer 2
        $response->assertStatus(404);
        $this->assertEquals('pending', $order->fresh()->payment_status);
    }
    public function test_stale_charge_success_generation_cannot_settle_order_or_post_journals(): void
    {
        $order = $this->createTestOrder([
            'order_number' => 'VM-STALE-WEBHOOK-SETTLEMENT-01',
            'payment_reference' => 'VM-STALE-WEBHOOK-SETTLEMENT-REF-01',
        ]);
        $event = ProcessedWebhookEvent::create([
            'event_id' => 'evt_charge_stale_generation_01',
            'event_type' => 'charge.success',
            'payload_hash' => hash('sha256', '{"event":"charge.success"}'),
            'payload' => '{"event":"charge.success"}',
            'status' => 'processing',
            'attempts' => 2,
        ]);
        $journalCountBefore = FinancialJournalEntry::count();

        $result = app(OrderSettlementService::class)->settlePaystackOrder(
            $order,
            [
                'reference' => 'VM-STALE-WEBHOOK-SETTLEMENT-REF-01',
                'status' => 'success',
                'amount' => 1250050,
                'currency' => 'NGN',
            ],
            null,
            'paystack_webhook',
            (int) $event->id,
            1
        );

        $this->assertFalse($result['success']);
        $this->assertSame('STALE_WEBHOOK_CLAIM', $result['status']);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('held', $order->fresh()->vendor_settlement_status);
        $this->assertSame(2, (int) $event->fresh()->attempts);
        $this->assertSame('processing', $event->fresh()->status);
        $this->assertSame($journalCountBefore, FinancialJournalEntry::count());
    }

}
