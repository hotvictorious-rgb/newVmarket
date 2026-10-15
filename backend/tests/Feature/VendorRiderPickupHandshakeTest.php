<?php

namespace Tests\Feature;

use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
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

class VendorRiderPickupHandshakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Helper to create merchant shop and vendor
     */
    protected function createMerchantWithShop(string $name, string $email, string $phone): array
    {
        $lga = Lga::where('name', 'Uyo')->first();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => bcrypt('Password123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $vendor = VendorProfile::create([
            'user_id' => $user->id,
            'business_name' => $name . ' Enterprises',
            'business_phone' => $phone,
            'support_email' => $email,
            'verification_status' => 'verified',
        ]);

        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'name' => $name . ' Plaza Shop',
            'slug' => \Illuminate\Support\Str::slug($name . ' Plaza Shop'),
            'address' => 'Plaza Block 4, Uyo',
            'country_id' => $lga->state->country_id ?? 1,
            'state_id' => $lga->state_id,
            'lga_id' => $lga->id,
            'is_active' => true,
        ]);

        return [$user, $vendor, $shop];
    }

    /**
     * Helper to create active delivery order
     */
    protected function createOrder(VendorProfile $vendor, Shop $shop, string $orderNum, string $status = 'processing'): Order
    {
        $customer = User::where('user_type', 'customer')->first() ?? User::factory()->create(['user_type' => 'customer']);
        $lga = Lga::where('name', 'Uyo')->first();

        return Order::create([
            'order_number' => $orderNum,
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'origin_lga_id' => $lga->id,
            'destination_lga_id' => $lga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => $status,
            'payment_status' => 'paid',
            'merchandise_subtotal' => 20000.00,
            'delivery_fee' => 1500.00,
            'grand_total' => 21500.00,
            'vendor_net_90' => 18000.00,
            'customer_cashback_5' => 1000.00,
            'platform_margin_5' => 1000.00,
            'logistics_fee_retained' => 1350.00,
            'platform_delivery_fee' => 150.00,
            'pickup_verification_code' => Order::generateUniquePickupOtp(),
            'verification_code' => Order::generateUniqueDeliveryOtp(),
        ]);
    }

    /**
     * Mathematical Non-Collision Proof:
     * Generates 50 sequential active pickup OTPs and proves zero collisions across all active orders.
     */
    public function test_order_pickup_otp_uniqueness_guarantee_eliminates_active_collisions(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Collision Vendor', 'collision@example.com', '08099887766');

        $otps = [];
        for ($i = 1; $i <= 50; $i++) {
            $order = $this->createOrder($vendor, $shop, "VM-COLLISION-{$i}");
            $this->assertNotEmpty($order->pickup_verification_code);
            $this->assertEquals(6, strlen($order->pickup_verification_code));
            $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $order->pickup_verification_code);
            $otps[] = $order->pickup_verification_code;
        }

        // Mathematical assertion: every single active OTP is strictly distinct (injectivity guarantee)
        $uniqueOtps = array_unique($otps);
        $this->assertCount(50, $uniqueOtps, 'Mathematical collision detected in active pickup OTP generation!');
    }

    /**
     * Mathematical Non-Interference Proof:
     * Order A's pickup OTP can NEVER pick up Order B.
     */
    public function test_multi_vendor_pickup_cannot_cross_pickup_with_other_orders_code(): void
    {
        [$vendorUserA, $vendorA, $shopA] = $this->createMerchantWithShop('Vendor Alpha', 'alpha@example.com', '08011110000');
        [$vendorUserB, $vendorB, $shopB] = $this->createMerchantWithShop('Vendor Beta', 'beta@example.com', '08022220000');

        $orderA = $this->createOrder($vendorA, $shopA, 'VM-ORDER-ALPHA');
        $orderB = $this->createOrder($vendorB, $shopB, 'VM-ORDER-BETA');

        $riderUser = User::where('user_type', 'rider')->first() ?? User::factory()->create(['user_type' => 'rider']);
        $rider = RiderProfile::where('user_id', $riderUser->id)->first() ?? RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => 1,
            'assigned_lga_id' => $orderA->origin_lga_id,
            'is_active' => true,
            'is_online' => true,
        ]);

        $this->assertNotEquals($orderA->pickup_verification_code, $orderB->pickup_verification_code);

        // Attempt to pick up Order B using Order A's OTP on mobile
        $response = $this->actingAs($riderUser)->postJson('/rider/verify-pickup-otp', [
            'order_id' => $orderB->id,
            'pickup_otp' => $orderA->pickup_verification_code,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid merchant pickup verification OTP. Please verify with counter staff.',
        ]);

        // Assert Order B remains unpicked and unchanged
        $orderB->refresh();
        $this->assertEquals('processing', $orderB->order_status);
        $this->assertFalse($orderB->pickup_confirmed_by_rider);
        $this->assertNull($orderB->picked_up_at);
        $this->assertNull($orderB->pickup_proof_image);
    }

    /**
     * Vendor Handshake Readiness Trigger:
     * Vendor clicks "Rider Arrived / Handover Ready" to reveal code and authorize handover.
     */
    public function test_vendor_can_mark_handover_ready_revealing_pickup_otp(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Merchant Lead', 'lead@example.com', '08033331111');
        $order = $this->createOrder($vendor, $shop, 'VM-HANDOVER-001');

        $this->assertNull($order->vendor_handover_ready_at);
        $this->assertFalse($order->isVendorHandoverReady());

        $response = $this->actingAs($vendorUser)->postJson("/vendor/orders/{$order->id}/ready-for-handover");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'pickup_otp' => $order->pickup_verification_code,
        ]);

        $order->refresh();
        $this->assertNotNull($order->vendor_handover_ready_at);
        $this->assertEquals('ready_for_pickup', $order->order_status);
        $this->assertTrue($order->isVendorHandoverReady());
    }

    /**
     * Rider Handshake on Mobile with Optional Photo Capture:
     * Rider inputs correct PIN and uploads package photo proof.
     */
    public function test_rider_verifies_pickup_on_phone_with_optional_photo(): void
    {
        Storage::fake('public');

        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Gadget Mart', 'gadget@example.com', '08055552222');
        $order = $this->createOrder($vendor, $shop, 'VM-PICKUP-PHOTO-01');

        $riderUser = User::where('user_type', 'rider')->first() ?? User::factory()->create(['user_type' => 'rider']);
        $rider = RiderProfile::where('user_id', $riderUser->id)->first() ?? RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => 1,
            'assigned_lga_id' => $order->origin_lga_id,
            'is_active' => true,
            'is_online' => true,
        ]);

        $photo = UploadedFile::fake()->image('counter_parcel.jpg', 600, 600);

        $response = $this->actingAs($riderUser)->postJson('/rider/verify-pickup-otp', [
            'order_id' => $order->id,
            'pickup_otp' => $order->pickup_verification_code,
            'proof_photo' => $photo,
            'pickup_notes' => 'Parcel seal verified intact at plaza counter.',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Pickup verified successfully. Order is out for delivery.',
        ]);

        $order->refresh();
        $this->assertEquals('out_for_delivery', $order->order_status);
        $this->assertTrue($order->pickup_confirmed_by_rider);
        $this->assertNotNull($order->picked_up_at);
        $this->assertEquals($rider->id, $order->rider_id);
        $this->assertNotNull($order->pickup_proof_image);
        $this->assertStringContainsString('/storage/pickup_proofs/', $order->pickup_proof_image);
        $this->assertEquals('Parcel seal verified intact at plaza counter.', $order->pickup_notes);
    }

    /**
     * Rider Pickup Without Photo:
     * Photo is strictly optional; pickup succeeds seamlessly without photo upload.
     */
    public function test_rider_verifies_pickup_without_photo(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('NoPhoto Mart', 'nophoto@example.com', '08077773333');
        $order = $this->createOrder($vendor, $shop, 'VM-NO-PHOTO-01');

        $riderUser = User::where('user_type', 'rider')->first() ?? User::factory()->create(['user_type' => 'rider']);
        $rider = RiderProfile::where('user_id', $riderUser->id)->first() ?? RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => 1,
            'assigned_lga_id' => $order->origin_lga_id,
            'is_active' => true,
            'is_online' => true,
        ]);

        $response = $this->actingAs($riderUser)->postJson('/rider/verify-pickup-otp', [
            'order_id' => $order->id,
            'pickup_otp' => $order->pickup_verification_code,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $order->refresh();
        $this->assertEquals('out_for_delivery', $order->order_status);
        $this->assertTrue($order->pickup_confirmed_by_rider);
        $this->assertNull($order->pickup_proof_image);
    }

    /**
     * Monotonic State Transition / Idempotency Burn Barrier:
     * Once an order is picked up, re-submitting the same OTP is rejected.
     */
    public function test_picked_up_order_is_idempotent_and_rejects_subsequent_pickup_attempts(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Burn Mart', 'burn@example.com', '08088884444');
        $order = $this->createOrder($vendor, $shop, 'VM-BURN-OTP-01');

        $riderUser = User::where('user_type', 'rider')->first() ?? User::factory()->create(['user_type' => 'rider']);
        $rider = RiderProfile::where('user_id', $riderUser->id)->first() ?? RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => 1,
            'assigned_lga_id' => $order->origin_lga_id,
            'is_active' => true,
            'is_online' => true,
        ]);

        // First attempt succeeds
        $res1 = $this->actingAs($riderUser)->postJson('/rider/verify-pickup-otp', [
            'order_id' => $order->id,
            'pickup_otp' => $order->pickup_verification_code,
        ]);
        $res1->assertStatus(200);

        // Second attempt with exact same OTP fails
        $res2 = $this->actingAs($riderUser)->postJson('/rider/verify-pickup-otp', [
            'order_id' => $order->id,
            'pickup_otp' => $order->pickup_verification_code,
        ]);
        $res2->assertStatus(422);
        $res2->assertJson([
            'success' => false,
            'message' => 'Order cannot be picked up in status out_for_delivery.',
        ]);
    }

    /**
     * Anti-Brute-Force Rate Limiting Shield:
     * 5 consecutive incorrect OTP attempts triggers brute force lockout.
     */
    public function test_anti_brute_force_shield_locks_verification_after_five_failures(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Shield Mart', 'shield@example.com', '08099995555');
        $order = $this->createOrder($vendor, $shop, 'VM-SHIELD-01');

        $riderUser = User::where('user_type', 'rider')->first() ?? User::factory()->create(['user_type' => 'rider']);
        $rider = RiderProfile::where('user_id', $riderUser->id)->first() ?? RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => 1,
            'assigned_lga_id' => $order->origin_lga_id,
            'is_active' => true,
            'is_online' => true,
        ]);

        // Send 5 incorrect attempts
        for ($i = 1; $i <= 5; $i++) {
            $res = $this->actingAs($riderUser)->postJson('/rider/verify-pickup-otp', [
                'order_id' => $order->id,
                'pickup_otp' => '000000',
            ]);
            $res->assertStatus(422);
        }

        $order->refresh();
        $this->assertEquals(5, $order->pickup_failed_attempts);

        // 6th attempt (even if correct!) is locked out by brute-force shield
        $res6 = $this->actingAs($riderUser)->postJson('/rider/verify-pickup-otp', [
            'order_id' => $order->id,
            'pickup_otp' => $order->pickup_verification_code,
        ]);

        $res6->assertStatus(422);
        $res6->assertJson([
            'success' => false,
            'message' => 'Pickup verification temporarily locked due to 5 consecutive incorrect attempts. Please consult merchant counter staff.',
        ]);
    }

    /**
     * Live Polling Endpoints:
     * Verifies real-time status reporting for both Rider and Vendor screens.
     */
    public function test_live_polling_endpoints_reflect_handshake_state(): void
    {
        [$vendorUser, $vendor, $shop] = $this->createMerchantWithShop('Poll Mart', 'poll@example.com', '08066661111');
        $order = $this->createOrder($vendor, $shop, 'VM-POLL-01');

        $riderUser = User::where('user_type', 'rider')->first() ?? User::factory()->create(['user_type' => 'rider']);
        $rider = RiderProfile::where('user_id', $riderUser->id)->first() ?? RiderProfile::create([
            'user_id' => $riderUser->id,
            'logistics_company_id' => 1,
            'assigned_lga_id' => $order->origin_lga_id,
            'is_active' => true,
            'is_online' => true,
        ]);

        // 1. Initial State
        $pollRes = $this->actingAs($riderUser)->getJson("/rider/orders/{$order->order_number}/pickup-status");
        $pollRes->assertStatus(200);
        $pollRes->assertJson([
            'order_number' => $order->order_number,
            'is_handover_ready' => false,
            'is_picked_up' => false,
        ]);

        // 2. Vendor marks ready
        $order->vendor_handover_ready_at = Carbon::now();
        $order->save();

        $pollRes2 = $this->actingAs($riderUser)->getJson("/rider/orders/{$order->order_number}/pickup-status");
        $pollRes2->assertStatus(200);
        $pollRes2->assertJson([
            'is_handover_ready' => true,
            'is_picked_up' => false,
        ]);

        // 3. Rider completes pickup
        $order->order_status = 'out_for_delivery';
        $order->pickup_confirmed_by_rider = true;
        $order->picked_up_at = Carbon::now();
        $order->rider_id = $rider->id;
        $order->save();

        // Vendor polls
        $vendorPoll = $this->getJson("/order/{$order->order_number}/pickup-handshake-status");
        $vendorPoll->assertStatus(200);
        $vendorPoll->assertJson([
            'order_number' => $order->order_number,
            'is_picked_up' => true,
            'rider_name' => $riderUser->name,
        ]);
    }
}
