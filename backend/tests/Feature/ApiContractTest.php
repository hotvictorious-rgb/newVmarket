<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Lga;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_geography_endpoints_return_canonical_hierarchy(): void
    {
        $countryRes = $this->getJson('/api/v1/geography/countries');
        $countryRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.0.iso_code', 'NGA');

        $state = State::where('name', 'Akwa Ibom')->firstOrFail();
        $lgaRes = $this->getJson("/api/v1/geography/lgas/{$state->id}");
        $lgaRes->assertStatus(200)
            ->assertJsonPath('status', true);

        $lgaNames = collect($lgaRes->json('data'))->pluck('name');
        $this->assertTrue($lgaNames->contains('Uyo'));
        $this->assertTrue($lgaNames->contains('Eket'));
    }

    public function test_fulfillment_availability_resolves_directional_lane(): void
    {
        $uyo = Lga::where('name', 'Uyo')->firstOrFail();
        $shop = Shop::where('lga_id', $uyo->id)->firstOrFail();

        $response = $this->postJson('/api/v1/fulfillment/availability', [
            'fulfillment_type' => 'doorstep_delivery',
            'shop_id' => $shop->id,
            'destination_lga_id' => $uyo->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.fee', '500.00');
    }

    public function test_customer_authentication_and_token_generation(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'customer@victoriousmarket.com.ng',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'user_type']]);
    }

    public function test_product_catalog_listing_and_freshness(): void
    {
        $response = $this->getJson('/api/v1/products');
        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $productCount = count($response->json('data.data'));
        $this->assertGreaterThan(0, $productCount);
    }

    public function test_in_shop_pickup_reservation_creation(): void
    {
        $customer = User::where('email', 'customer@victoriousmarket.com.ng')->firstOrFail();
        $product = Product::firstOrFail();

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/pickup-reservations/create', [
            'shop_id' => $product->shop_id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['reservation_code', 'shop_name', 'estimated_total', 'status']]);

        $code = $response->json('data.reservation_code');
        $this->assertStringStartsWith('RES-', $code);

        // Public lookup
        $lookupRes = $this->getJson("/api/v1/pickup-reservations/{$code}");
        $lookupRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.reservation_code', $code);
    }
}
