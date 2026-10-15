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
        $response->assertSee('id="laneOriginSearchInput"', false);
        $response->assertSee('id="laneDestSearchInput"', false);
        $response->assertSee('id="btnSwapOriginDest"', false);
        $response->assertSee('id="vmCorridorLiveTopology"', false);
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
        $response->assertRedirect('/admin/delivery-lanes');

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
        $response->assertRedirect('/admin/delivery-lanes');

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
}