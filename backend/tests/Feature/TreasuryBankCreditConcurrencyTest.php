<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Lga;
use App\Models\Order;
use App\Models\RetailerSupplierPayoutRequest;
use App\Models\State;
use App\Models\TreasuryBankCreditBatch;
use App\Models\TreasuryBankCreditBatchAllocation;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TreasuryBankCreditConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function runDatabaseMigrations()
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true)) {
            $this->artisan('migrate:fresh');
            $this->beforeApplicationDestroyed(function () {
                $this->artisan('migrate:rollback');
            });
        }
    }

    public function test_two_processes_cannot_overreserve_one_treasury_bank_credit_batch(): void
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('True multi-process row-lock proof runs against MySQL or PostgreSQL only.');
        }

        $adminOne = User::factory()->create(['user_type' => 'super_admin', 'status' => 'active']);
        $adminTwo = User::factory()->create(['user_type' => 'super_admin', 'status' => 'active']);
        $customer = User::factory()->create(['user_type' => 'customer', 'status' => 'active']);
        $supplierUser = User::factory()->create(['user_type' => 'vendor', 'status' => 'active']);
        $supplier = VendorProfile::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Concurrent Treasury Supplier',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
            'bank_name' => 'Zenith Bank',
            'bank_code' => '057',
            'account_number' => '1012345678',
            'account_name' => 'Concurrent Treasury Supplier',
            'bank_updated_at' => now()->subDays(5),
        ]);
        $country = Country::firstOrCreate(['iso_code' => 'NG'], ['name' => 'Nigeria', 'phone_code' => '+234', 'currency_code' => 'NGN', 'is_active' => true]);
        $state = State::firstOrCreate(['name' => 'Akwa Ibom'], ['country_id' => $country->id, 'state_code' => 'AK', 'is_active' => true]);
        $uyo = Lga::firstOrCreate(['name' => 'Uyo', 'state_id' => $state->id], ['is_active' => true]);

        $batch = TreasuryBankCreditBatch::create([
            'gateway_provider' => 'paystack',
            'gateway_settlement_reference' => 'CONCURRENT-SETTLE-' . uniqid(),
            'bank_credit_reference' => 'CONCURRENT-BANK-' . uniqid(),
            'corporate_account_last4' => '4321',
            'gross_collection_amount_kobo' => 1000000,
            'provider_fee_amount_kobo' => 0,
            'net_credit_amount_kobo' => 1000000,
            'currency' => 'NGN',
            'statement_value_date' => now()->toDateString(),
            'statement_evidence_reference' => 'verified corporate statement row for concurrency test',
            'reconciliation_notes' => 'Independent reviewers matched the settlement and corporate bank credit.',
            'status' => 'reconciled',
            'prepared_by_admin_id' => $adminOne->id,
            'confirmed_by_admin_id' => $adminTwo->id,
            'prepared_at' => now()->subMinute(),
            'confirmed_at' => now(),
            'idempotency_key' => 'CONCURRENT-TREASURY-' . uniqid(),
        ]);

        $order = Order::create([
            'order_number' => 'VM-CONCURRENT-TREASURY-' . uniqid(),
            'customer_id' => $customer->id,
            'vendor_id' => null,
            'shop_id' => null,
            'origin_lga_id' => $uyo->id,
            'destination_lga_id' => $uyo->id,
            'retailer_model_order' => true,
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'sourcing_status' => 'awaiting_supplier_confirmation',
            'fulfillment_type' => 'doorstep_delivery',
            'delivery_fee' => '0.00',
            'merchandise_subtotal' => '10000.00',
            'grand_total' => '10000.00',
            'vendor_net_90' => '0.00',
            'customer_cashback_5' => '0.00',
            'platform_margin_5' => '0.00',
            'payment_reference' => 'CONCURRENT-PAY-' . uniqid(),
            'paystack_transaction_id' => 'CONCURRENT-TX-' . uniqid(),
        ]);
        TreasuryBankCreditBatchAllocation::create([
            'treasury_bank_credit_batch_id' => $batch->id,
            'order_id' => $order->id,
            'gateway_transaction_id' => $order->paystack_transaction_id,
            'payment_reference' => $order->payment_reference,
            'amount_kobo' => 1000000,
            'currency' => 'NGN',
            'idempotency_key' => 'CONCURRENT-ALLOC-' . uniqid(),
            'added_by_admin_id' => $adminOne->id,
            'added_at' => now(),
        ]);

        $payouts = [];
        foreach ([1, 2] as $n) {
            $payouts[] = RetailerSupplierPayoutRequest::create([
                'supplier_vendor_id' => $supplier->id,
                'amount' => '6000.00',
                'status' => 'pending',
                'gateway_status' => 'not_submitted',
                'bank_name' => 'Zenith Bank',
                'bank_code' => '057',
                'account_number' => '1012345678',
                'account_name' => 'Concurrent Treasury Supplier',
            ]);
        }

        $processes = [];
        foreach ($payouts as $payout) {
            $process = new Process([PHP_BINARY, 'artisan', 'treasury:reserve-supplier-payout', (string) $payout->id], base_path());
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        }

        $reservedCount = RetailerSupplierPayoutRequest::where('treasury_bank_credit_batch_id', $batch->id)->count();
        $this->assertSame(1, $reservedCount, 'Two simultaneous ₦6,000 reservations must not overreserve a ₦10,000 reconciled bank-credit batch.');
        $reservedAmountKobo = 0;
        foreach (RetailerSupplierPayoutRequest::where('treasury_bank_credit_batch_id', $batch->id)
            ->whereIn('status', ['pending', 'processing', 'uncertain', 'paid'])
            ->get(['amount']) as $reservedReq) {
            $reservedAmountKobo += (int) bcmul((string) $reservedReq->amount, '100', 0);
        }
        $this->assertSame(600000, $reservedAmountKobo);

        // This class uses committed fixtures so child processes can see them.
        // Remove the FK-linked rows before DatabaseMigrations rolls the schema back.
        RetailerSupplierPayoutRequest::whereIn('id', collect($payouts)->pluck('id'))->delete();
        TreasuryBankCreditBatchAllocation::where('treasury_bank_credit_batch_id', $batch->id)->delete();
        Order::whereKey($order->id)->delete();
        TreasuryBankCreditBatch::whereKey($batch->id)->delete();
    }
}
