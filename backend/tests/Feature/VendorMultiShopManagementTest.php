<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Lga;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDeliveryCoverage;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorMultiShopManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected State $state;
    protected Lga $uyoLga;
    protected Lga $eketLga;
    protected Lga $ikotEkpeneLga;
    protected Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->country = Country::first() ?? Country::create(['name' => 'Nigeria', 'code' => 'NG', 'phonecode' => '234']);
        $this->state = State::where('name', 'Akwa Ibom')->first() ?? State::create(['country_id' => $this->country->id, 'name' => 'Akwa Ibom', 'code' => 'AK']);
        $this->uyoLga = Lga::where('name', 'Uyo')->first() ?? Lga::create(['state_id' => $this->state->id, 'name' => 'Uyo', 'code' => 'UYO', 'is_active' => true]);
        $this->eketLga = Lga::where('name', 'Eket')->first() ?? Lga::create(['state_id' => $this->state->id, 'name' => 'Eket', 'code' => 'EKT', 'is_active' => true]);
        $this->ikotEkpeneLga = Lga::where('name', 'Ikot Ekpene')->first() ?? Lga::create(['state_id' => $this->state->id, 'name' => 'Ikot Ekpene', 'code' => 'IKP', 'is_active' => true]);

        $this->vendorUser = User::factory()->create([
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Apex Mega Superstores',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
        ]);
    }

    /**
     * TEST: Every shop must be strictly tied to an LGA.
     */
    public function test_every_shop_must_be_tied_to_a_valid_lga(): void
    {
        $this->actingAs($this->vendorUser);

        // Attempting to create a shop without an LGA fails validation
        $response = $this->post(route('vendor.shops.store'), [
            'name' => 'Invalid Shop No LGA',
            'address' => 'Somewhere in town',
            'phone' => '08011112222',
            // lga_id omitted
        ]);

        $response->assertSessionHasErrors(['lga_id']);
        $this->assertDatabaseMissing('shops', ['name' => 'Invalid Shop No LGA']);

        // A valid LGA is not sufficient: the sourcing point must be inside Uyo.
        $outsideResponse = $this->post(route('vendor.shops.store'), [
            'name' => 'Apex Eket Outlet',
            'address' => '5 Marina Road, Eket',
            'phone' => '08011112222',
            'lga_id' => $this->eketLga->id,
        ]);
        $outsideResponse->assertRedirect();
        $outsideResponse->assertSessionHas('error');
        $this->assertDatabaseMissing('shops', ['name' => 'Apex Eket Outlet']);

        // Successfully creating a shop requires a valid Uyo LGA
        $validResponse = $this->post(route('vendor.shops.store'), [
            'name' => 'Apex Ibom Plaza Branch',
            'address' => '14 Ibom Plaza, Uyo',
            'phone' => '08011112222',
            'lga_id' => $this->uyoLga->id,
            'pickup_enabled' => '1',
        ]);

        $validResponse->assertRedirect();
        $this->assertDatabaseHas('shops', [
            'vendor_id' => $this->vendor->id,
            'name' => 'Apex Ibom Plaza Branch',
            'lga_id' => $this->uyoLga->id,
        ]);
    }

    /**
     * TEST: Vendor can own 2 or more shops and manage them independently.
     */
    public function test_vendor_can_register_only_one_active_uyo_shop(): void
    {
        $this->actingAs($this->vendorUser);

        $firstResponse = $this->post(route('vendor.shops.store'), [
            'name' => 'Apex Ibom Plaza',
            'address' => '10 Plaza Way, Uyo',
            'phone' => '08011112222',
            'lga_id' => $this->uyoLga->id,
            'pickup_enabled' => '1',
        ]);
        $firstResponse->assertRedirect();
        $this->assertDatabaseHas('shops', [
            'vendor_id' => $this->vendor->id,
            'name' => 'Apex Ibom Plaza',
            'lga_id' => $this->uyoLga->id,
        ]);

        $secondResponse = $this->post(route('vendor.shops.store'), [
            'name' => 'Apex Second Outlet',
            'address' => '20 Aka Road, Uyo',
            'phone' => '08011112222',
            'lga_id' => $this->uyoLga->id,
            'pickup_enabled' => '1',
        ]);
        $secondResponse->assertRedirect();
        $secondResponse->assertSessionHas('error');
        $this->assertDatabaseMissing('shops', ['name' => 'Apex Second Outlet']);
        $this->assertSame(
            1,
            Shop::where('vendor_id', $this->vendor->id)->whereNull('deleted_at')->count(),
            'A supplier may maintain one active shop/pickup point only.'
        );
    }

}
