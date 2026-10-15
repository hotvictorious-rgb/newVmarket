<?php

namespace Tests\Feature;

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

    public function test_super_admin_dashboard_loads_successfully(): void
    {
        $response = $this->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('Platform Sovereign Command Tower');
        $response->assertSee('Manual Settlement Desk');
        $response->assertSee('Storefront Announcement &amp; Support Control Center', false);
    }

    public function test_admin_staff_dashboard_loads_successfully(): void
    {
        $response = $this->get('/admin-staff');
        $response->assertStatus(200);
        $response->assertSee('Operations, Moderation &amp; Support Desk', false);
        $response->assertSee('Product Catalog Moderation &amp; 7-Day Freshness Queue', false);
    }

    public function test_vendor_merchant_dashboard_loads_successfully(): void
    {
        $response = $this->get('/vendor');
        $response->assertStatus(200);
        $response->assertSee('Merchant Commerce Suite');
        $response->assertSee('Gross Merchant Sales');
        $response->assertSee('90% Payout Bank Account');
    }

    public function test_branch_counter_dashboard_loads_successfully(): void
    {
        $response = $this->get('/counter');
        $response->assertStatus(200);
        $response->assertSee('In-Shop Inspection &amp; Handover Terminal', false);
        $response->assertSee('Physical Inspection Sign-Off Desk');
        $response->assertSee('Rider Handover Custody Verification');
    }

    public function test_logistics_fleet_dashboard_loads_successfully(): void
    {
        $response = $this->get('/logistics');
        $response->assertStatus(200);
        $response->assertSee('Fleet Logistics Operations Center');
        $response->assertSee('Directional LGA Waybills Manifest');
        $response->assertSee('5% Logistics Fee Wallet');
    }

    public function test_delivery_rider_dashboard_loads_successfully(): void
    {
        $response = $this->get('/rider');
        $response->assertStatus(200);
        $response->assertSee('Delivery Rider Dispatch Cockpit');
        $response->assertSee('STEP 1: PLAZA COUNTER PICKUP');
        $response->assertSee('STEP 2: CUSTOMER DOORSTEP HANDSHAKE');
    }

    public function test_customer_shopper_dashboard_loads_successfully(): void
    {
        $response = $this->get('/customer');
        $response->assertStatus(200);
        $response->assertSee('My Profile &amp; Account Suite', false);
        $response->assertSee('Live Order Multi-Stage Tracking');
        $response->assertSee('5% Sovereign Cashback Rewards Wallet');
        $response->assertSee('5% Victorious Cashback Guarantee');
    }
    public function test_super_admin_delivery_lanes_page_loads_successfully(): void
    {
        $response = $this->get('/admin/delivery-lanes');
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

    public function test_super_admin_can_store_custom_delivery_lane_via_searchable_lga_selection(): void
    {
        $origin = \App\Models\Lga::where('name', 'Uyo')->first() ?? \App\Models\Lga::first();
        $dest = \App\Models\Lga::where('name', 'Eket')->first() ?? \App\Models\Lga::skip(1)->first();

        $postData = [
            'origin_lga_id' => $origin->id,
            'destination_lga_id' => $dest->id,
            'base_fee' => '1350.00',
            'bulky_surcharge' => '1200.00',
            'estimated_delivery_hours_min' => 4,
            'estimated_delivery_hours_max' => 12,
            'is_bidirectional' => '1',
        ];

        $response = $this->post('/admin/delivery-lanes', $postData);
        $response->assertRedirect(route('admin.delivery_lanes.index', ['hub_lga_id' => $origin->id]));

        $this->assertDatabaseHas('delivery_lanes', [
            'origin_lga_id' => $origin->id,
            'destination_lga_id' => $dest->id,
            'base_fee' => '1350.00',
        ]);

        // Assert bidirectional reverse lane created automatically
        $this->assertDatabaseHas('delivery_lanes', [
            'origin_lga_id' => $dest->id,
            'destination_lga_id' => $origin->id,
            'base_fee' => '1350.00',
        ]);
    }

    public function test_super_admin_can_edit_existing_delivery_lane(): void
    {
        $lane = \App\Models\DeliveryLane::first();
        $this->assertNotNull($lane);

        $updateData = [
            'lane_id' => $lane->id,
            'origin_lga_id' => $lane->origin_lga_id,
            'destination_lga_id' => $lane->destination_lga_id,
            'base_fee' => '2450.00',
            'bulky_surcharge' => '1500.00',
            'estimated_delivery_hours_min' => 8,
            'estimated_delivery_hours_max' => 20,
        ];

        $response = $this->post('/admin/delivery-lanes', $updateData);
        $response->assertRedirect(route('admin.delivery_lanes.index', ['hub_lga_id' => $lane->origin_lga_id]));

        $this->assertDatabaseHas('delivery_lanes', [
            'id' => $lane->id,
            'base_fee' => '2450.00',
            'bulky_surcharge' => '1500.00',
            'estimated_delivery_hours_min' => 8,
            'estimated_delivery_hours_max' => 20,
        ]);
    }

    public function test_super_admin_settlements_page_loads_successfully(): void
    {
        $response = $this->get('/admin/settlements');
        $response->assertStatus(200);
        $response->assertSee('90/5/5 Sovereign Escrow &amp; Settlement Desk', false);
        $response->assertSee('Sovereign 90/5/5 Mathematical Split Formula');
    }

    public function test_super_admin_announcements_page_loads_successfully(): void
    {
        $response = $this->get('/admin/announcements');
        $response->assertStatus(200);
        $response->assertSee('Storefront Announcement Bar &amp; Customer Care Desk', false);
        $response->assertSee('CUSTOMER CARE PHONE');
    }

    public function test_super_admin_merchants_page_loads_successfully(): void
    {
        $response = $this->get('/admin/merchants');
        $response->assertStatus(200);
        $response->assertSee('Merchant Accreditation &amp; KYC Governance Desk', false);
        $response->assertSee('Accredited Merchant KYC &amp; Banking Desk', false);
    }

    public function test_super_admin_orders_page_loads_successfully(): void
    {
        $response = $this->get('/admin/orders');
        $response->assertStatus(200);
        $response->assertSee('Platform Escrow Orders &amp; Fulfillment Ledger', false);
        $response->assertSee('Master Platform Orders Ledger');
    }

    /**
     * Test All 7 Roles Dedicated Navigation & Standalone Pages
     */
    public function test_all_seven_roles_dedicated_standalone_pages_load_successfully(): void
    {
        // 1. Super Admin (/admin)
        $this->get('/admin')->assertStatus(200);
        $this->get('/admin/delivery-lanes')->assertStatus(200);
        $this->get('/admin/settlements')->assertStatus(200);
        $this->get('/admin/announcements')->assertStatus(200);
        $this->get('/admin/merchants')->assertStatus(200);
        $this->get('/admin/orders')->assertStatus(200);

        // 2. Admin Staff (/admin-staff)
        $this->get('/admin-staff')->assertStatus(200);
        $this->get('/admin-staff/moderation')->assertStatus(200)->assertSee('Product Moderation &amp; 7-Day Freshness Desk', false);
        $this->get('/admin-staff/orders')->assertStatus(200)->assertSee('Order Dispatch Triage');
        $this->get('/admin-staff/inspections')->assertStatus(200)->assertSee('Counter Inspection Monitor');
        $this->get('/admin-staff/logs')->assertStatus(200)->assertSee('Platform Operational Logs');

        // 3. Vendor Merchant (/vendor)
        $this->get('/vendor')->assertStatus(200);
        $this->get('/vendor/products')->assertStatus(200)->assertSee('Products &amp; 7-Day Freshness Catalog', false);
        $this->get('/vendor/orders')->assertStatus(200)->assertSee('Customer Orders');
        $this->get('/vendor/reservations')->assertStatus(200)->assertSee('In-Shop Pickup Reservations');
        $this->get('/vendor/shops')->assertStatus(200)->assertSee('Physical Plaza Outlets &amp; Branches', false);
        $this->get('/vendor/payout-account')->assertStatus(200)->assertSee('90% Payout Bank Account &amp; Financial Settlement', false);

        // 4. Counter Staff (/counter)
        $this->get('/counter')->assertStatus(200);
        $this->get('/counter/inspections')->assertStatus(200)->assertSee('Physical Inspection Sign-Off Desk');
        $this->get('/counter/handovers')->assertStatus(200)->assertSee('Rider Handover Custody Verification');
        $this->get('/counter/records')->assertStatus(200)->assertSee("Today's Inspection Records &amp; Sign-Off Logs", false);

        // 5. Logistics Fleet (/logistics)
        $this->get('/logistics')->assertStatus(200);
        $this->get('/logistics/riders')->assertStatus(200)->assertSee('Rider Roster &amp; Shift Management', false);
        $this->get('/logistics/waybills')->assertStatus(200)->assertSee('Directional LGA Waybills Manifest');
        $this->get('/logistics/wallet')->assertStatus(200)->assertSee('5% Logistics Fee Wallet &amp; Revenue Ledger', false);

        // 6. Delivery Rider (/rider)
        $this->get('/rider')->assertStatus(200);
        $this->get('/rider/deliveries')->assertStatus(200)->assertSee('My Active Drops &amp; LGA Dispatches', false);
        $this->get('/rider/pickup')->assertStatus(200)->assertSee('STEP 1: PLAZA COUNTER PICKUP');
        $this->get('/rider/dropoff')->assertStatus(200)->assertSee('STEP 2: CUSTOMER DOORSTEP HANDSHAKE');
        $this->get('/rider/earnings')->assertStatus(200)->assertSee("Today's Earning Ledger");

        // 7. Customer Shopper (/customer)
        $this->get('/customer')->assertStatus(200);
        $this->get('/customer/orders')->assertStatus(200)->assertSee('My Orders &amp; Live Tracking', false);
        $this->get('/customer/reservations')->assertStatus(200)->assertSee('In-Shop Pickup Reservations');
        $this->get('/customer/cashback')->assertStatus(200)->assertSee('5% Sovereign Cashback Rewards Wallet');
        $this->get('/customer/addresses')->assertStatus(200)->assertSee('Verified Delivery Addresses');
    }
}
