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
}
