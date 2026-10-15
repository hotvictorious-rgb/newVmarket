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
    public function test_commercial_splits_90_5_5_invariant(): void
    {
        // Case 1: Standard ₦100,000 merchandise, ₦2,000 delivery fee
        $result = BcMathService::computeCommercialSplits('100000.00', '2000.00');
        $this->assertEquals('90000.00', $result['vendor_net_90']);
        $this->assertEquals('5000.00', $result['customer_cashback_5']);
        $this->assertEquals('5000.00', $result['platform_retained_5']);
        $this->assertEquals('5000.00', $result['platform_vendor_commission_5']);
        $this->assertEquals('2000.00', $result['delivery_fee_segregated']);
        $this->assertEquals('1800.00', $result['logistics_net_90']);
        $this->assertEquals('200.00', $result['platform_delivery_commission_10']);
        $this->assertEquals('5200.00', $result['platform_total_retained']); // 5,000 vendor cut + 200 delivery cut
        $this->assertEquals('102000.00', $result['grand_total']);

        // Invariant 1: Vendor + Cashback + Platform Vendor Margin == Merchandise Subtotal
        $merchandiseSum = bcadd(bcadd($result['vendor_net_90'], $result['customer_cashback_5'], 2), $result['platform_retained_5'], 2);
        $this->assertEquals('100000.00', $merchandiseSum);

        // Invariant 2: Logistics Net (90%) + VMarket Delivery Cut (10%) == Total Delivery Fee
        $deliverySum = bcadd($result['logistics_net_90'], $result['platform_delivery_commission_10'], 2);
        $this->assertEquals('2000.00', $deliverySum);

        // Total Conservation: Vendor (90k) + Cashback (5k) + Logistics (1.8k) + VMarket Total (5.2k) == Grand Total (102k)
        $totalSystemSum = bcadd(
            bcadd($result['vendor_net_90'], $result['customer_cashback_5'], 2),
            bcadd($result['logistics_net_90'], $result['platform_total_retained'], 2),
            2
        );
        $this->assertEquals('102000.00', $totalSystemSum);

        // Case 2: Odd merchandise amount (₦33,333.33) and ₦500.00 delivery fee
        $oddResult = BcMathService::computeCommercialSplits('33333.33', '500.00');
        $oddMerchandiseSum = bcadd(bcadd($oddResult['vendor_net_90'], $oddResult['customer_cashback_5'], 2), $oddResult['platform_retained_5'], 2);
        $this->assertEquals('33333.33', $oddMerchandiseSum);
        $this->assertEquals('450.00', $oddResult['logistics_net_90']);
        $this->assertEquals('50.00', $oddResult['platform_delivery_commission_10']);
        $this->assertEquals('500.00', bcadd($oddResult['logistics_net_90'], $oddResult['platform_delivery_commission_10'], 2));
    }

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

        // Uyo to Eket: ₦1500.00
        $interLga = $service->checkDeliveryAvailability($shop->id, $eket->id);
        $this->assertTrue($interLga['is_available']);
        $this->assertEquals('1500.00', $interLga['fee']);

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
        $this->assertEquals('corridor_suspended', $eketToUyo['rate_tier']);

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
