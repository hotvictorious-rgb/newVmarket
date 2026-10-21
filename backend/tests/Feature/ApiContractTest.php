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

    public function test_cart_fulfillment_availability_is_order_level_and_uyo_only(): void
    {
        $uyo = Lga::where('name', 'Uyo')->firstOrFail();
        $product = Product::marketplaceEligible()
            ->whereHas('supplierOffers.shop.lga', function ($query) use ($uyo) {
                $query->whereKey($uyo->id);
            })
            ->firstOrFail();

        $response = $this->postJson('/api/v1/fulfillment/availability', [
            'fulfillment_type' => 'doorstep_delivery',
            'destination_lga_id' => $uyo->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('available', true)
            ->assertJsonPath('fulfillment_options.delivery.total_shipping_fee', '500.00')
            ->assertJsonPath('fulfillment_options.in_shop_pickup.available', false);
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

    public function test_product_catalog_is_uyo_sourced_and_hides_supplier_details(): void
    {
        $response = $this->getJson('/api/v1/products');
        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        foreach ((array) $response->json('data.data') as $product) {
            foreach (['vendor_id', 'shop_id', 'cost_price', 'supplierOffers', 'vendor', 'shop'] as $privateField) {
                $this->assertArrayNotHasKey($privateField, $product);
            }
        }

        $eket = Lga::where('name', 'Eket')->firstOrFail();
        $outside = $this->getJson('/api/v1/products?lga_id=' . $eket->id);
        $outside->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data', []);
    }

    public function test_public_pickup_reservation_creation_and_lookup_are_disabled(): void
    {
        $customer = User::where('email', 'customer@victoriousmarket.com.ng')->firstOrFail();
        $product = Product::firstOrFail();

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/pickup-reservations/create', [
            'shop_id' => $product->shop_id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(410)
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 'SUPPORT_MANAGED_PICKUP_ONLY');
    }
}
