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

    public function test_customer_can_register_and_is_authenticated_with_customer_role(): void
    {
        $response = $this->post('/customer/register', [
            'name' => 'Akwa Ibom Buyer',
            'email' => 'buyer@victoriousmarket.com.ng',
            'phone' => '08012345678',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'terms' => '1',
        ]);

        $response->assertRedirect('/customer');
        $this->assertAuthenticated();

        $user = User::where('email', 'buyer@victoriousmarket.com.ng')->first();
        $this->assertNotNull($user);
        $this->assertEquals('customer', $user->user_type);
        $this->assertEquals('active', $user->status);
    }

    public function test_customer_registration_strictly_blocks_privilege_escalation(): void
    {
        // Attacker attempts to forge user_type as super_admin
        $response = $this->post('/customer/register', [
            'name' => 'Sneaky Actor',
            'email' => 'sneaky@example.com',
            'phone' => '08099887766',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'user_type' => 'super_admin',
            'terms' => '1',
        ]);

        $response->assertRedirect('/customer');
        $user = User::where('email', 'sneaky@example.com')->first();
        $this->assertNotNull($user);
        // Invariant: Must strictly remain 'customer'
        $this->assertEquals('customer', $user->user_type);
        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_customer_registration_validates_required_fields(): void
    {
        $response = $this->post('/customer/register', []);
        $response->assertSessionHasErrors(['name', 'email', 'phone', 'password', 'terms']);
        $this->assertGuest();
    }

    public function test_google_redirect_handles_unconfigured_or_redirect_state_safely(): void
    {
        $response = $this->get('/auth/google/redirect');
        // If not configured in .env, redirects to customer.login with explanatory message
        // If configured, redirects to accounts.google.com
        $this->assertTrue(in_array($response->getStatusCode(), [302, 303]));
    }
}
