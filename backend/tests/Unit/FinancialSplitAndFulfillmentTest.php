<?php

namespace Tests\Unit;

use App\Models\DeliveryLane;
use App\Models\Lga;
use App\Models\Order;
use App\Models\Shop;
use App\Models\VendorEmployeeProfile;
use App\Models\VendorProfile;
use App\Services\BcMathService;
use App\Services\FulfillmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialSplitAndFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test 1: Mathematical Split Engine Proof (Zero Float Drift Δ = ₦0.00).
     * Merchandise: 90% Vendor Net, 5% Cashback, 5% VMarket Vendor Commission.
     * Delivery Fee: 90% Logistics Company Net, 10% VMarket Delivery Commission.
     * VMarket Total = 5% Vendor Commission + 10% Delivery Commission.
     */
    
    /**
     * Test 2: Directional LGA Delivery Lane Resolution.
     */
    public function test_directional_delivery_lane_resolution(): void
    {
        $uyo = Lga::where('name', 'Uyo')->firstOrFail();
        $eket = Lga::where('name', 'Eket')->firstOrFail();
        $shop = Shop::where('lga_id', $uyo->id)->firstOrFail();

        $service = new FulfillmentAvailabilityService();

        // Intra-Uyo: ₦500.00
        $intraUyo = $service->checkDeliveryAvailability($shop->id, $uyo->id);
        $this->assertTrue($intraUyo['is_available']);
        $this->assertEquals('500.00', $intraUyo['fee']);

        // Uyo to Eket is blocked by launch policy (all deliveries confined to Uyo).
        DeliveryLane::updateOrCreate([
            'origin_lga_id' => $uyo->id,
            'destination_lga_id' => $eket->id,
        ], [
            'base_fee' => '1500.00',
            'bulky_surcharge' => '1000.00',
            'estimated_delivery_hours_min' => 12,
            'estimated_delivery_hours_max' => 24,
            'is_active' => true,
        ]);
        $interLga = $service->checkDeliveryAvailability($shop->id, $eket->id);
        $this->assertFalse($interLga['is_available']);
        $this->assertEquals('outside_uyo_launch_boundary', $interLga['rate_tier']);

        // Real-World Scenario: Asymmetric unserviceable corridor
        // Vendor shop located in Eket
        $eketShop = Shop::create([
            'vendor_id' => $shop->vendor_id,
            'country_id' => $shop->country_id ?? 1,
            'lga_id' => $eket->id,
            'state_id' => $eket->state_id,
            'name' => 'Eket Marine Plaza Branch',
            'slug' => 'eket-marine-plaza-branch',
            'phone' => '+2348098765432',
            'address' => '12 Marina Road, Eket',
            'is_primary' => false,
            'pickup_enabled' => true,
        ]);

        // Eket -> Uyo is unserviceable / deactivated by admin
        DeliveryLane::updateOrCreate([
            'origin_lga_id' => $eket->id,
            'destination_lga_id' => $uyo->id,
        ], [
            'base_fee' => '1500.00',
            'bulky_surcharge' => '1000.00',
            'estimated_delivery_hours_min' => 12,
            'estimated_delivery_hours_max' => 24,
            'is_active' => false, // Explicitly Deactivated / Unserviceable
        ]);

        // Doorstep delivery from Eket to Uyo MUST be unavailable
        $eketToUyo = $service->checkDeliveryAvailability($eketShop->id, $uyo->id);
        $this->assertFalse($eketToUyo['is_available']);
        $this->assertEquals('0.00', $eketToUyo['fee']);
        $this->assertEquals('origin_outside_uyo', $eketToUyo['rate_tier']);

        // But In-Shop Counter Inspection & Pickup at Eket Shop is 100% available with ₦0.00 fee!
        $pickup = $service->checkPickupAvailability($eketShop->id);
        $this->assertTrue($pickup['is_available']);
        $this->assertEquals('Eket Marine Plaza Branch', $pickup['shop_name']);
        $this->assertEquals('Eket', $pickup['lga']);
    }

    /**
     * Test 3: 48-Hour NUBAN Bank Cooldown Security Invariant.
     */
    public function test_vendor_bank_cooldown_invariant(): void
    {
        $vendor = VendorProfile::firstOrFail();

        // Updated just 2 hours ago -> strictly inside cooldown
        $vendor->bank_updated_at = Carbon::now()->subHours(2);
        $vendor->save();
        $this->assertTrue($vendor->isWithinBankCooldown());

        // Updated 50 hours ago -> safely past cooldown
        $vendor->bank_updated_at = Carbon::now()->subHours(50);
        $vendor->save();
        $this->assertFalse($vendor->isWithinBankCooldown());
    }

    /**
     * Test 4: Branch Isolation Barrier for Vendor Staff.
     */
    public function test_vendor_staff_branch_isolation_barrier(): void
    {
        $staff = VendorEmployeeProfile::firstOrFail();
        $assignedShopId = $staff->shop_id;
        $unassignedShopId = $assignedShopId + 999;

        $this->assertTrue($staff->canAccessBranch($assignedShopId));
        $this->assertFalse($staff->canAccessBranch($unassignedShopId));
    }
}
