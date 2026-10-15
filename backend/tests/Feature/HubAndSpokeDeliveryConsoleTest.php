<?php

namespace Tests\Feature;

use App\Models\DeliveryLane;
use App\Models\Lga;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Services\FulfillmentAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubAndSpokeDeliveryConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test 1: Delivery console loads correctly for default Hub (Uyo) and custom Hub (Eket).
     */
    public function test_delivery_console_loads_with_hub_filtering(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@victoriousmarket.com.ng'],
            [
                'name' => 'Super Admin',
                'phone' => '08000000001',
                'password' => bcrypt('Password123!'),
                'user_type' => 'super_admin',
                'is_active' => true,
            ]
        );

        $uyo = Lga::where('name', 'Uyo')->firstOrFail();
        $eket = Lga::where('name', 'Eket')->firstOrFail();

        // 1. Default load (defaults to Uyo)
        $response = $this->actingAs($admin)->get(route('admin.delivery_lanes.index'));
        $response->assertStatus(200);
        $response->assertSee('Tier 1: Municipal Dispatch');
        $response->assertSee('Tier 2: Inter-LGA Regional Corridors');
        $response->assertSee('Tier 3: Out-of-State Corridors');
        $response->assertSee('Uyo');

        // 2. Explicit Hub load (Eket)
        $responseEket = $this->actingAs($admin)->get(route('admin.delivery_lanes.index', ['hub_lga_id' => $eket->id]));
        $responseEket->assertStatus(200);
        $responseEket->assertSee('Eket');
    }

    /**
     * Test 2: Toggle Inter-LGA corridor and verify directional asymmetry.
     */
    public function test_toggle_inter_lga_corridor_directional_asymmetry(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@victoriousmarket.com.ng'],
            [
                'name' => 'Super Admin',
                'phone' => '08000000001',
                'password' => bcrypt('Password123!'),
                'user_type' => 'super_admin',
                'is_active' => true,
            ]
        );

        $uyo = Lga::where('name', 'Uyo')->firstOrFail();
        $oron = Lga::where('name', 'Oron')->firstOrFail();
        $shop = Shop::where('lga_id', $uyo->id)->firstOrFail();

        $service = new FulfillmentAvailabilityService();

        // Initially baseline is active
        $initialCheck = $service->checkDeliveryAvailability($shop->id, $oron->id);
        $this->assertTrue($initialCheck['is_available']);

        // Admin toggles corridor OFF (Uyo -> Oron)
        $toggleResponse = $this->actingAs($admin)->post(route('admin.delivery_lanes.toggle_corridor'), [
            'origin_lga_id' => $uyo->id,
            'destination_lga_id' => $oron->id,
            'set_active' => '0',
        ]);
        $toggleResponse->assertRedirect(route('admin.delivery_lanes.index', ['hub_lga_id' => $uyo->id]));

        // Verify Uyo -> Oron is now suspended
        $lane = DeliveryLane::where('origin_lga_id', $uyo->id)
            ->where('destination_lga_id', $oron->id)
            ->firstOrFail();
        $this->assertFalse((bool)$lane->is_active);

        $checkAfter = $service->checkDeliveryAvailability($shop->id, $oron->id);
        $this->assertFalse($checkAfter['is_available']);
        $this->assertEquals('corridor_suspended', $checkAfter['rate_tier']);

        // Verify reverse direction asymmetry: Oron -> Uyo is NOT suspended!
        $reverseLane = DeliveryLane::where('origin_lga_id', $oron->id)
            ->where('destination_lga_id', $uyo->id)
            ->first();
        $this->assertTrue($reverseLane === null || (bool)$reverseLane->is_active);
    }

    /**
     * Test 3: Toggle Out-of-State corridor and verify nationwide serviceability guard.
     */
    public function test_toggle_out_of_state_corridor(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@victoriousmarket.com.ng'],
            [
                'name' => 'Super Admin',
                'phone' => '08000000001',
                'password' => bcrypt('Password123!'),
                'user_type' => 'super_admin',
                'is_active' => true,
            ]
        );

        $uyo = Lga::where('name', 'Uyo')->firstOrFail();
        $abia = State::where('name', 'Abia')->firstOrFail();
        $abiaLga = Lga::where('state_id', $abia->id)->firstOrFail();
        $shop = Shop::where('lga_id', $uyo->id)->firstOrFail();

        $service = new FulfillmentAvailabilityService();

        // Suspend delivery to Abia State
        $response = $this->actingAs($admin)->post(route('admin.delivery_lanes.toggle_corridor'), [
            'origin_lga_id' => $uyo->id,
            'destination_state_id' => $abia->id,
            'set_active' => '0',
        ]);
        $response->assertRedirect(route('admin.delivery_lanes.index', ['hub_lga_id' => $uyo->id]));

        // Check serviceability to the capital LGA in Abia
        $abiaCheck = $service->checkDeliveryAvailability($shop->id, $abiaLga->id);
        $this->assertFalse($abiaCheck['is_available']);
        $this->assertContains($abiaCheck['rate_tier'], ['corridor_suspended', 'state_corridor_suspended']);

        // Check serviceability to another LGA in Abia that relies on state-level suspension
        $otherAbiaLga = Lga::where('state_id', $abia->id)->where('id', '!=', $abiaLga->id)->first();
        if ($otherAbiaLga) {
            $otherCheck = $service->checkDeliveryAvailability($shop->id, $otherAbiaLga->id);
            $this->assertFalse($otherCheck['is_available']);
            $this->assertEquals('state_corridor_suspended', $otherCheck['rate_tier']);
        }

        // In-shop pickup remains 100% available and free
        $pickupCheck = $service->checkPickupAvailability($shop->id);
        $this->assertTrue($pickupCheck['is_available']);
        $this->assertNotEmpty($pickupCheck['shop_name']);
    }

    /**
     * Test 4: Checkout rejects doorstep delivery when corridor is suspended, but accepts in-store pickup.
     */
    public function test_checkout_serviceability_enforcement(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'shopper@test.com'],
            [
                'name' => 'Test Shopper',
                'phone' => '08099887766',
                'password' => bcrypt('Password123!'),
                'user_type' => 'customer',
                'is_active' => true,
            ]
        );

        $product = Product::with('shop')->firstOrFail();
        $shop = $product->shop;

        // Choose a destination LGA and suspend the corridor
        $destLga = Lga::where('id', '!=', $shop->lga_id)->firstOrFail();
        DeliveryLane::updateOrCreate(
            ['origin_lga_id' => $shop->lga_id, 'destination_lga_id' => $destLga->id],
            [
                'base_fee' => '1500.00',
                'bulky_surcharge' => '1000.00',
                'estimated_delivery_hours_min' => 12,
                'estimated_delivery_hours_max' => 24,
                'is_active' => false, // Suspended!
            ]
        );

        // 1. Attempt doorstep delivery on suspended corridor -> Should fail with validation error
        $doorstepResponse = $this->actingAs($user)->post(route('checkout.process'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => 'Test Customer',
            'customer_phone' => '08099887766',
            'destination_lga_id' => $destLga->id,
            'delivery_address' => '12 Test Street',
            'shop_id' => $shop->id,
        ]);
        $doorstepResponse->assertSessionHasErrors(['fulfillment_type']);

        // 2. Select In-Shop Pickup -> Should succeed flawlessly
        $pickupResponse = $this->actingAs($user)->post(route('checkout.process'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'fulfillment_type' => 'in_shop_pickup',
            'customer_name' => 'Test Customer',
            'customer_phone' => '08099887766',
            'shop_id' => $shop->id,
        ]);
        $pickupResponse->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'customer_id' => $user->id,
            'fulfillment_type' => 'in_shop_pickup',
            'delivery_fee' => '0.00',
        ]);
    }
}
