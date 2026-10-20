<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\DeliveryLane;
use App\Models\Lga;
use App\Models\Shop;
use App\Models\ShopDeliveryCoverage;
use App\Models\State;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\FulfillmentAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorDeliveryCoverageAndAdminFeesTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;
    private State $state;
    private Lga $lgaUyo;
    private Lga $lgaEket;
    private Lga $lgaIkotEkpene;
    private State $stateLagos;
    private Lga $lgaIkeja;
    private User $vendorUser;
    private VendorProfile $vendor;
    private Shop $shop;
    private FulfillmentAvailabilityService $fulfillmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);

        $this->state = State::create([
            'country_id' => $this->country->id,
            'name' => 'Akwa Ibom',
            'state_code' => 'AK',
            'is_active' => true,
        ]);

        $this->lgaUyo = Lga::create(['state_id' => $this->state->id, 'name' => 'Uyo', 'is_active' => true]);
        $this->lgaEket = Lga::create(['state_id' => $this->state->id, 'name' => 'Eket', 'is_active' => true]);
        $this->lgaIkotEkpene = Lga::create(['state_id' => $this->state->id, 'name' => 'Ikot Ekpene', 'is_active' => true]);

        $this->stateLagos = State::create([
            'country_id' => $this->country->id,
            'name' => 'Lagos',
            'state_code' => 'LA',
            'is_active' => true,
        ]);
        $this->lgaIkeja = Lga::create(['state_id' => $this->stateLagos->id, 'name' => 'Ikeja', 'is_active' => true]);

        $this->vendorUser = User::create([
            'name' => 'Vendor Test',
            'email' => 'vendor@test.com',
            'phone' => '08011112222',
            'password' => bcrypt('Password123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Victor Tech Solutions',
            'marketplace_status' => 'active',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Victor Tech Plaza Outlet',
            'slug' => 'victor-tech-plaza',
            'address' => 'Shop 12, Ibom Plaza, Uyo',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lgaUyo->id,
            'phone' => '08011112222',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $this->fulfillmentService = app(FulfillmentAvailabilityService::class);
    }

    public function test_shop_covers_destination_when_explicit_lga_is_configured(): void
    {
        // Vendor configures coverage for Uyo LGA
        ShopDeliveryCoverage::create([
            'shop_id' => $this->shop->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lgaUyo->id,
            'is_active' => true,
        ]);

        $result = $this->fulfillmentService->checkDeliveryAvailability($this->shop->id, $this->lgaUyo->id);

        $this->assertTrue($result['is_available']);
        // Same LGA default fee set by Admin is 500.00
        $this->assertEquals('500.00', $result['fee']);
    }

    public function test_shop_rejects_delivery_when_destination_lga_is_not_covered_failing_closed(): void
    {
        // Vendor only configures coverage for Uyo LGA
        ShopDeliveryCoverage::create([
            'shop_id' => $this->shop->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lgaUyo->id,
            'is_active' => true,
        ]);

        // Customer in Eket attempts doorstep delivery
        $result = $this->fulfillmentService->checkDeliveryAvailability($this->shop->id, $this->lgaEket->id);

        $this->assertFalse($result['is_available']);
        $this->assertEquals('vendor_unserviced_area', $result['rate_tier']);
        $this->assertStringContainsString('does not offer doorstep delivery to Eket LGA', $result['message']);
        $this->assertStringContainsString('In-shop pickup is available', $result['message']);
    }

    public function test_shop_covers_all_lgas_in_state_when_entire_state_is_selected(): void
    {
        // Vendor configures entire Akwa Ibom State coverage (lga_id = null)
        ShopDeliveryCoverage::create([
            'shop_id' => $this->shop->id,
            'state_id' => $this->state->id,
            'lga_id' => null,
            'is_active' => true,
        ]);

        // Both Uyo and Eket are covered
        $resUyo = $this->fulfillmentService->checkDeliveryAvailability($this->shop->id, $this->lgaUyo->id);
        $resEket = $this->fulfillmentService->checkDeliveryAvailability($this->shop->id, $this->lgaEket->id);

        $this->assertTrue($resUyo['is_available']);
        $this->assertTrue($resEket['is_available']);

        // But Lagos (different state) is not covered
        $resLagos = $this->fulfillmentService->checkDeliveryAvailability($this->shop->id, $this->lgaIkeja->id);
        $this->assertFalse($resLagos['is_available']);
        $this->assertEquals('vendor_unserviced_area', $resLagos['rate_tier']);
    }

    public function test_admin_standardized_corridor_fee_is_honored_for_covered_destination(): void
    {
        // Vendor covers Eket
        ShopDeliveryCoverage::create([
            'shop_id' => $this->shop->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lgaEket->id,
            'is_active' => true,
        ]);

        // Admin configures explicit corridor rate in delivery_lanes (1200.00)
        DeliveryLane::create([
            'origin_lga_id' => $this->lgaUyo->id,
            'destination_lga_id' => $this->lgaEket->id,
            'base_fee' => 1200.00,
            'bulky_surcharge' => 300.00,
            'estimated_delivery_hours_min' => 4,
            'estimated_delivery_hours_max' => 12,
            'is_active' => true,
        ]);

        $resultBike = $this->fulfillmentService->checkDeliveryAvailability($this->shop->id, $this->lgaEket->id, 'bike');
        $resultKeke = $this->fulfillmentService->checkDeliveryAvailability($this->shop->id, $this->lgaEket->id, 'keke');

        $this->assertTrue($resultBike['is_available']);
        $this->assertEquals('1200.00', $resultBike['fee']);

        $this->assertTrue($resultKeke['is_available']);
        $this->assertEquals('1500.00', $resultKeke['fee']); // 1200 + 300 bulky
    }

    public function test_vendor_can_update_shop_delivery_coverage_areas_via_portal(): void
    {
        $response = $this->actingAs($this->vendorUser)
            ->post(route('vendor.shops.coverage.update', $this->shop->id), [
                'lga_ids' => [$this->lgaUyo->id, $this->lgaIkotEkpene->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('shop_delivery_coverages', [
            'shop_id' => $this->shop->id,
            'lga_id' => $this->lgaUyo->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('shop_delivery_coverages', [
            'shop_id' => $this->shop->id,
            'lga_id' => $this->lgaIkotEkpene->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseMissing('shop_delivery_coverages', [
            'shop_id' => $this->shop->id,
            'lga_id' => $this->lgaEket->id,
        ]);
    }

    public function test_vendor_cannot_update_coverage_for_another_vendors_shop(): void
    {
        $otherVendorUser = User::create([
            'name' => 'Intruder Vendor',
            'email' => 'intruder@test.com',
            'phone' => '08099998888',
            'password' => bcrypt('Password123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        VendorProfile::create([
            'user_id' => $otherVendorUser->id,
            'business_name' => 'Intruder Corp',
            'marketplace_status' => 'active',
        ]);

        $response = $this->actingAs($otherVendorUser)
            ->post(route('vendor.shops.coverage.update', $this->shop->id), [
                'lga_ids' => [$this->lgaUyo->id],
            ]);

        // Fails with 404 because shop does not belong to intruder
        $response->assertStatus(404);
    }
}
