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

        // Zero order allocations created prematurely
        $this->assertEquals(0, VendorPayoutAllocation::where('vendor_payout_request_id', $payout->id)->count());

        // Zero financial journal entries created prematurely
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
            'paystack_refund_status' => null,
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
}
