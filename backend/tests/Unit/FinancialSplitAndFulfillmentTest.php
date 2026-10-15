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
     * Test 1: Mathematical 90 / 5 / 5 Invariant Proof (Delta = ₦0.00).
     */
    public function test_commercial_splits_90_5_5_invariant(): void
    {
        // Case 1: Standard ₦100,000 merchandise
        $result = BcMathService::computeCommercialSplits('100000.00', '2000.00');
        $this->assertEquals('90000.00', $result['vendor_net_90']);
        $this->assertEquals('5000.00', $result['customer_cashback_5']);
        $this->assertEquals('5000.00', $result['platform_retained_5']);
        $this->assertEquals('2000.00', $result['delivery_fee_segregated']);
        $this->assertEquals('102000.00', $result['grand_total']);

        // Invariant: Vendor + Cashback + Platform == Merchandise
        $sum = bcadd(bcadd($result['vendor_net_90'], $result['customer_cashback_5'], 2), $result['platform_retained_5'], 2);
        $this->assertEquals('100000.00', $sum);

        // Case 2: Odd merchandise amount (₦33,333.33)
        $oddResult = BcMathService::computeCommercialSplits('33333.33', '500.00');
        $oddSum = bcadd(bcadd($oddResult['vendor_net_90'], $oddResult['customer_cashback_5'], 2), $oddResult['platform_retained_5'], 2);
        $this->assertEquals('33333.33', $oddSum);
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
