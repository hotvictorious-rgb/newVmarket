<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\DeliveryLane;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StructuralEntityAndLogisticsInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendorUser;
    protected VendorProfile $vendor;
    protected Shop $shop;
    protected Country $country;
    protected State $state;
    protected Lga $lga;
    protected User $customer;
    protected User $riderUser;
    protected RiderProfile $rider;

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
            'code' => 'AK',
            'is_active' => true,
        ]);
        $this->lga = Lga::create([
            'state_id' => $this->state->id,
            'name' => 'Uyo',
            'code' => 'UYO',
            'is_active' => true,
        ]);

        // Vendor Profile
        $this->vendorUser = User::create([
            'name' => 'Merchant Test User',
            'email' => 'merchant@test.com',
            'phone' => '+2348011223344',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->vendor = VendorProfile::create([
            'user_id' => $this->vendorUser->id,
            'business_name' => 'Apex Electronics Hub',
            'contact_phone' => '+2348011223344',
            'marketplace_status' => 'active',
            'bank_name' => 'Zenith Bank',
            'account_number' => '1000000001',
            'account_name' => 'Apex Electronics Hub',
        ]);

        // Shop belonging to Vendor
        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Apex Plaza Branch',
            'slug' => 'apex-plaza-branch',
            'address' => '10 Oron Road, Uyo',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        // Customer
        $this->customer = User::create([
            'name' => 'Customer Shopper',
            'email' => 'shopper@test.com',
            'phone' => '+2348099887766',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        // Delivery Courier (Rider)
        $this->riderUser = User::create([
            'name' => 'Rider Dispatcher',
            'email' => 'rider@speedlogistics.ng',
            'phone' => '+2348033445566',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);
        $this->rider = RiderProfile::create([
            'user_id' => $this->riderUser->id,
            'vendor_id' => $this->vendor->id,
            'assigned_lga_id' => $this->lga->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
            'is_online' => true,
        ]);
    }








            /**
     * PROOF 18: Recommended Hardening - Customer account and Product marketplace freshness guards.
     */
    public function test_recommended_hardening_customer_account_and_product_freshness_guards(): void
    {
        $category = \App\Models\Category::firstOrCreate(
            ['slug' => 'audio'],
            ['name' => 'Audio', 'is_active' => true]
        );

        $product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $category->id,
            'name' => 'Studio Monitor Earphones',
            'slug' => 'studio-monitor-earphones-' . uniqid(),
            'unit_price' => '45000.00',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now(),
        ]);

        $address = CustomerAddress::create([
            'user_id' => $this->customer->id,
            'contact_name' => 'John Customer',
            'phone' => '+2348011223344',
            'address_line' => '44 Wellington Bassey Way',
            'country_id' => $this->country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
        ]);

        Sanctum::actingAs($this->customer, ['*']);

        // Case A: Customer account is suspended -> HTTP 403 Forbidden
        $this->customer->status = 'suspended';
        $this->customer->save();

        $resSuspendedCustomer = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resSuspendedCustomer->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Your customer account is deactivated or suspended.');

        // Intent creation by suspended customer
        $resIntentCustomer = $this->postJson('/api/v1/checkout/intent', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resIntentCustomer->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Your customer account is deactivated or suspended.');

        // In-Shop Pickup Reservation by suspended customer
        $resPickupCustomer = $this->postJson('/api/v1/customer/pickup-reservations', [
            'shop_id' => $this->shop->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $resPickupCustomer->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Your customer account is deactivated or suspended.');

        // Restore customer account
        $this->customer->status = 'active';
        $this->customer->save();

        // Case B: Product is soft-deleted -> Checkout rejected (not found in eligible products)
        $product->delete();

        $resProductTrashed = $this->postJson('/api/v1/customer/orders/checkout', [
            'shop_id' => $this->shop->id,
            'customer_address_id' => $address->id,
            'fulfillment_type' => 'doorstep_delivery',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $resProductTrashed->assertStatus(404);
    }
}


