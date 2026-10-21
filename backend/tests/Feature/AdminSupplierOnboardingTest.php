<?php

namespace Tests\Feature;

use App\Models\Lga;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSupplierOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected State $state;
    protected Lga $uyoLga;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $country = \App\Models\Country::firstOrCreate(
            ['iso_code' => 'NG'],
            ['name' => 'Nigeria', 'currency_code' => 'NGN', 'phone_code' => '+234', 'is_active' => true]
        );

        $this->state = State::create([
            'country_id' => $country->id,
            'name' => 'Akwa Ibom',
            'code' => 'AK',
            'is_active' => true,
        ]);

        $this->uyoLga = Lga::create([
            'state_id' => $this->state->id,
            'name' => 'Uyo',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@victoriousmarket.ng',
            'phone' => '08000000001',
            'user_type' => 'super_admin',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_public_registration_strictly_creates_customer_and_cannot_self_register_as_vendor(): void
    {
        $response = $this->post(route('customer.register.post'), [
            'name' => 'Test Hacker',
            'email' => 'hacker@test.com',
            'phone' => '08099998888',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'vendor', // Attempt to escalate role
            'terms' => 'on',
        ]);

        $createdUser = User::where('email', 'hacker@test.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertEquals('customer', $createdUser->user_type, 'Public registration must never allow vendor/supplier creation');
    }

    public function test_unauthenticated_or_non_admin_cannot_onboard_suppliers(): void
    {
        $payload = [
            'business_name' => 'Unauthorized Plaza',
            'contact_name' => 'John Doe',
            'phone' => '08011112222',
            'shop_name' => 'Unauthorized Shop',
            'shop_address' => '123 Fake Street',
            'lga_id' => $this->uyoLga->id,
        ];

        // Guest attempt
        $guestResponse = $this->post(route('admin.merchants.store'), $payload);
        $guestResponse->assertRedirect();
        $this->assertDatabaseMissing('vendor_profiles', ['business_name' => 'Unauthorized Plaza']);

        // Normal customer attempt
        $customer = User::create([
            'name' => 'Regular Customer',
            'email' => 'cust@test.com',
            'phone' => '08022223333',
            'user_type' => 'customer',
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);

        $authResponse = $this->actingAs($customer)->post(route('admin.merchants.store'), $payload);
        $this->assertTrue(in_array($authResponse->status(), [302, 403]));
        $this->assertDatabaseMissing('vendor_profiles', ['business_name' => 'Unauthorized Plaza']);
    }

    public function test_super_admin_can_successfully_onboard_new_supplier_with_shop(): void
    {
        $payload = [
            'business_name' => 'Emeka Electronics Plaza',
            'contact_name' => 'Chief Emeka',
            'phone' => '08034567890',
            'email' => 'emeka@electronics.ng',
            'password' => 'Wholesale2026!',
            'shop_name' => 'Plaza Main Counter',
            'shop_address' => 'Shop 14, Plaza Line, Ikot Ekpene Road, Uyo',
            'lga_id' => $this->uyoLga->id,
            'bank_name' => 'Zenith Bank',
            'account_number' => '1012345678',
            'account_name' => 'Emeka Electronics Ltd',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('admin.merchants.store'), $payload);

        $response->assertRedirect(route('admin.merchants.index'));
        $response->assertSessionHas('success');

        // Verify User was created as vendor
        $user = User::where('email', 'emeka@electronics.ng')->first();
        $this->assertNotNull($user);
        $this->assertEquals('vendor', $user->user_type);
        $this->assertEquals('Chief Emeka', $user->name);
        $this->assertEquals('08034567890', $user->phone);

        // Verify VendorProfile was created and verified
        $vendor = VendorProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($vendor);
        $this->assertEquals('Emeka Electronics Plaza', $vendor->business_name);
        $this->assertEquals('verified', $vendor->kyc_status);
        $this->assertEquals('Zenith Bank', $vendor->bank_name);
        $this->assertEquals('1012345678', $vendor->account_number);

        // Verify Shop was created in Uyo with active pickup
        $shop = Shop::where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($shop);
        $this->assertEquals('Plaza Main Counter', $shop->name);
        $this->assertEquals($this->uyoLga->id, $shop->lga_id);
        $this->assertTrue((bool)$shop->is_primary_branch);
        $this->assertTrue((bool)$shop->is_active);
        $this->assertTrue((bool)$shop->pickup_enabled);

        // Verify ShopDeliveryCoverage was automatically initialized
        $this->assertDatabaseHas('shop_delivery_coverages', [
            'shop_id' => $shop->id,
            'lga_id' => $this->uyoLga->id,
            'is_active' => 1,
        ]);
    }
}
