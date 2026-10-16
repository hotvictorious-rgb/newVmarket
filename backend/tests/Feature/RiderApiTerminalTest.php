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

    public function test_rider_dashboard_returns_profile_and_telemetry(): void
    {
        $riderUser = User::create([
            'name' => 'Musa Haruna',
            'email' => 'musa@speedyfleet.ng',
            'phone' => '+2348055556677',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $fleetOwner = User::create([
            'name' => 'Fleet Admin',
            'email' => 'fleet@speedy.ng',
            'password' => bcrypt('password'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $fleet = LogisticsCompanyProfile::create([
            'user_id' => $fleetOwner->id,
            'company_name' => 'Speedy Parcel Express',
            'contact_phone' => '+234800112233',
            'business_registration_number' => 'RC-998811',
            'status' => 'active',
            'is_active' => true,
        ]);

        $rider = RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => $fleet->id,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'KJA-104-XY',
            'is_active' => true,
            'is_online' => true,
            'current_balance' => '14500.00',
        ]);

        Sanctum::actingAs($riderUser);
        $response = $this->getJson('/api/v1/rider/dashboard');

        $response->assertStatus(200)
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
        $riderUser = User::create([
            'name' => 'Duty Rider',
            'email' => 'duty@rider.ng',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $rider = RiderProfile::create([
            'user_id' => $riderUser->id,
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
