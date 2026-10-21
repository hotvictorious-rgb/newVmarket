<?php

namespace Tests\Feature;

use App\Models\DeliveryLane;
use App\Models\DeliveryCorridor;
use App\Models\LogisticsCompanyCorridorRate;
use App\Models\LogisticsCompanyProfile;
use App\Models\SystemSetting;
use App\Models\Lga;
use App\Models\User;
use App\Services\DemoAccountRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    public function test_customer_storefront_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Victorious MARKET');
        $response->assertSee('Latest Products in Market');
        $response->assertSee('Seamless Shopping, Swift Logistics');
    }

    public function test_super_admin_dashboard_loads_successfully_when_authenticated(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('Platform Sovereign Command Tower');
        $response->assertSee('Manual Settlement Desk');
        $response->assertSee('Storefront Announcement &amp; Support Control Center', false);
    }

    public function test_admin_staff_dashboard_loads_successfully_when_authenticated(): void
    {
        $staff = User::where('user_type', 'admin_employee')->first();
        $this->assertNotNull($staff);

        $response = $this->actingAs($staff)->get('/admin-staff');
        $response->assertStatus(200);
        $response->assertSee('Operations, Moderation &amp; Support Desk', false);
        $response->assertSee('Product Catalog Moderation &amp; 7-Day Freshness Queue', false);
    }

    public function test_vendor_merchant_dashboard_loads_successfully_when_authenticated(): void
    {
        $vendor = User::where('user_type', 'vendor')->first();
        $this->assertNotNull($vendor);

        $response = $this->actingAs($vendor)->get('/vendor');
        $response->assertStatus(200);
        $response->assertSee('Merchant Commerce Suite');
        $response->assertSee('Gross Merchant Sales');
        $response->assertSee('90% Payout Bank Account');
    }

    public function test_branch_counter_dashboard_loads_successfully_when_authenticated(): void
    {
        $counter = User::where('user_type', 'vendor_employee')->first();
        $this->assertNotNull($counter);

        $response = $this->actingAs($counter)->get('/counter');
        $response->assertStatus(200);
        $response->assertSee('In-Shop Inspection &amp; Handover Terminal', false);
        $response->assertSee('Physical Inspection Sign-Off Desk');
        $response->assertSee('Rider Handover Custody Verification');
    }

    public function test_logistics_fleet_dashboard_loads_successfully_when_authenticated(): void
    {
        $logistics = User::where('user_type', 'logistics_company')->first();
        $this->assertNotNull($logistics);

        $response = $this->actingAs($logistics)->get('/logistics');
        $response->assertStatus(200);
        $response->assertSee('Fleet Logistics Operations Center');
        $response->assertSee('Directional LGA Waybills Manifest');
        $response->assertSee('90% Delivery Fee Wallet');
    }

    public function test_delivery_rider_dashboard_loads_successfully_when_authenticated(): void
    {
        $rider = User::where('user_type', 'rider')->first();
        $this->assertNotNull($rider);

        $response = $this->actingAs($rider)->get('/rider');
        $response->assertStatus(200);
        $response->assertSee('Delivery Rider Dispatch Cockpit');
        $response->assertSee('STEP 1: PLAZA COUNTER PICKUP');
        $response->assertSee('STEP 2: CUSTOMER DOORSTEP HANDSHAKE');
    }

    public function test_customer_shopper_dashboard_loads_successfully_when_authenticated(): void
    {
        $customer = User::where('user_type', 'customer')->first();
        $this->assertNotNull($customer);

        $response = $this->actingAs($customer)->get('/customer');
        $response->assertStatus(200);
        $response->assertSee('My Profile &amp; Account Suite', false);
        $response->assertSee('Live Order Multi-Stage Tracking');
        $response->assertSee('5% Sovereign Cashback Rewards Wallet');
        $response->assertSee('5% Victorious Cashback Guarantee');
    }

    public function test_super_admin_delivery_lanes_page_loads_successfully(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        $response = $this->actingAs($admin)->get('/admin/delivery-lanes');
        $response->assertStatus(200);
        $response->assertSee('Directional LGA Delivery Lanes &amp; 3-Tier Distance Matrix', false);
        $response->assertSee('Tier 1: Municipal (Intra-LGA)');
        $response->assertSee('Tier 2: Regional (Inter-LGA)');
        $response->assertSee('Tier 3: Inter-State Freight');
        $response->assertSee('id="vmAllLgasData"', false);
        $response->assertSee('id="vmHubSwitcherInput"', false);
        $response->assertSee('id="vmInterLgaTable"', false);
        $response->assertSee('id="vmOutOfStateTable"', false);
        $response->assertSee('id="vmCorridorModalOverlay"', false);
    }

    public function test_super_admin_can_store_only_uyo_to_uyo_delivery_lane(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        $origin = Lga::where('name', 'Uyo')->firstOrFail();
        $dest = $origin;

        $postData = [
            'origin_lga_id' => $origin->id,
            'destination_lga_id' => $dest->id,
            'base_fee' => '1350.00',
            'bulky_surcharge' => '1200.00',
            'estimated_delivery_hours_min' => 4,
            'estimated_delivery_hours_max' => 12,
            'is_bidirectional' => '1',
        ];

        $response = $this->actingAs($admin)->post('/admin/delivery-lanes', $postData);
        $response->assertRedirect(route('admin.delivery_lanes.index', ['hub_lga_id' => $origin->id]));

        $this->assertDatabaseHas('delivery_lanes', [
            'origin_lga_id' => $origin->id,
            'destination_lga_id' => $dest->id,
            'base_fee' => '1350.00',
        ]);
    }

    public function test_super_admin_settlements_page_loads_successfully(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        $response = $this->actingAs($admin)->get('/admin/settlements');
        $response->assertStatus(200);
        $response->assertSee('90/5/5 Sovereign Escrow &amp; Settlement Desk', false);
        $response->assertSee('Sovereign 90/5/5 Mathematical Split Formula');
    }

    public function test_super_admin_announcements_page_loads_successfully(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        $response = $this->actingAs($admin)->get('/admin/announcements');
        $response->assertStatus(200);
        $response->assertSee('Storefront Announcement Bar &amp; Customer Care Desk', false);
    }

    public function test_super_admin_merchants_page_loads_successfully(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        $response = $this->actingAs($admin)->get('/admin/merchants');
        $response->assertStatus(200);
        $response->assertSee('Merchant Accreditation &amp; KYC Governance Desk', false);
    }

    public function test_super_admin_orders_page_loads_successfully(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        $response = $this->actingAs($admin)->get('/admin/orders');
        $response->assertStatus(200);
        $response->assertSee('Platform Escrow Orders &amp; Fulfillment Ledger', false);
    }

    /**
     * SECURITY SUITE: Zero-Trust RBAC & IDOR Barrier Tests
     */
    public function test_unauthenticated_requests_are_redirected_to_respective_login_portals(): void
    {
        $this->get('/admin')->assertStatus(302)->assertRedirect(route('admin.login'));
        $this->get('/admin-staff')->assertStatus(302)->assertRedirect(route('admin.staff.login'));
        $this->get('/vendor')->assertStatus(302)->assertRedirect(route('vendor.login'));
        $this->get('/counter')->assertStatus(302)->assertRedirect(route('counter.login'));
        $this->get('/logistics')->assertStatus(302)->assertRedirect(route('logistics.login'));
        $this->get('/rider')->assertStatus(302)->assertRedirect(route('rider.login'));
        $this->get('/customer')->assertStatus(302)->assertRedirect(route('customer.login'));
    }

    public function test_cross_role_access_is_blocked_with_403_role_access_barrier(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        
        // Super admin attempting to access customer portal must get 403 barrier (no customer PII leak)
        $response = $this->actingAs($admin)->get('/customer');
        $response->assertStatus(403);
        $response->assertSee('Role Access Barrier');
        $response->assertSee('HTTP 403 • ZERO-TRUST ISOLATION');

        // Customer attempting to access super admin command center must get 403 barrier
        $customer = User::where('user_type', 'customer')->first();
        $responseCustomer = $this->actingAs($customer)->get('/admin');
        $responseCustomer->assertStatus(403);
        $responseCustomer->assertSee('Role Access Barrier');
    }

    public function test_super_admin_login_page_has_zero_demo_accounts_and_is_configured_from_env(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
        $response->assertDontSee('Demo Personas');
        $response->assertDontSee('demo.');
        $response->assertSee('ENV Configuration Notice');

        $superAdmin = User::where('user_type', 'super_admin')->first();
        $this->assertFalse((bool)$superAdmin->is_demo, 'Super Admin must NEVER be marked as a demo account');
    }

    public function test_role_login_pages_display_3_clickable_demo_accounts_when_demo_enabled(): void
    {
        // 1. Customer login
        $resCustomer = $this->get('/customer/login');
        $resCustomer->assertStatus(200);
        $resCustomer->assertSee('Demo Personas (3 Available)');
        $resCustomer->assertSee('demo.customer1@victoriousmarket.com.ng');
        $resCustomer->assertSee('demo.customer2@victoriousmarket.com.ng');
        $resCustomer->assertSee('demo.customer3@victoriousmarket.com.ng');

        // 2. Vendor login
        $resVendor = $this->get('/vendor/login');
        $resVendor->assertStatus(200);
        $resVendor->assertSee('Demo Personas (3 Available)');
        $resVendor->assertSee('demo.vendor1@victoriousmarket.com.ng');
        $resVendor->assertSee('demo.vendor2@victoriousmarket.com.ng');
        $resVendor->assertSee('demo.vendor3@victoriousmarket.com.ng');
    }

    public function test_demo_quick_login_authenticates_demo_persona(): void
    {
        $response = $this->post('/demo/quick-login', [
            'email' => 'demo.customer1@victoriousmarket.com.ng',
            'role' => 'customer',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticated();
        $this->assertEquals('demo.customer1@victoriousmarket.com.ng', auth()->user()->email);
        $this->assertTrue((bool)auth()->user()->is_demo);
    }

    public function test_demo_quick_login_strictly_rejects_super_admin_attempts(): void
    {
        $response = $this->post('/demo/quick-login', [
            'email' => 'admin@victoriousmarket.com.ng',
            'role' => 'super_admin',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test All 7 Roles Dedicated Navigation & Standalone Pages When Authenticated
     */
    public function test_all_seven_roles_dedicated_standalone_pages_load_successfully(): void
    {
        // 1. Super Admin (/admin)
        $admin = User::where('user_type', 'super_admin')->first();
        $this->actingAs($admin)->get('/admin')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/delivery-lanes')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/settlements')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/announcements')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/merchants')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/orders')->assertStatus(200);

        // 2. Admin Staff (/admin-staff)
        $staff = User::where('user_type', 'admin_employee')->first();
        $this->actingAs($staff)->get('/admin-staff')->assertStatus(200);
        $this->actingAs($staff)->get('/admin-staff/moderation')->assertStatus(200);
        $this->actingAs($staff)->get('/admin-staff/orders')->assertStatus(200);
        $this->actingAs($staff)->get('/admin-staff/inspections')->assertStatus(200);
        $this->actingAs($staff)->get('/admin-staff/logs')->assertStatus(200);

        // 3. Vendor Merchant (/vendor)
        $vendor = User::where('user_type', 'vendor')->first();
        $this->actingAs($vendor)->get('/vendor')->assertStatus(200);
        $this->actingAs($vendor)->get('/vendor/products')->assertStatus(200);
        $this->actingAs($vendor)->get('/vendor/orders')->assertStatus(200);
        $this->actingAs($vendor)->get('/vendor/reservations')->assertStatus(200);
        $this->actingAs($vendor)->get('/vendor/shops')->assertStatus(200);
        $this->actingAs($vendor)->get('/vendor/payout-account')->assertStatus(200);

        // 4. Counter Staff (/counter)
        $counter = User::where('user_type', 'vendor_employee')->first();
        $this->actingAs($counter)->get('/counter')->assertStatus(200);
        $this->actingAs($counter)->get('/counter/inspections')->assertStatus(200);
        $this->actingAs($counter)->get('/counter/handovers')->assertStatus(200);
        $this->actingAs($counter)->get('/counter/records')->assertStatus(200);

        // 5. Logistics Fleet (/logistics)
        $logistics = User::where('user_type', 'logistics_company')->first();
        $this->actingAs($logistics)->get('/logistics')->assertStatus(200);
        $this->actingAs($logistics)->get('/logistics/riders')->assertStatus(200);
        $this->actingAs($logistics)->get('/logistics/waybills')->assertStatus(200);
        $this->actingAs($logistics)->get('/logistics/wallet')->assertStatus(200);

        // 6. Delivery Rider (/rider)
        $rider = User::where('user_type', 'rider')->first();
        $this->actingAs($rider)->get('/rider')->assertStatus(200);
        $this->actingAs($rider)->get('/rider/deliveries')->assertStatus(200);
        $this->actingAs($rider)->get('/rider/pickup')->assertStatus(200);
        $this->actingAs($rider)->get('/rider/dropoff')->assertStatus(200);
        $this->actingAs($rider)->get('/rider/earnings')->assertStatus(200);

        // 7. Customer Shopper (/customer)
        $customer = User::where('user_type', 'customer')->first();
        $this->actingAs($customer)->get('/customer')->assertStatus(200);
        $this->actingAs($customer)->get('/customer/orders')->assertStatus(200);
        $this->actingAs($customer)->get('/customer/reservations')->assertStatus(200);
        $this->actingAs($customer)->get('/customer/cashback')->assertStatus(200);
        $this->actingAs($customer)->get('/customer/addresses')->assertStatus(200);
    }

    public function test_super_admin_cannot_be_saved_as_demo_and_sync_command_works(): void
    {
        // 1. Eloquent booted invariant: attempting to set is_demo = true on super_admin must be overridden to false
        $admin = User::where('user_type', 'super_admin')->first();
        $admin->is_demo = true;
        $admin->save();
        $admin->refresh();
        $this->assertFalse((bool)$admin->is_demo, 'Model invariant: Super Admin must NEVER have is_demo = true');

        // 2. Test artisan sync command
        $this->artisan('vmarket:sync-superadmin')
            ->assertExitCode(0);

        $admin->refresh();
        $this->assertFalse((bool)$admin->is_demo);
        $this->assertEquals(config('auth.superadmin.email'), $admin->email);
    }

    public function test_super_admin_can_manage_products_and_toggle_flagship_badge(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        $product = \App\Models\Product::first();
        $this->assertNotNull($product, 'A product should exist');

        // 1. Super Admin can view /admin/products page
        $response = $this->actingAs($admin)->get('/admin/products');
        $response->assertStatus(200);
        $response->assertSee('Product Catalog &amp; Flagship Badge Governance', false);
        $response->assertSee($product->name);

        // 2. Super Admin toggles Flagship badge
        $initialState = (bool)$product->is_vmarket_flagship;
        $toggleResponse = $this->actingAs($admin)->post('/admin/products/' . $product->id . '/toggle-flagship');
        $toggleResponse->assertRedirect();

        $product->refresh();
        $this->assertEquals(!$initialState, (bool)$product->is_vmarket_flagship);

        // 3. Toggle back
        $this->actingAs($admin)->post('/admin/products/' . $product->id . '/toggle-flagship');
        $product->refresh();
        $this->assertEquals($initialState, (bool)$product->is_vmarket_flagship);
    }

    public function test_non_admin_cannot_toggle_product_flagship_badge(): void
    {
        $customer = User::where('user_type', 'customer')->first();
        $product = \App\Models\Product::first();

        // Customer cannot access /admin/products or toggle flagship
        $this->actingAs($customer)->get('/admin/products')->assertStatus(403);
        $this->actingAs($customer)->post('/admin/products/' . $product->id . '/toggle-flagship')->assertStatus(403);
    }

    public function test_super_admin_can_manage_customer_corridors_separately_from_company_tariffs(): void
    {
        $admin = User::where('user_type', 'super_admin')->firstOrFail();
        $this->actingAs($admin)->get('/admin/delivery-corridors/manage')
            ->assertOk()
            ->assertSee('Create Uyo customer-delivery corridor')
            ->assertSee('Independent logistics-company payables')
            ->assertSee('Customer delivery pricing (flat per order)');

        $uyo = Lga::where('name', 'Uyo')->whereHas('state', fn ($q) => $q->where('name', 'Akwa Ibom'))->firstOrFail();
        $response = $this->actingAs($admin)->post('/admin/delivery-corridors', [
            'code' => 'uyo-plaza-demo',
            'name' => 'Uyo Plaza Corridor',
            'lga_id' => $uyo->id,
            'landmark_aliases' => ['Ibom Plaza', 'Plaza Uyo'],
            'estimated_delivery_hours_min' => 2,
            'estimated_delivery_hours_max' => 4,
            'is_active' => true,
        ]);

        $response->assertCreated()->assertJsonPath('corridor.code', 'uyo-plaza-demo');
        $corridor = DeliveryCorridor::where('code', 'uyo-plaza-demo')->firstOrFail();
        $this->assertSame($corridor->id, DeliveryCorridor::resolveForAddress($uyo->id, '12 Ibom Plaza Road', null)?->id);

        $pricingResponse = $this->actingAs($admin)->post('/admin/delivery-corridors/customer-fees', [
            'customer_base_fee' => '650.00',
            'customer_bulky_surcharge_per_unit' => '400.00',
        ]);
        $pricingResponse->assertRedirect();
        $this->assertSame('650.00', (string) SystemSetting::get('zone_intra_lga_fee'));
        $this->assertSame('400.00', (string) SystemSetting::get('zone_bulky_cargo_surcharge'));

        $company = LogisticsCompanyProfile::where('status', 'active')
            ->whereHas('hqLga', fn ($q) => $q->where('name', 'Uyo')->whereHas('state', fn ($state) => $state->where('name', 'Akwa Ibom')))
            ->firstOrFail();
        $rateResponse = $this->actingAs($admin)->post('/admin/delivery-corridors/' . $corridor->id . '/company-rates', [
            'logistics_company_id' => $company->id,
            'base_payable' => '1600.00',
            'bulky_surcharge_per_unit' => '400.00',
            'is_active' => true,
        ]);
        $rateResponse->assertOk()->assertJsonPath('rate.base_payable', '1600.00');
        $rate = LogisticsCompanyCorridorRate::where('delivery_corridor_id', $corridor->id)
            ->where('logistics_company_id', $company->id)
            ->firstOrFail();
        $this->assertSame('1600.00', $rate->base_payable);
        $this->assertSame('400.00', $rate->bulky_surcharge_per_unit);
        $this->assertSame('2400.00', $rate->calculatePayable(2));
    }

    public function test_ambiguous_or_unmatched_landmark_does_not_guess_a_delivery_corridor(): void
    {
        $uyo = Lga::where('name', 'Uyo')->whereHas('state', fn ($q) => $q->where('name', 'Akwa Ibom'))->firstOrFail();
        DeliveryCorridor::create([
            'code' => 'uyo-alias-one',
            'name' => 'Alias One',
            'lga_id' => $uyo->id,
            'landmark_aliases' => ['Plaza'],
            'estimated_delivery_hours_min' => 2,
            'estimated_delivery_hours_max' => 4,
            'is_active' => true,
        ]);
        DeliveryCorridor::create([
            'code' => 'uyo-alias-two',
            'name' => 'Alias Two',
            'lga_id' => $uyo->id,
            'landmark_aliases' => ['Plaza Road'],
            'estimated_delivery_hours_min' => 2,
            'estimated_delivery_hours_max' => 4,
            'is_active' => true,
        ]);

        $this->assertNull(DeliveryCorridor::resolveForAddress($uyo->id, 'Plaza Road, Uyo', null));
        $this->assertNull(DeliveryCorridor::resolveForAddress($uyo->id, 'An unlisted street in Uyo', null));
    }
}