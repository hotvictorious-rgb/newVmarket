<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\RiderProfile;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderApiTerminalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'currency_code' => 'NGN',
            'phone_code' => '+234',
            'is_active' => true,
        ]);
        $state = State::create(['country_id' => $country->id, 'name' => 'Lagos', 'code' => 'LA', 'is_active' => true]);
        Lga::create(['state_id' => $state->id, 'name' => 'Ikeja', 'code' => 'IKJ', 'is_active' => true]);
    }

    private function createLogisticsCompany(string $companyName = 'Speedy Parcel Express', string $status = 'active'): array
    {
        $fleetOwner = User::create([
            'name' => 'Fleet Admin',
            'email' => 'fleet_' . uniqid() . '@speedy.ng',
            'password' => bcrypt('password'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $fleet = LogisticsCompanyProfile::create([
            'user_id' => $fleetOwner->id,
            'company_name' => $companyName,
            'contact_phone' => '+234800112233',
            'business_registration_number' => 'RC-' . rand(100000, 999999),
            'status' => $status,
            'is_active' => ($status === 'active'),
        ]);

        return [$fleetOwner, $fleet];
    }

    public function test_unauthenticated_rider_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/rider/dashboard');
        $response->assertStatus(401);
    }

    public function test_customer_user_cannot_access_rider_terminal(): void
    {
        $customer = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '+2348011223344',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        Sanctum::actingAs($customer);
        $response = $this->getJson('/api/v1/rider/dashboard');
        $response->assertStatus(403);
    }

    public function test_logistics_company_user_cannot_access_rider_terminal(): void
    {
        [$fleetOwner] = $this->createLogisticsCompany();

        Sanctum::actingAs($fleetOwner);
        $response = $this->getJson('/api/v1/rider/dashboard');
        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
            ]);
    }

    public function test_vendor_user_cannot_access_rider_terminal(): void
    {
        $vendor = User::create([
            'name' => 'Test Vendor',
            'email' => 'vendor@example.com',
            'phone' => '+2348099887766',
            'password' => bcrypt('password'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        Sanctum::actingAs($vendor);
        $response = $this->getJson('/api/v1/rider/dashboard');
        $response->assertStatus(403);
    }

    public function test_rider_without_logistics_company_is_strictly_rejected(): void
    {
        $unassignedRider = User::create([
            'name' => 'Independent Rogue Rider',
            'email' => 'rogue@rider.ng',
            'phone' => '+2348077665544',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        RiderProfile::create([
            'user_id' => $unassignedRider->id,
            'logistics_company_id' => null, // INVARIANT VIOLATION: No logistics company
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
            'is_online' => true,
        ]);

        Sanctum::actingAs($unassignedRider);
        $response = $this->getJson('/api/v1/rider/dashboard');
        $response->assertStatus(403)
            ->assertJsonFragment([
                'status' => false,
            ]);
    }

    public function test_rider_belonging_to_inactive_logistics_company_is_rejected(): void
    {
        [, $inactiveFleet] = $this->createLogisticsCompany('Suspended Fleet', 'suspended');

        $riderUser = User::create([
            'name' => 'Suspended Fleet Rider',
            'email' => 'suspended_fleet@rider.ng',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => $inactiveFleet->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
            'is_online' => true,
        ]);

        Sanctum::actingAs($riderUser);
        $response = $this->getJson('/api/v1/rider/dashboard');
        $response->assertStatus(403);
    }

    public function test_rider_terminal_login_blocks_logistics_company_account(): void
    {
        [$fleetOwner] = $this->createLogisticsCompany();

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => $fleetOwner->email,
            'password' => 'password',
            'terminal' => 'rider',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
            ]);
    }

    public function test_rider_terminal_login_blocks_customer_and_vendor(): void
    {
        $customer = User::create([
            'name' => 'Buyer Account',
            'email' => 'buyer@market.ng',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => $customer->email,
            'password' => 'password',
            'terminal' => 'rider',
        ]);

        $response->assertStatus(403);
    }

    public function test_rider_terminal_login_blocks_unassigned_rider(): void
    {
        $unassignedRider = User::create([
            'name' => 'Unassigned Rider',
            'email' => 'no_fleet@rider.ng',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        RiderProfile::create([
            'user_id' => $unassignedRider->id,
            'logistics_company_id' => null,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
            'is_online' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => $unassignedRider->email,
            'password' => 'password',
            'terminal' => 'rider',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'status' => false,
            ]);
    }

    public function test_valid_rider_with_logistics_company_can_login_and_access_dashboard(): void
    {
        [, $fleet] = $this->createLogisticsCompany('Speedy Parcel Express');

        $riderUser = User::create([
            'name' => 'Musa Haruna',
            'email' => 'musa@speedyfleet.ng',
            'phone' => '+2348055556677',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $rider = RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => $fleet->id,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'KJA-104-XY',
            'is_active' => true,
            'is_online' => true,
        ]);
        $rider->creditBalance('14500.00');

        // 1. Mobile Terminal Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'login' => 'musa@speedyfleet.ng',
            'password' => 'password',
            'terminal' => 'rider',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'user' => [
                    'name' => 'Musa Haruna',
                    'user_type' => 'rider',
                    'fleet_company' => 'Speedy Parcel Express',
                ],
            ]);

        $token = $loginResponse->json('token');
        $this->assertNotEmpty($token);

        // 2. Access Rider Dashboard with Bearer Token
        $dashResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/rider/dashboard');

        $dashResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'rider' => [
                        'name' => 'Musa Haruna',
                        'fleet_company' => 'Speedy Parcel Express',
                        'vehicle_type' => 'motorcycle',
                        'is_online' => true,
                    ],
                    'fintech_metrics' => [
                        'wallet_balance' => 14500.00,
                    ],
                ],
            ]);
    }

    public function test_rider_can_toggle_duty_status(): void
    {
        [, $fleet] = $this->createLogisticsCompany('Speedy Parcel Express');

        $riderUser = User::create([
            'name' => 'Duty Rider',
            'email' => 'duty@rider.ng',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $rider = RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => $fleet->id,
            'is_active' => true,
            'is_online' => true,
            'vehicle_type' => 'motorcycle',
        ]);

        Sanctum::actingAs($riderUser);
        $response = $this->postJson('/api/v1/rider/toggle-duty');
        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => ['is_online' => false],
            ]);

        $this->assertFalse((bool)$rider->fresh()->is_online);
    }
}
