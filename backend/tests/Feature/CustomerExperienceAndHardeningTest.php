<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\CustomerCashbackLedger;
use App\Models\DeliveryLane;
use App\Models\DisputeAndReturn;
use App\Models\Lga;
use App\Models\Order;
use App\Models\PickupReservation;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerExperienceAndHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $customerB;
    protected User $vendorUser;
    protected Shop $shop;
    protected Product $product;
    protected Country $country;
    protected State $state;
    protected Lga $originLga;
    protected Lga $destLga;
    protected CustomerAddress $address;
    protected DeliveryLane $lane;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NGA',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'currency_symbol' => '₦',
            'is_active' => true,
        ]);

        $this->state = State::create([
            'country_id' => $this->country->id,
            'name' => 'Akwa Ibom',
            'state_code' => 'AK',
            'is_active' => true,
        ]);

        $this->originLga = Lga::create([
            'state_id' => $this->state->id,
            'name' => 'Uyo',
            'is_active' => true,
        ]);

        $this->destLga = Lga::create([
            'state_id' => $this->state->id,
            'name' => 'Eket',
            'is_active' => true,
        ]);

        $this->customer = User::create([
            'name' => 'John Customer',
            'email' => 'customer@vmarket.ng',
            'phone' => '+2348011112222',
            'password' => bcrypt('password123'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $this->customerB = User::create([
            'name' => 'Jane Imposter',
            'email' => 'imposter@vmarket.ng',
            'phone' => '+2348099998888',
            'password' => bcrypt('password123'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $this->address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'John Customer',
            'phone' => '+2348011112222',
            'address_line' => '10 Aka Road, Uyo',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->destLga->id,
            'is_default' => true,
        ]);

        $this->vendorUser = User::create([
            'name' => 'Merchant Chief',
            'email' => 'merchant@vmarket.ng',
            'phone' => '+2348033334444',
            'password' => bcrypt('password123'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $vendorProfile = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Akwa Fresh Foods',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $vendorProfile->id,
            'name' => 'Akwa Plaza Store',
            'slug' => 'akwa-plaza-store',
            'address' => '24 Oron Road, Uyo',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->originLga->id,
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $category = Category::create([
            'name' => 'Groceries',
            'slug' => 'groceries',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $vendorProfile->id,
            'category_id' => $category->id,
            'name' => 'Fresh Palm Oil 5L',
            'slug' => 'fresh-palm-oil-5l',
            'unit_price' => '10000.00',
            'inventory_quantity' => 20,
            'is_in_stock' => true,
            'is_active' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
            'freshness_verified_at' => Carbon::now(),
        ]);

        $this->lane = DeliveryLane::create([
            'origin_lga_id' => $this->originLga->id,
            'destination_lga_id' => $this->destLga->id,
            'base_fee' => '1500.00',
            'bulky_surcharge' => '500.00',
            'estimated_delivery_hours_min' => 2,
            'estimated_delivery_hours_max' => 6,
            'is_active' => true,
        ]);
    }

    public function test_cart_centric_fulfillment_availability_resolves_lanes_and_pickups(): void
    {
        $response = $this->postJson('/api/v1/fulfillment/availability', [
            'address_id' => $this->address->id,
            'cart_items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('available', true)
            ->assertJsonPath('fulfillment_options.delivery.available', true)
            ->assertJsonPath('fulfillment_options.delivery.total_shipping_fee', '1500.00')
            ->assertJsonPath('fulfillment_options.delivery.currency', 'NGN')
            ->assertJsonPath('fulfillment_options.in_shop_pickup.available', true);
    }

    public function test_single_lane_delivery_fee_query_succeeds(): void
    {
        $response = $this->postJson('/api/v1/fulfillment/delivery-fee', [
            'shop_id' => $this->shop->id,
            'address_id' => $this->address->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('delivery_fee', '1500.00')
            ->assertJsonPath('currency', 'NGN');
    }

    public function test_customer_address_book_supports_full_crud_and_zero_idor_security(): void
    {
        $token = $this->customer->createToken('test')->plainTextToken;
        $tokenB = $this->customerB->createToken('testB')->plainTextToken;

        // 1. Store with mobile parameter compatibility (contact_person_name, address)
        $addRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/customer/address/add', [
                'contact_person_name' => 'Customer Office',
                'phone' => '+2348011112222',
                'address' => '50 Ikot Ekpene Road',
                'country_id' => $this->country->id,
                'state_id' => $this->state->id,
                'lga_id' => $this->originLga->id,
                'is_default' => false,
            ]);

        $addRes->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.contact_name', 'Customer Office');

        $addressId = $addRes->json('data.id');

        // 2. IDOR Shield: Customer B cannot update Customer A's address
        app('auth')->forgetGuards();
        $idorUpdate = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->postJson("/api/v1/customer/address/update/{$addressId}", [
                'contact_name' => 'Hacked Name',
            ]);
        $idorUpdate->assertStatus(404);

        // 3. Authorized update by Customer A
        app('auth')->forgetGuards();
        $updateRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/customer/address/update/{$addressId}", [
                'contact_person_name' => 'Updated Office Branch',
                'landmark' => 'Opposite Total Filling Station',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.contact_name', 'Updated Office Branch')
            ->assertJsonPath('data.landmark', 'Opposite Total Filling Station');

        // 4. IDOR Shield: Customer B cannot delete Customer A's address
        app('auth')->forgetGuards();
        $idorDelete = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->deleteJson("/api/v1/customer/address/{$addressId}");
        $idorDelete->assertStatus(404);

        // 5. Authorized delete by Customer A
        app('auth')->forgetGuards();
        $deleteRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/v1/customer/address/{$addressId}");
        $deleteRes->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseMissing('customer_addresses', ['id' => $addressId]);
    }

    public function test_cashback_summary_and_list_endpoints_return_correct_contract(): void
    {
        $token = $this->customer->createToken('test')->plainTextToken;

        $order1 = Order::create([
            'order_number' => 'VM-TEST-CB-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->originLga->id,
            'destination_lga_id' => $this->destLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'merchandise_subtotal' => '50000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '51500.00',
            'vendor_net_90' => '45000.00',
            'customer_cashback_5' => '2500.00',
            'platform_margin_5' => '2500.00',
            'vendor_settlement_status' => 'matured',
            'received_at' => Carbon::now()->subDays(2),
            'refund_window_expires_at' => Carbon::now()->subDay(),
            'verification_code' => '112233',
            'pickup_verification_code' => '332211',
        ]);

        $order2 = Order::create([
            'order_number' => 'VM-TEST-CB-02',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->originLga->id,
            'destination_lga_id' => $this->destLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '11500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'vendor_settlement_status' => 'held',
            'received_at' => Carbon::now()->subHours(2),
            'refund_window_expires_at' => Carbon::now()->addHours(22),
            'verification_code' => '445566',
            'pickup_verification_code' => '665544',
        ]);

        // Seed available cashback lot
        CustomerCashbackLedger::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order1->id,
            'amount' => '2500.00',
            'status' => 'available',
            'matures_at' => Carbon::now()->subDay(),
            'expires_at' => Carbon::now()->addYear(),
        ]);

        // Seed pending maturation lot
        CustomerCashbackLedger::create([
            'customer_id' => $this->customer->id,
            'order_id' => $order2->id,
            'amount' => '500.00',
            'status' => 'pending_maturation',
            'matures_at' => Carbon::now()->addHours(24),
            'expires_at' => Carbon::now()->addYear(),
        ]);

        $summaryRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/cashback/summary');

        $summaryRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('summary.points_balance', 2500)
            ->assertJsonPath('summary.equivalent_naira', '2500.00')
            ->assertJsonPath('summary.currency', 'NGN')
            ->assertJsonPath('summary.pending_settlement_points', 500);

        $listRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/cashback/list');

        $listRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_in_shop_pickup_reservation_full_lifecycle_and_pay_after_inspection(): void
    {
        $customerToken = $this->customer->createToken('test')->plainTextToken;
        $vendorToken = $this->vendorUser->createToken('vendor')->plainTextToken;

        // 1. Customer creates in-shop pickup reservation (Inspect First, Pay Later Engine)
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->postJson('/api/v1/customer/pickup-reservations', [
                'shop_id' => $this->shop->id,
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'reserved');

        $code = $createRes->json('data.reservation_code');

        // 2. Customer attempts to pay BEFORE inspection accepted -> strictly rejected
        $prematurePay = $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->postJson("/api/v1/customer/pickup-reservations/{$code}/pay");

        $prematurePay->assertStatus(422)
            ->assertJsonPath('status', false);

        // 3. Customer lists reservations
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->getJson('/api/v1/customer/pickup-reservations');

        $listRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data');

        // 4. Merchant inspects and accepts goods in store
        app('auth')->forgetGuards();
        $inspectRes = $this->withHeader('Authorization', 'Bearer ' . $vendorToken)
            ->postJson('/api/v1/vendor/pickup-reservations/verify-inspection', [
                'reservation_code' => $code,
                'action' => 'accept',
            ]);

        $inspectRes->assertStatus(200)
            ->assertJsonPath('status', true);

        // 5. Customer digital payment is unlocked -> Pays via Paystack
        app('auth')->forgetGuards();
        $initialStock = $this->product->fresh()->inventory_quantity;
        $payRes = $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->postJson("/api/v1/customer/pickup-reservations/{$code}/pay", [
                'use_cashback' => false,
            ]);

        $payRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.reservation_code', $code);

        $this->assertNotNull($payRes->json('data.pickup_otp'));
        $this->assertNotNull($payRes->json('data.order_number'));

        // Binary Stock Model Invariant: Stock quantity is NOT decremented at payment initiation
        $this->assertTrue($this->product->fresh()->is_in_stock);

        // Reservation marked payment_completed and order bound
        $reservation = PickupReservation::where('reservation_code', $code)->first();
        $this->assertEquals('payment_completed', $reservation->status);
        $this->assertNotNull($reservation->order_id);
    }

    public function test_post_delivery_24h_dispute_initiation_and_escrow_freeze(): void
    {
        $token = $this->customer->createToken('test')->plainTextToken;
        $tokenB = $this->customerB->createToken('testB')->plainTextToken;

        // Create a delivered order within the 24-hour return window
        $order = Order::create([
            'order_number' => 'VM-TEST-DISPUTE-01',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->originLga->id,
            'destination_lga_id' => $this->destLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '11500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'vendor_settlement_status' => 'held',
            'received_at' => Carbon::now()->subHours(2),
            'refund_window_expires_at' => Carbon::now()->addHours(22),
            'verification_code' => '123456',
            'pickup_verification_code' => '654321',
        ]);

        // IDOR Shield: Customer B cannot dispute Customer A's order
        app('auth')->forgetGuards();
        $idorDispute = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->postJson("/api/v1/customer/orders/{$order->order_number}/dispute", [
                'reason' => 'damaged_item',
                'customer_description' => 'Container leaked during transit.',
            ]);
        $idorDispute->assertStatus(404);

        // Customer A files valid dispute
        app('auth')->forgetGuards();
        $disputeRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/customer/orders/{$order->order_number}/dispute", [
                'reason' => 'damaged_item',
                'customer_description' => 'Container leaked during transit.',
                'evidence_photos' => ['https://vmarket.ng/storage/evidence1.jpg'],
            ]);

        $disputeRes->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'filed');

        // Vendor settlement status is immediately frozen to 'disputed'
        $this->assertEquals('disputed', $order->fresh()->vendor_settlement_status);

        // Check dispute view
        $viewRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/v1/customer/orders/{$order->order_number}/dispute");

        $viewRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.reason', 'damaged_item');

        // Duplicate dispute filing is rejected
        $dupRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/customer/orders/{$order->order_number}/dispute", [
                'reason' => 'damaged_item',
                'customer_description' => 'Filing again.',
            ]);
        $dupRes->assertStatus(422);
        $this->assertSame(1, DisputeAndReturn::where('order_id', $order->id)->where('customer_id', $this->customer->id)->count(), 'Duplicate filing must not create another dispute record.');
    }

    public function test_auth_profile_update_and_logout(): void
    {
        $token = $this->customer->createToken('test')->plainTextToken;

        // Profile update
        $updateRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/customer/update-profile', [
                'name' => 'John Victorious',
                'avatar' => 'https://vmarket.ng/avatars/user.jpg',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'John Victorious');

        $this->assertEquals('John Victorious', $this->customer->fresh()->name);

        // Logout revokes token
        $logoutRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $logoutRes->assertStatus(200)
            ->assertJsonPath('status', true);

        // Assert token is deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->customer->id,
        ]);
    }

    public function test_order_tracking_alias_returns_5_stage_details(): void
    {
        $token = $this->customer->createToken('test')->plainTextToken;

        $order = Order::create([
            'order_number' => 'VM-TRACK-991',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->shop->vendor_id,
            'shop_id' => $this->shop->id,
            'origin_lga_id' => $this->originLga->id,
            'destination_lga_id' => $this->destLga->id,
            'fulfillment_type' => 'doorstep_delivery',
            'order_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'payment_method' => 'paystack',
            'merchandise_subtotal' => '10000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '11500.00',
            'vendor_net_90' => '9000.00',
            'customer_cashback_5' => '500.00',
            'platform_margin_5' => '500.00',
            'vendor_settlement_status' => 'held',
            'verification_code' => '449911',
            'pickup_verification_code' => '119944',
        ]);

        $trackRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/v1/order/track?order_number={$order->order_number}");

        $trackRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.order_number', 'VM-TRACK-991')
            ->assertJsonPath('data.delivery_otp', '449911')
            ->assertJsonPath('data.tracking_stage', 3);
    }
}
