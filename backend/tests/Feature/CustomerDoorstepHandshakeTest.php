<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerDoorstepHandshakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCountryStateLgas();
    }

    private function seedCountryStateLgas(): void
    {
        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);
        $state = State::create(['country_id' => $country->id, 'name' => 'Akwa Ibom', 'code' => 'AK', 'is_active' => true]);
        Lga::create(['state_id' => $state->id, 'name' => 'Uyo', 'code' => 'UYO', 'is_active' => true]);
    }

    public function test_rider_marks_arrived_and_customer_enters_delivery_code_to_release_escrow(): void
    {
        $country = Country::first();
        $uyo = Lga::where('name', 'Uyo')->first();

        // 1. Logistics Fleet & Courier
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

        $riderUser = User::create([
            'name' => 'Musa Courier',
            'email' => 'musa@speedy.ng',
            'password' => bcrypt('password'),
            'user_type' => 'rider',
            'status' => 'active',
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

        // 2. Vendor & Shop
        $vendorUser = User::create([
            'name' => 'Vendor Plaza',
            'email' => 'vendor@megatech.ng',
            'password' => bcrypt('password'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $vendorProfile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'business_name' => 'MegaTech Plaza',
            'contact_phone' => '+2348022334455',
            'marketplace_status' => 'active',
            'commission_rate' => 10.00,
        ]);

        $shop = Shop::create([
            'vendor_id' => $vendorProfile->id,
            'name' => 'MegaTech Plaza',
            'slug' => 'megatech-plaza',
            'address' => '10 Aka Road, Uyo',
            'phone' => '+2348039999999',
            'lga_id' => $uyo->id,
            'state_id' => $uyo->state_id,
            'country_id' => $country->id,
            'pickup_enabled' => true,
            'is_primary_branch' => true,
            'is_active' => true,
        ]);

        // 3. Customer
        $customer = User::create([
            'name' => 'Doorstep Customer',
            'email' => 'customer@doorstep.ng',
            'phone' => '+2348011223344',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        // 4. Order in "out_for_delivery" state
        $order = Order::create([
            'order_number' => 'VM-ORD-9081',
            'customer_id' => $customer->id,
            'vendor_id' => $vendorProfile->id,
            'shop_id' => $shop->id,
            'rider_id' => $rider->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'origin_lga_id' => $uyo->id,
            'destination_lga_id' => $uyo->id,
            'delivery_address' => '15 Brooks Street, Uyo',
            'recipient_phone' => '+2348011223344',
            'recipient_name' => 'Doorstep Customer',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '500.00',
            'grand_total' => '10500.00',
            'customer_cashback_5' => '500.00',
            'vendor_net_90' => '9000.00',
            'logistics_fee_retained' => '450.00',
            'platform_margin_5' => '500.00',
            'platform_delivery_fee' => '50.00',
            'verification_code' => '883921',
            'pickup_verification_code' => '482910',
        ]);

        // Step 1: Customer checks live tracking before rider arrival
        Sanctum::actingAs($customer);
        $resp = $this->getJson("/api/v1/customer/orders/{$order->order_number}");
        $resp->assertStatus(200);
        $resp->assertJsonPath('data.has_rider_arrived', false);
        $resp->assertJsonPath('data.delivery_otp', '883921');

        // Step 2: Rider arrives at doorstep and presses "I'm Here"
        Sanctum::actingAs($riderUser);
        $arrivedResp = $this->postJson('/api/v1/rider/orders/mark-arrived', [
            'order_id' => $order->id,
        ]);
        $arrivedResp->assertStatus(200);
        $arrivedResp->assertJsonPath('status', true);

        // Step 3: Customer live tracking now reflects arrival (has_rider_arrived=true, tracking_stage=4)
        Sanctum::actingAs($customer);
        $trackingResp = $this->getJson("/api/v1/customer/orders/{$order->order_number}");
        $trackingResp->assertStatus(200);
        $trackingResp->assertJsonPath('data.has_rider_arrived', true);
        $trackingResp->assertJsonPath('data.tracking_stage', 4);

        // Step 4: Customer enters the 6-digit code on their phone to finalize delivery
        $confirmResp = $this->postJson("/api/v1/customer/orders/{$order->order_number}/confirm-delivery", [
            'delivery_otp' => '883921',
        ]);
        $confirmResp->assertStatus(200);
        $confirmResp->assertJsonPath('status', true);

        // Step 5: Verify Order is now Delivered and Escrow finalized
        $order->refresh();
        $this->assertEquals('delivered', $order->order_status);
        $this->assertTrue((bool)$order->delivery_confirmed_by_customer);
    }
}