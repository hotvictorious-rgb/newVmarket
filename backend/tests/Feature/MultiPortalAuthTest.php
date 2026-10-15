<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiPortalAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_customer_can_login_at_customer_portal(): void
    {
        $response = $this->post('/customer/login', [
            'login' => 'customer@victoriousmarket.com.ng',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/customer');
        $this->assertAuthenticated();
    }

    public function test_customer_is_strictly_blocked_from_admin_command_gate(): void
    {
        $response = $this->post('/admin/login', [
            'login' => 'customer@victoriousmarket.com.ng',
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_super_admin_can_login_at_admin_command_gate(): void
    {
        $response = $this->post('/admin/login', [
            'login' => 'admin@victoriousmarket.com.ng',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticated();
    }

    public function test_admin_staff_finance_can_login_at_staff_portal(): void
    {
        $response = $this->post('/admin-staff/login', [
            'login' => 'finance@victoriousmarket.com.ng',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/admin-staff');
        $this->assertAuthenticated();
    }

    public function test_vendor_can_login_at_merchant_portal(): void
    {
        $response = $this->post('/vendor/login', [
            'login' => 'vendor@victoriousmarket.com.ng',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/vendor');
        $this->assertAuthenticated();
    }

    public function test_branch_staff_can_login_at_counter_terminal(): void
    {
        $response = $this->post('/counter/login', [
            'login' => 'staff@victoriousmarket.com.ng',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/counter');
        $this->assertAuthenticated();
    }

    public function test_logistics_company_can_login_at_fleet_desk(): void
    {
        $response = $this->post('/logistics/login', [
            'login' => 'logistics@victoriousmarket.com.ng',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/logistics');
        $this->assertAuthenticated();
    }

    public function test_rider_can_login_at_rider_portal(): void
    {
        $response = $this->post('/rider/login', [
            'login' => 'rider@victoriousmarket.com.ng',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/rider');
        $this->assertAuthenticated();
    }

    public function test_authenticated_user_can_logout_safely(): void
    {
        $user = User::where('email', 'admin@victoriousmarket.com.ng')->firstOrFail();
        $this->actingAs($user);

        $response = $this->post('/logout');
        $response->assertRedirect('/');
        $this->assertGuest();
    }
}
