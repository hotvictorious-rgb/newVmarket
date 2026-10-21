<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\RiderProfile;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLogisticsAndRiderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected State $state;
    protected Lga $uyoLga;
    protected Lga $ikotEkpeneLga;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::firstOrCreate(
            ['iso_code' => 'NG'],
            ['name' => 'Nigeria', 'currency_code' => 'NGN', 'phone_code' => '+234', 'is_active' => true]
        );

        $this->state = State::create([
            'country_id' => $country->id,
            'name' => 'Akwa Ibom',
            'code' => 'AK',
            'is_active' => true,
        ]);

        $this->uyoLga = Lga::create([
            'state_id' => $this->state->id,
            'name' => 'Uyo',
            'is_active' => true,
        ]);

        $this->ikotEkpeneLga = Lga::create([
            'state_id' => $this->state->id,
            'name' => 'Ikot Ekpene',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@victoriousmarket.ng',
            'phone' => '08000000001',
            'user_type' => 'super_admin',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_public_registration_cannot_register_as_logistics_company_or_rider(): void
    {
        // Attempt role escalation to logistics_company
        $this->post(route('customer.register.post'), [
            'name' => 'Rogue Logistics',
            'email' => 'rogue_fleet@test.com',
            'phone' => '08099990001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'logistics_company',
            'terms' => 'on',
        ]);

        $user1 = User::where('email', 'rogue_fleet@test.com')->first();
        $this->assertNotNull($user1);
        $this->assertEquals('customer', $user1->user_type, 'Public registration must never allow logistics_company role');

        // Attempt role escalation to rider
        $this->post(route('customer.register.post'), [
            'name' => 'Rogue Rider',
            'email' => 'rogue_rider@test.com',
            'phone' => '08099990002',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'rider',
            'terms' => 'on',
        ]);

        $user2 = User::where('email', 'rogue_rider@test.com')->first();
        $this->assertNotNull($user2);
        $this->assertEquals('customer', $user2->user_type, 'Public registration must never allow rider role');
    }

    public function test_unauthenticated_or_non_admin_cannot_access_or_onboard_logistics_companies(): void
    {
        $payload = [
            'company_name' => 'Unauthorized Logistics Ltd',
            'contact_name' => 'John Doe',
            'phone' => '08011113333',
            'hq_address' => '123 Fake Street',
            'lga_id' => $this->uyoLga->id,
        ];

        // Guest attempt
        $guestResponse = $this->post(route('admin.logistics_companies.store'), $payload);
        $guestResponse->assertRedirect();
        $this->assertDatabaseMissing('logistics_company_profiles', ['company_name' => 'Unauthorized Logistics Ltd']);

        // Customer attempt
        $customer = User::create([
            'name' => 'Regular Customer',
            'email' => 'cust_log@test.com',
            'phone' => '08022224444',
            'user_type' => 'customer',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);

        $authResponse = $this->actingAs($customer)->post(route('admin.logistics_companies.store'), $payload);
        $this->assertTrue(in_array($authResponse->status(), [302, 403]));
        $this->assertDatabaseMissing('logistics_company_profiles', ['company_name' => 'Unauthorized Logistics Ltd']);
    }

    public function test_super_admin_can_onboard_and_toggle_logistics_company(): void
    {
        $payload = [
            'company_name' => 'Apex Express Logistics',
            'contact_name' => 'Bassey Albert',
            'phone' => '08033335555',
            'email' => 'bassey@apexexpress.ng',
            'password' => 'FleetPass2026!',
            'registration_number' => 'RC 7654321',
            'hq_address' => 'Plot 10, Oron Road, Uyo, Akwa Ibom State',
            'lga_id' => $this->uyoLga->id,
            'fleet_size' => 15,
            'commission_rate' => 0.00,
            'bank_name' => 'Access Bank',
            'account_number' => '0123456789',
            'account_name' => 'Apex Express Ltd',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('admin.logistics_companies.store'), $payload);
        $response->assertRedirect(route('admin.logistics_companies.index'));
        $response->assertSessionHas('success');

        // Verify user created
        $user = User::where('email', 'bassey@apexexpress.ng')->first();
        $this->assertNotNull($user);
        $this->assertEquals('logistics_company', $user->user_type);
        $this->assertEquals('active', $user->status);

        // Verify LogisticsCompanyProfile created
        $company = LogisticsCompanyProfile::where('company_name', 'Apex Express Logistics')->first();
        $this->assertNotNull($company);
        $this->assertEquals($user->id, $company->user_id);
        $this->assertEquals($this->uyoLga->id, $company->lga_id);
        $this->assertEquals(15, $company->fleet_size);
        $this->assertEquals('active', $company->status);
        $this->assertEquals('Access Bank', $company->bank_name);
        $this->assertEquals('0123456789', $company->account_number);

        // Verify Admin Audit Log
        $this->assertDatabaseHas('admin_audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'LOGISTICS_COMPANY_ONBOARDED',
            'target_type' => 'LogisticsCompanyProfile',
            'target_id' => $company->id,
        ]);

        // Toggle status (suspend)
        $toggleResponse = $this->actingAs($this->adminUser)->post(route('admin.logistics_companies.toggle_status', $company->id));
        $toggleResponse->assertRedirect(route('admin.logistics_companies.index'));

        $company->refresh();
        $this->assertEquals('suspended', $company->status);
        $this->assertEquals('suspended', $company->user->status);

        // Toggle back (reactivate)
        $this->actingAs($this->adminUser)->post(route('admin.logistics_companies.toggle_status', $company->id));
        $company->refresh();
        $this->assertEquals('active', $company->status);
        $this->assertEquals('active', $company->user->status);
    }

    public function test_logistics_company_can_manage_their_riders_with_tenant_isolation(): void
    {
        // 1. Create Logistics Company A
        $userA = User::create([
            'name' => 'Swift Courier Ltd',
            'email' => 'admin@swiftcouriers.ng',
            'phone' => '08044446666',
            'user_type' => 'logistics_company',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);

        $companyA = LogisticsCompanyProfile::create([
            'user_id' => $userA->id,
            'company_name' => 'Swift Couriers Ltd',
            'lga_id' => $this->uyoLga->id,
            'fleet_size' => 10,
            'status' => 'active',
        ]);

        // 2. Create Logistics Company B
        $userB = User::create([
            'name' => 'Eagle Express Ltd',
            'email' => 'admin@eagleexpress.ng',
            'phone' => '08055557777',
            'user_type' => 'logistics_company',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);

        $companyB = LogisticsCompanyProfile::create([
            'user_id' => $userB->id,
            'company_name' => 'Eagle Express Ltd',
            'lga_id' => $this->ikotEkpeneLga->id,
            'fleet_size' => 5,
            'status' => 'active',
        ]);

        // 3. Company A onboards Rider 1
        $riderPayload = [
            'name' => 'Kufre Daniel',
            'phone' => '08077778888',
            'email' => 'kufre@swiftcouriers.ng',
            'password' => 'RiderPass2026!',
            'assigned_lga_id' => $this->uyoLga->id,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'UYO-789-AK',
            'nin_number' => '12345678901',
        ];

        $response = $this->actingAs($userA)->post(route('logistics.riders.store'), $riderPayload);
        $response->assertRedirect(route('logistics.riders.index'));
        $response->assertSessionHas('status');

        // Verify rider user & profile
        $riderUser = User::where('email', 'kufre@swiftcouriers.ng')->first();
        $this->assertNotNull($riderUser);
        $this->assertEquals('rider', $riderUser->user_type);

        $riderProfile = RiderProfile::where('user_id', $riderUser->id)->first();
        $this->assertNotNull($riderProfile);
        $this->assertEquals($companyA->id, $riderProfile->logistics_company_id, 'Rider must be tied exclusively to Company A');
        $this->assertEquals($this->uyoLga->id, $riderProfile->assigned_lga_id);
        $this->assertEquals('UYO-789-AK', $riderProfile->license_plate);
        $this->assertTrue($riderProfile->is_active);

        // 4. Company A toggles rider status (deactivate)
        $toggleResponse = $this->actingAs($userA)->post(route('logistics.riders.toggle_active', $riderProfile->id));
        $toggleResponse->assertRedirect(route('logistics.riders.index'));

        $riderProfile->refresh();
        $this->assertFalse($riderProfile->is_active);

        // 5. Tenant Isolation: Company B attempts to toggle Company A's rider -> Must fail 404
        $crossTenantResponse = $this->actingAs($userB)->post(route('logistics.riders.toggle_active', $riderProfile->id));
        $crossTenantResponse->assertNotFound();

        // Ensure status was not altered by Company B
        $riderProfile->refresh();
        $this->assertFalse($riderProfile->is_active);
    }
}
