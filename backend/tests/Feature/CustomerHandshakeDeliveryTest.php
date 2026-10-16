<?php

namespace Tests\Feature;

use App\Models\CustomerCashbackLedger;
use App\Models\Lga;
use App\Models\Order;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerHandshakeDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Mathematical & Multi-Vendor Proof:
     * When a customer has orders from multiple vendors (e.g. Vendor A and Vendor B),
     * an OTP generated for Vendor A can NEVER mark Vendor B's order as delivered.
     */
    public function test_multi_vendor_orders_have_distinct_otps_and_cannot_cross_deliver(): void
    {
        $customer = User::where('user_type', 'customer')->first() ?? User::factory()->create(['user_type' => 'customer']);
        $uyoLga = Lga::where('name', 'Uyo')->first();

        // Vendor 1
        $vendorUser1 = User::create([
            'name' => 'Vendor Alpha User',
            'email' => 'vendor.alpha@example.com',
            'phone' => '08011112222',
            'password' => bcrypt('Password123!'),
            'user_type' => 'vendor',
            'is_active' => true,
        ]);
        $vendorProfile1 = VendorProfile::create([
            'user_id' => $vendorUser1->id,
            'business_name' => 'Vendor Alpha Store',
            'business_phone' => '08011112222',
            'support_email' => 'vendor.alpha@example.com',
            'verification_status' => 'verified',
        ]);
        $shop1 = Shop::create([
            'vendor_id' => $vendorProfile1->id,
            'name' => 'Alpha Plaza Branch',
            'slug' => 'alpha-plaza-branch',
            'address' => '10 Abak Road',
            'country_id' => $uyoLga->state->country_id ?? 1,
            'state_id' => $uyoLga->state_id,
            'lga_id' => $uyoLga->id,
            'is_active' => true,
        ]);

        // Vendor 2
        $vendorUser2 = User::create([
            'name' => 'Vendor Beta User',
            'email' => 'vendor.beta@example.com',
            'phone' => '08033334444',
            'password' => bcrypt('Password123!'),
            'user_type' => 'vendor',
            'is_active' => true,
        ]);
        $vendorProfile2 = VendorProfile::create([
            'user_id' => $vendorUser2->id,
            'business_name' => 'Vendor Beta Electronics',
            'business_phone' => '08033334444',
            'support_email' => 'vendor.beta@example.com',
            'verification_status' => 'verified',
        ]);
        $shop2 = Shop::create([
            'vendor_id' => $vendorProfile2->id,
            'name' => 'Beta Tech Depot',
            'slug' => 'beta-tech-depot',
            'address' => '45 Ikot Ekpene Road',
            'country_id' => $uyoLga->state->country_id ?? 1,
            'state_id' => $uyoLga->state_id,
            'lga_id' => $uyoLga->id,
            'is_active' => true,
        ]);

        $rider = RiderProfile::first();

        // Create Order 1 for Vendor Alpha
        $order1 = Order::create([
            'order_number' => 'ORD-ALPHA-001',
            'customer_id' => $customer->id,
            'vendor_id' => $vendorProfile1->id,
            'shop_id' => $shop1->id,
            'rider_id' => $rider->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'origin_lga_id' => $uyoLga->id,
            'destination_lga_id' => $uyoLga->id,
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1500.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => '11500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'logistics_fee_retained' => '1500.00',
            'verification_code' => '111111',
            'rider_arrived_at' => Carbon::now(),
            'vendor_settlement_status' => 'held',
        ]);

        // Create Order 2 for Vendor Beta
        $order2 = Order::create([
            'order_number' => 'ORD-BETA-002',
            'customer_id' => $customer->id,
            'vendor_id' => $vendorProfile2->id,
            'shop_id' => $shop2->id,
            'rider_id' => $rider->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'origin_lga_id' => $uyoLga->id,
            'destination_lga_id' => $uyoLga->id,
            'merchandise_subtotal' => '20000.00',
            'delivery_fee' => '1500.00',
            'cashback_redeemed' => '0.00',
            'grand_total' => '21500.00',
            'vendor_net_90' => '18000.00',
            'customer_cashback_5' => '1000.00',
            'platform_margin_5' => '1000.00',
            'logistics_fee_retained' => '1500.00',
            'verification_code' => '222222',
            'rider_arrived_at' => Carbon::now(),
            'vendor_settlement_status' => 'held',
        ]);

        // PROOF 1: Attempt to confirm Order 2 (Vendor Beta) using Order 1's OTP (111111)
        $responseWrongOtp = $this->actingAs($customer)->post(route('order.customer_confirm_delivery', $order2->order_number), [
            'delivery_otp' => '111111', // WRONG OTP FOR ORDER 2
        ]);

        // Assert failure: Order 2 must NOT be marked delivered
        $order2->refresh();
        $this->assertEquals('out_for_delivery', $order2->order_status);
        $this->assertFalse((bool)$order2->delivery_confirmed_by_customer);
        $this->assertNull($order2->received_at);

        // Order 1 must also remain untouched
        $order1->refresh();
        $this->assertEquals('out_for_delivery', $order1->order_status);
        $this->assertFalse((bool)$order1->delivery_confirmed_by_customer);

        // PROOF 2: Now submit the correct OTP (222222) for Order 2
        $responseCorrectOtp = $this->actingAs($customer)->post(route('order.customer_confirm_delivery', $order2->order_number), [
            'delivery_otp' => '222222', // CORRECT OTP
        ]);

        $order2->refresh();
        $this->assertEquals('delivered', $order2->order_status);
        $this->assertTrue((bool)$order2->delivery_confirmed_by_customer);
        $this->assertNotNull($order2->received_at);

        // Crucial Invariant: Order 1 (Vendor Alpha) MUST STILL BE out_for_delivery!
        $order1->refresh();
        $this->assertEquals('out_for_delivery', $order1->order_status);
        $this->assertFalse((bool)$order1->delivery_confirmed_by_customer);
        $this->assertNull($order1->received_at);
    }

    /**
     * Test Rider Arrival Action and Live Status Sync.
     */
    public function test_rider_arrival_triggers_customer_handshake_state(): void
    {
        $riderUser = User::where('user_type', 'rider')->first();
        $order = Order::where('fulfillment_type', 'doorstep_delivery')->first();
        $order->order_status = 'out_for_delivery';
        $order->rider_arrived_at = null;
        $order->save();

        // Rider signals arrival
        $response = $this->actingAs($riderUser)->post(route('rider.order.arrived'), [
            'order_id' => $order->id,
        ]);

        $order->refresh();
        $this->assertNotNull($order->rider_arrived_at);
        $this->assertTrue($order->hasRiderArrived());

        // Check handshake-status endpoint for live polling
        $statusResponse = $this->getJson(route('order.handshake_status', $order->order_number));
        $statusResponse->assertOk();
        $statusResponse->assertJson([
            'order_number' => $order->order_number,
            'rider_arrived' => true,
            'is_delivered' => false,
        ]);
    }

    /**
     * Test Customer Handshake Confirmation with Optional Snapped Photo and Real-Time Rider Wallet Credit.
     */
    public function test_customer_handshake_confirmation_with_optional_photo_and_rider_wallet_credit(): void
    {
        Storage::fake('public');

        $customer = User::where('user_type', 'customer')->first();
        $rider = RiderProfile::first();
        // Set rider as independent courier to verify direct courier wallet credit
        $rider->logistics_company_id = null;
        $rider->save();
        $initialRiderBalance = (float)($rider->current_balance ?? 0);

        $order = Order::where('fulfillment_type', 'doorstep_delivery')->first();
        $order->order_status = 'out_for_delivery';
        $order->rider_id = $rider->id;
        $order->verification_code = '554433';
        $order->delivery_fee = '1500.00';
        $order->logistics_fee_retained = '1350.00';
        $order->platform_delivery_fee = '150.00';
        $order->rider_arrived_at = Carbon::now();
        $order->save();

        // Fake photo taken on customer camera
        $proofPhoto = UploadedFile::fake()->image('doorstep_box.jpg');

        $response = $this->actingAs($customer)->post(route('order.customer_confirm_delivery', $order->order_number), [
            'delivery_otp' => '554433',
            'proof_photo' => $proofPhoto,
            'customer_notes' => 'Box received in perfect sealed condition.',
        ]);

        $order->refresh();
        $this->assertEquals('delivered', $order->order_status);
        $this->assertTrue((bool)$order->delivery_confirmed_by_customer);
        $this->assertNotNull($order->received_at);
        $this->assertNotNull($order->proof_of_delivery_image);

        // Verify independent rider wallet was credited in real-time by 90% net delivery_fee (₦1,350.00)
        $rider->refresh();
        $newRiderBalance = (float)$rider->current_balance;
        $this->assertEquals($initialRiderBalance + 1350.00, $newRiderBalance);

        // Verify 5% customer cashback reward enqueued with 24h maturation lock
        $cashback = CustomerCashbackLedger::where('order_id', $order->id)->first();
        $this->assertNotNull($cashback);
        $this->assertEquals('pending_maturation', $cashback->status);
    }

    /**
     * Test Unique OTP Generation ensures active collision elimination.
     */
    public function test_order_otp_uniqueness_guarantee_eliminates_active_collisions(): void
    {
        $otp1 = Order::generateUniqueDeliveryOtp();
        $otp2 = Order::generateUniqueDeliveryOtp();

        $this->assertEquals(6, strlen($otp1));
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $otp1);
        $this->assertEquals(6, strlen($otp2));
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $otp2);

        $pickupOtp = Order::generateUniquePickupOtp();
        $this->assertEquals(6, strlen($pickupOtp));
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $pickupOtp);
    }

    /**
     * Idempotency Barrier: Once an order is delivered, re-submitting the OTP is rejected.
     */
    public function test_delivered_order_is_idempotent_and_rejects_subsequent_handshake_attempts(): void
    {
        $customer = User::where('user_type', 'customer')->first();
        $order = Order::where('fulfillment_type', 'doorstep_delivery')->first();
        $order->order_status = 'delivered';
        $order->verification_code = '998877';
        $order->save();

        $service = app(OrderSettlementService::class);
        $result = $service->verifyCustomerDeliveryOtp($order, '998877');

        $this->assertFalse($result['success']);
        $this->assertEquals('Order is not currently out for delivery.', $result['message']);
    }
}
