<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\DeliveryLane;
use App\Models\Lga;
use App\Models\LogisticsCollectionPoint;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\FulfillmentAvailabilityService;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsCollectionPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test 1: Logistics Company can access Collection Points management console.
     */
    public function test_logistics_company_can_view_collection_points_page(): void
    {
        $logisticsUser = User::where('email', 'logistics@victoriousmarket.com.ng')->first();
        $this->assertNotNull($logisticsUser);

        $response = $this->actingAs($logisticsUser)->get(route('logistics.collection_points.index'));

        $response->assertStatus(200);
        $response->assertSee('LGA Collection Points');
        $response->assertSee('Register Collection Point');
        $response->assertSee('Akwa Express Station & Eket Transit Hub');
    }

    /**
     * Test 2: Logistics Company can register a new collection point in an LGA.
     */
    public function test_logistics_company_can_register_new_collection_point_per_lga(): void
    {
        $logisticsUser = User::where('email', 'logistics@victoriousmarket.com.ng')->first();
        $company = $logisticsUser->logisticsCompanyProfile;
        $oronLga = Lga::where('name', 'Oron')->first() ?? Lga::skip(3)->first();

        $response = $this->actingAs($logisticsUser)->post(route('logistics.collection_points.store'), [
            'lga_id' => $oronLga->id,
            'name' => 'Akwa Express Oron Maritime Station',
            'address' => '5 Beach Road, Oron Water-Front',
            'landmark' => 'Beside Marine Police Headquarters',
            'contact_phone' => '08099887766',
            'contact_person' => 'Effiong Edet',
            'operating_hours' => '08:00 AM - 06:00 PM',
        ]);

        $response->assertRedirect(route('logistics.collection_points.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('logistics_collection_points', [
            'logistics_company_id' => $company->id,
            'lga_id' => $oronLga->id,
            'name' => 'Akwa Express Oron Maritime Station',
            'contact_phone' => '08099887766',
            'is_active' => true,
        ]);

        $created = LogisticsCollectionPoint::where('name', 'Akwa Express Oron Maritime Station')->first();
        $this->assertNotNull($created->code);
        $this->assertStringStartsWith('CP-', $created->code);
        $this->assertEquals($oronLga->state_id, $created->state_id);
    }

    /**
     * Test 3: Logistics Company can toggle collection point active/paused status.
     */
    public function test_logistics_company_can_toggle_collection_point_status(): void
    {
        $logisticsUser = User::where('email', 'logistics@victoriousmarket.com.ng')->first();
        $point = LogisticsCollectionPoint::where('logistics_company_id', $logisticsUser->logisticsCompanyProfile->id)->first();
        $this->assertNotNull($point);

        $initialStatus = $point->is_active;

        $response = $this->actingAs($logisticsUser)->post(route('logistics.collection_points.toggle', $point->id));
        $response->assertRedirect(route('logistics.collection_points.index'));

        $this->assertEquals(!$initialStatus, $point->fresh()->is_active);
    }

    /**
     * Test 4: FulfillmentAvailabilityService attaches collection points for cross-LGA routes.
     */
    public function test_fulfillment_service_attaches_collection_points_for_inter_lga_routes(): void
    {
        $uyo = Lga::where('name', 'Uyo')->first();
        $eket = Lga::where('name', 'Eket')->first();
        $shop = Shop::where('lga_id', $uyo->id)->first();

        $service = new FulfillmentAvailabilityService();

        // 1. Intra-LGA (Uyo to Uyo) -> Pure Doorstep, is_collection_point = false
        $intraResult = $service->checkDeliveryAvailability($shop->id, $uyo->id);
        $this->assertTrue($intraResult['is_available']);
        $this->assertFalse($intraResult['is_collection_point']);
        $this->assertEmpty($intraResult['collection_points']);

        // 2. Inter-LGA (Uyo to Eket) -> Collection Point Pickup, is_collection_point = true
        $interResult = $service->checkDeliveryAvailability($shop->id, $eket->id);
        $this->assertTrue($interResult['is_available']);
        $this->assertTrue($interResult['is_collection_point']);
        $this->assertNotEmpty($interResult['collection_points']);
        $this->assertEquals('Akwa Express Station & Eket Transit Hub', $interResult['collection_points'][0]['name']);
    }

    /**
     * Test 5: Checkout Process assigns collection point and links rider to parent logistics company.
     */
    public function test_checkout_process_attaches_collection_point_and_rider_from_company(): void
    {
        $customer = User::where('user_type', 'customer')->first();
        $uyo = Lga::where('name', 'Uyo')->first();
        $product = Product::whereHas('shop', function ($q) use ($uyo) {
            $q->where('lga_id', $uyo->id);
        })->marketplaceEligible()->first();
        $eket = Lga::where('name', 'Eket')->first();
        $collectionPoint = LogisticsCollectionPoint::where('lga_id', $eket->id)->first();

        $this->assertNotNull($collectionPoint);

        $response = $this->actingAs($customer)->post(route('checkout.process'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'fulfillment_type' => 'doorstep_delivery',
            'customer_name' => 'Emeka Okonkwo',
            'customer_phone' => '08055550001',
            'destination_lga_id' => $eket->id,
            'collection_point_id' => $collectionPoint->id,
            'delivery_address' => '22 Marina Road, Eket',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals($collectionPoint->id, $order->collection_point_id);
        $this->assertTrue($order->isCollectionPointPickup());
        $this->assertNotNull($order->rider);
        $this->assertEquals($collectionPoint->logistics_company_id, $order->rider->logistics_company_id);
        // Verify 90% Logistics Net and 10% Victorious Market Delivery Commission
        $this->assertEquals(bcmul((string)$order->delivery_fee, '0.90', 2), $order->logistics_fee_retained);
        $this->assertEquals(bcmul((string)$order->delivery_fee, '0.10', 2), $order->platform_delivery_fee);
    }

    /**
     * Test 6: Corporate B2B Settlement guarantees 90% delivery fee is credited to parent
     * Logistics Company Corporate Wallet, with Victorious Market retaining 10% delivery commission.
     */
    public function test_delivery_otp_credits_parent_logistics_company_corporate_wallet(): void
    {
        $customer = User::where('user_type', 'customer')->first();
        $logisticsCompany = LogisticsCompanyProfile::where('status', 'active')->first();
        $rider = RiderProfile::where('logistics_company_id', $logisticsCompany->id)->first();
        $eket = Lga::where('name', 'Eket')->first();
        $collectionPoint = LogisticsCollectionPoint::where('lga_id', $eket->id)->first();

        // Ensure clean corporate wallet balance
        $logisticsCompany->wallet_balance = '0.00';
        $logisticsCompany->save();

        $deliveryFee = '1500.00';
        $otp = '789123';

        $order = Order::create([
            'order_number' => 'ORD-TEST-B2B-' . rand(1000, 9999),
            'customer_id' => $customer->id,
            'vendor_id' => VendorProfile::first()->id,
            'shop_id' => Shop::first()->id,
            'collection_point_id' => $collectionPoint->id,
            'rider_id' => $rider->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'payment_method' => 'paystack_direct',
            'origin_lga_id' => Shop::first()->lga_id,
            'destination_lga_id' => $eket->id,
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => $deliveryFee,
            'grand_total' => '21500.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'logistics_fee_retained' => '1350.00', // 90% to logistics company
            'platform_delivery_fee' => '150.00',   // 10% to Victorious Market
            'pickup_verification_code' => '654321',
            'verification_code' => $otp,
            'rider_arrived_at' => Carbon::now(),
            'vendor_settlement_status' => 'held',
        ]);

        $settlementService = new OrderSettlementService();
        $result = $settlementService->verifyCustomerDeliveryOtp($order, $otp, $rider);

        $this->assertTrue($result['success']);
        $this->assertEquals('delivered', $order->fresh()->order_status);

        // Authoritative Proof: 90% of delivery fee credited to Logistics Company Corporate Wallet
        $refreshedCompany = $logisticsCompany->fresh();
        $this->assertEquals('1350.00', $refreshedCompany->wallet_balance);

        // Authoritative Proof: VMarket earns both Vendor Commission (5% = ₦1,000) and Delivery Commission (10% = ₦150)
        $this->assertEquals('1000.00', $order->fresh()->platform_margin_5);
        $this->assertEquals('150.00', $order->fresh()->platform_delivery_fee);
    }
}
