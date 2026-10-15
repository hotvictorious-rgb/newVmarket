<?php

namespace Tests\Feature;

use App\Models\LogisticsCompanyProfile;
use App\Models\LogisticsPayoutRequest;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsCorporatePayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test 1: Logistics Company can configure corporate bank account and triggers 48h cooldown.
     */
    public function test_logistics_company_bank_update_and_48h_cooldown(): void
    {
        $logisticsUser = User::where('user_type', 'logistics_company')->first();
        $company = LogisticsCompanyProfile::where('user_id', $logisticsUser->id)->first();

        // Update bank account
        $response = $this->actingAs($logisticsUser)->post(route('logistics.bank.update'), [
            'bank_name' => 'United Bank for Africa',
            'bank_code' => '033',
            'account_number' => '2098765432',
            'account_name' => 'Victorious Express Corporate',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status');

        $company->refresh();
        $this->assertEquals('United Bank for Africa', $company->bank_name);
        $this->assertEquals('2098765432', $company->account_number);
        $this->assertTrue($company->isWithinBankCooldown());
    }

    /**
     * Test 2: Withdrawal request is strictly BLOCKED during 48-hour bank cooldown.
     */
    public function test_withdrawal_is_blocked_during_48h_cooldown(): void
    {
        $logisticsUser = User::where('user_type', 'logistics_company')->first();
        $company = LogisticsCompanyProfile::where('user_id', $logisticsUser->id)->first();

        // Give wallet balance of ₦50,000
        $company->wallet_balance = '50000.00';
        $company->bank_name = 'Zenith Bank PLC';
        $company->account_number = '1019283746';
        $company->account_name = 'Akwa Express Logistics';
        $company->bank_updated_at = Carbon::now()->subHours(2); // strictly within cooldown (<48h)
        $company->save();

        $initialCount = LogisticsPayoutRequest::count();

        $response = $this->actingAs($logisticsUser)->post(route('logistics.payout.request'), [
            'amount' => '10000.00',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Security Cooldown Active', session('error'));

        // Corporate balance must remain untouched
        $company->refresh();
        $this->assertEquals('50000.00', $company->wallet_balance);
        $this->assertEquals($initialCount, LogisticsPayoutRequest::count());
    }

    /**
     * Test 3: Withdrawal request succeeds past 48h cooldown and debits wallet balance atomically.
     */
    public function test_withdrawal_succeeds_past_cooldown_and_debits_wallet(): void
    {
        $logisticsUser = User::where('user_type', 'logistics_company')->first();
        $company = LogisticsCompanyProfile::where('user_id', $logisticsUser->id)->first();

        $company->wallet_balance = '50000.00';
        $company->bank_name = 'Zenith Bank PLC';
        $company->account_number = '1019283746';
        $company->account_name = 'Akwa Express Logistics';
        $company->bank_updated_at = Carbon::now()->subHours(50); // safely past cooldown (>48h)
        $company->save();

        $response = $this->actingAs($logisticsUser)->post(route('logistics.payout.request'), [
            'amount' => '15000.00',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status');

        // Wallet balance must be atomically debited from ₦50,000 to ₦35,000
        $company->refresh();
        $this->assertEquals('35000.00', $company->wallet_balance);

        // Payout request created
        $payout = LogisticsPayoutRequest::latest('id')->first();
        $this->assertNotNull($payout);
        $this->assertEquals('15000.00', $payout->amount);
        $this->assertEquals('pending', $payout->status);
        $this->assertEquals('1019283746', $payout->account_number);
    }

    /**
     * Test 4: Super Admin can disburse corporate payout with audit logging.
     */
    public function test_super_admin_can_disburse_fleet_payout(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        $company = LogisticsCompanyProfile::first();

        $payout = LogisticsPayoutRequest::create([
            'logistics_company_id' => $company->id,
            'amount' => '25000.00',
            'status' => 'pending',
            'bank_name' => 'Zenith Bank PLC',
            'account_number' => '1019283746',
            'account_name' => 'Akwa Express Logistics',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.disburse.logistics_payout'), [
            'payout_id' => $payout->id,
            'notes' => 'Confirmed via CBN NIP Instant Transfer',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status');

        $payout->refresh();
        $this->assertEquals('approved', $payout->status);
        $this->assertEquals($admin->id, $payout->approved_by_admin_id);
        $this->assertNotNull($payout->disbursed_at);
        $this->assertNotNull($payout->settlement_proof_path);
    }

    /**
     * Test 5: Super Admin Settlements page loads dual commission metrics and payout queue.
     */
    public function test_admin_settlements_page_displays_dual_commissions(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();

        $response = $this->actingAs($admin)->get(route('admin.settlements.index'));
        $response->assertStatus(200);
        $response->assertSee('Dual-Commission Escrow & Corporate Settlement Desk');
        $response->assertSee('VMarket Total Commission');
        $response->assertSee('Logistics Fleet Corporate Withdrawal Queue');
        $response->assertSee('Merchant 90% Merchandise Escrow Queue');
    }
}
