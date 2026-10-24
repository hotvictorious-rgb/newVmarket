<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Lga;
use App\Models\ManualBankTransferPayment;
use App\Models\Order;
use App\Models\State;
use App\Models\User;
use App\Services\OrderSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualBankTransferStrictSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_single_env_configured_super_admin_can_ever_mark_manual_transfer_paid(): void
    {
        config([
            'auth.superadmin.email' => 'canonical-superadmin@victoriousmarket.com.ng',
            'auth.strict_env_super_admin_in_tests' => true,
        ]);

        $country = Country::firstOrCreate(['iso_code' => 'NG'], ['name' => 'Nigeria', 'phone_code' => '+234', 'currency_code' => 'NGN', 'is_active' => true]);
        $state = State::firstOrCreate(['name' => 'Akwa Ibom'], ['country_id' => $country->id, 'state_code' => 'AK', 'is_active' => true]);
        $lga = Lga::firstOrCreate(['name' => 'Uyo', 'state_id' => $state->id], ['is_active' => true]);

        // 1. The ONLY authorized Super Admin matching ENV
        $canonicalSuperAdmin = User::factory()->create([
            'name' => 'Canonical Super Admin',
            'email' => 'canonical-superadmin@victoriousmarket.com.ng',
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        // 2. A rogue user with user_type='super_admin' but a different email
        $rogueSuperAdmin = User::factory()->create([
            'name' => 'Rogue Super Admin Infiltrator',
            'email' => 'imposter@example.com',
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        // 3. Regular staff & actors
        $adminStaff = User::factory()->create(['user_type' => 'admin_employee', 'status' => 'active']);
        $vendorUser = User::factory()->create(['user_type' => 'vendor', 'status' => 'active']);
        $customer = User::factory()->create(['user_type' => 'customer', 'status' => 'active']);

        $order = Order::create([
            'order_number' => 'VM-SECURE-MANUAL-01',
            'customer_id' => $customer->id,
            'origin_lga_id' => $lga->id,
            'destination_lga_id' => $lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'manual_bank_transfer',
            'payment_reference' => 'VM-REF-MANUAL-SEC-01',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '500.00',
            'grand_total' => '10500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '450.00',
            'platform_delivery_fee' => '50.00',
        ]);

        $payment = ManualBankTransferPayment::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'evidence_path' => 'manual-bank-transfer/' . $order->id . '/sample.jpg',
            'evidence_mime' => 'image/jpeg',
            'evidence_size_bytes' => 1024,
            'evidence_sha256' => hash('sha256', 'sample'),
            'status' => 'pending_review',
            'currency' => 'NGN',
            'submitted_at' => now(),
        ]);

        $payload = [
            'bank_transaction_reference' => 'BANK-CBN-REF-SEC-999',
            'credited_amount' => '10500.00',
            'confirm_bank_credit' => '1',
            'decision_reason' => 'Corporate account audit matching reference.',
        ];

        // A. Customer cannot approve
        $responseCust = $this->actingAs($customer)->post(route('admin.manual-bank-transfers.approve', $payment->id), $payload);
        $responseCust->assertStatus(403);
        $this->assertSame('pending', $order->fresh()->payment_status);

        // B. Vendor cannot approve
        $responseVendor = $this->actingAs($vendorUser)->post(route('admin.manual-bank-transfers.approve', $payment->id), $payload);
        $responseVendor->assertStatus(403);
        $this->assertSame('pending', $order->fresh()->payment_status);

        // C. Admin Employee cannot approve
        $responseStaff = $this->actingAs($adminStaff)->post(route('admin.manual-bank-transfers.approve', $payment->id), $payload);
        $responseStaff->assertStatus(403);
        $this->assertSame('pending', $order->fresh()->payment_status);

        // D. Rogue Super Admin (different email) CANNOT approve
        $responseRogue = $this->actingAs($rogueSuperAdmin)->post(route('admin.manual-bank-transfers.approve', $payment->id), $payload);
        $responseRogue->assertStatus(403);
        $this->assertSame('pending', $order->fresh()->payment_status);

        // E. Direct Service invocation with non-canonical super admin fails closed
        $settlementService = app(OrderSettlementService::class);
        $directServiceResult = $settlementService->settleManualBankTransferOrder(
            $order,
            'BANK-CBN-DIRECT-FAIL',
            1050000,
            $adminStaff
        );
        $this->assertFalse($directServiceResult['success']);
        $this->assertSame('UNAUTHORIZED_SUPER_ADMIN_ONLY', $directServiceResult['status']);
        $this->assertSame('pending', $order->fresh()->payment_status);

        // F. ONLY the single ENV-configured Canonical Super Admin can approve
        $responseCanonical = $this->actingAs($canonicalSuperAdmin)->post(route('admin.manual-bank-transfers.approve', $payment->id), $payload);
        $responseCanonical->assertRedirect(route('admin.manual-bank-transfers.index'));
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('approved', $payment->fresh()->status);
    }
}
