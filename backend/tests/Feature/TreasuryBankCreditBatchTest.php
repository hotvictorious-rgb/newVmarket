<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Lga;
use App\Models\Order;
use App\Models\RetailerSupplierPayoutRequest;
use App\Models\State;
use App\Models\TreasuryBankCreditBatch;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\RetailerSupplierPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryBankCreditBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $adminOne;
    private User $adminTwo;
    private VendorProfile $supplier;
    private User $customer;
    private Lga $uyoLga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminOne = User::factory()->create(['user_type' => 'super_admin', 'status' => 'active']);
        $this->adminTwo = User::factory()->create(['user_type' => 'super_admin', 'status' => 'active']);
        $country = Country::firstOrCreate(['iso_code' => 'NG'], ['name' => 'Nigeria', 'phone_code' => '+234', 'currency_code' => 'NGN', 'is_active' => true]);
        $state = State::firstOrCreate(['name' => 'Akwa Ibom'], ['country_id' => $country->id, 'state_code' => 'AK', 'is_active' => true]);
        $this->uyoLga = Lga::firstOrCreate(['name' => 'Uyo', 'state_id' => $state->id], ['is_active' => true]);
        $this->customer = User::factory()->create(['user_type' => 'customer', 'status' => 'active']);
        $supplierUser = User::factory()->create(['user_type' => 'vendor', 'status' => 'active']);
        $this->supplier = VendorProfile::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Treasury Gate Test Supplier',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
            'bank_name' => 'Zenith Bank',
            'bank_code' => '057',
            'account_number' => '1012345678',
            'account_name' => 'Treasury Gate Test Supplier',
            'bank_updated_at' => now()->subDays(5),
        ]);
    }

    private function createPayout(string $amount): RetailerSupplierPayoutRequest
    {
        return RetailerSupplierPayoutRequest::create([
            'supplier_vendor_id' => $this->supplier->id,
            'amount' => $amount,
            'status' => 'pending',
            'gateway_status' => 'not_submitted',
            'bank_name' => 'Zenith Bank',
            'bank_code' => '057',
            'account_number' => '1012345678',
            'account_name' => 'Treasury Gate Test Supplier',
        ]);
    }

    private function createReconciledBatch(int $amountKobo): TreasuryBankCreditBatch
    {
        return TreasuryBankCreditBatch::create([
            'gateway_provider' => 'paystack',
            'gateway_settlement_reference' => 'SETTLE-' . uniqid(),
            'bank_credit_reference' => 'BANK-CREDIT-' . uniqid(),
            'corporate_account_last4' => '4321',
            'net_credit_amount_kobo' => $amountKobo,
            'currency' => 'NGN',
            'statement_value_date' => now()->toDateString(),
            'statement_evidence_reference' => 'statement page 1 / transaction row verified',
            'reconciliation_notes' => 'Settlement and corporate bank credit matched by independent reviewers.',
            'status' => 'reconciled',
            'prepared_by_admin_id' => $this->adminOne->id,
            'confirmed_by_admin_id' => $this->adminTwo->id,
            'prepared_at' => now()->subMinute(),
            'confirmed_at' => now(),
            'idempotency_key' => 'TREASURY-' . uniqid(),
        ]);
    }

    public function test_supplier_payout_fails_closed_without_dual_reviewed_bank_credit(): void
    {
        $payout = $this->createPayout('5000.00');

        $result = app(RetailerSupplierPayoutService::class)->reserveTreasuryBankCreditBatch($payout->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No dual-reviewed corporate bank-credit batch', $result['message']);
        $this->assertNull($payout->fresh()->treasury_bank_credit_batch_id);
    }

    public function test_bank_credit_capacity_is_reserved_once_and_never_overdrawn_by_concurrent_payouts(): void
    {
        $batch = $this->createReconciledBatch(1000000); // ₦10,000.00
        $first = $this->createPayout('6000.00');
        $second = $this->createPayout('5000.00');

        $firstResult = app(RetailerSupplierPayoutService::class)->reserveTreasuryBankCreditBatch($first->id);
        $this->assertTrue($firstResult['success'], $firstResult['message'] ?? '');
        $this->assertSame((int) $batch->id, (int) $first->fresh()->treasury_bank_credit_batch_id);

        $replay = app(RetailerSupplierPayoutService::class)->reserveTreasuryBankCreditBatch($first->id);
        $this->assertTrue($replay['success'], $replay['message'] ?? '');
        $this->assertSame((int) $batch->id, (int) $first->fresh()->treasury_bank_credit_batch_id);

        $secondResult = app(RetailerSupplierPayoutService::class)->reserveTreasuryBankCreditBatch($second->id);
        $this->assertFalse($secondResult['success']);
        $this->assertStringContainsString('does not have enough unreserved', $secondResult['message']);
        $this->assertNull($second->fresh()->treasury_bank_credit_batch_id);
    }

    public function test_treasury_bank_credit_batch_requires_second_admin_confirmation(): void
    {
        $this->actingAs($this->adminOne)->post(route('admin.treasury-bank-credit-batches.prepare'), [
            'gateway_provider' => 'paystack',
            'gateway_settlement_reference' => 'SETTLE-TEST-DUAL-001',
            'bank_credit_reference' => 'BANK-TEST-DUAL-001',
            'corporate_account_last4' => '4321',
            'gross_collection_amount_kobo' => 5000000,
            'provider_fee_amount_kobo' => 50000,
            'net_credit_amount_kobo' => 4950000,
            'currency' => 'NGN',
            'statement_value_date' => now()->toDateString(),
            'statement_evidence_reference' => 'Bank statement page 2 row 17',
            'reconciliation_notes' => 'Settlement statement and matching corporate bank credit have been located.',
        ])->assertRedirect()->assertSessionHas('success');

        $batch = TreasuryBankCreditBatch::where('gateway_settlement_reference', 'SETTLE-TEST-DUAL-001')->firstOrFail();
        $this->assertSame('pending_second_review', $batch->status);

        $this->actingAs($this->adminOne)->post(route('admin.treasury-bank-credit-batches.confirm', $batch->id), [
            'confirmation_notes' => 'Same preparer must not be allowed to approve this batch.',
        ])->assertSessionHasErrors('confirmation_notes');
        $this->assertSame('pending_second_review', $batch->fresh()->status);

        $order = Order::create([
            'order_number' => 'VM-TREASURY-' . uniqid(),
            'customer_id' => $this->customer->id,
            'vendor_id' => null,
            'shop_id' => null,
            'origin_lga_id' => $this->uyoLga->id,
            'destination_lga_id' => $this->uyoLga->id,
            'retailer_model_order' => true,
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'sourcing_status' => 'awaiting_supplier_confirmation',
            'fulfillment_type' => 'doorstep_delivery',
            'delivery_fee' => '0.00',
            'merchandise_subtotal' => '50000.00',
            'grand_total' => '50000.00',
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '0.00',
            'platform_margin_5' => '0.00',
            'payment_reference' => 'PAY-TREASURY-001',
            'paystack_transaction_id' => 'TX-TREASURY-001',
        ]);
        $this->actingAs($this->adminOne)->post(route('admin.treasury-bank-credit-batches.allocations.store', $batch->id), [
            'order_id' => $order->id,
            'gateway_transaction_id' => 'TX-TREASURY-001',
            'payment_reference' => 'PAY-TREASURY-001',
            'amount_kobo' => 5000000,
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($this->adminTwo)->post(route('admin.treasury-bank-credit-batches.confirm', $batch->id), [
            'confirmation_notes' => 'Independent review confirms the bank credit and settlement references match.',
        ])->assertRedirect()->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame('reconciled', $batch->status);
        $this->assertSame((int) $this->adminTwo->id, (int) $batch->confirmed_by_admin_id);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'treasury_bank_credit_batch_reconciled',
            'target_type' => TreasuryBankCreditBatch::class,
            'target_id' => $batch->id,
        ]);
    }

}
