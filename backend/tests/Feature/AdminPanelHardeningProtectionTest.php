<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminPanelHardeningProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_custom_secret_admin_path_hides_default_admin_url_with_404(): void
    {
        config(['auth.admin_panel_path' => 'hq-tower-7729']);

        // Re-load routes with the updated secret prefix inside web middleware
        $this->app['router']->setRoutes(new RouteCollection());
        $this->app['router']->middleware('web')->group(function () {
            require base_path('routes/web.php');
        });
        $this->app['router']->getRoutes()->refreshNameLookups();

        // Standard /admin or /admin/login returns 404
        $responseDefault = $this->get('/admin/login');
        $responseDefault->assertStatus(404);

        // Secret path loads login page successfully
        $responseSecret = $this->get('/hq-tower-7729/login');
        $responseSecret->assertStatus(200);
        $responseSecret->assertSee('Super Admin Command Gate');
    }

    public function test_session_inactivity_timeout_terminates_stale_super_admin_session(): void
    {
        $superAdmin = User::factory()->create([
            'email' => User::getSuperAdminEmail(),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        // Simulate login with activity 20 minutes ago (timeout is 15 minutes)
        $this->actingAs($superAdmin);
        session([
            'admin_session_ip' => '127.0.0.1',
            'admin_session_ua' => hash('sha256', 'Symfony'),
            'admin_last_activity_at' => now()->subMinutes(20)->timestamp,
            'admin_2fa_verified' => true,
        ]);

        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.login'));
        $this->assertFalse(Auth::check());
    }

    public function test_session_anti_hijack_defense_terminates_session_on_ip_mutation(): void
    {
        $superAdmin = User::factory()->create([
            'email' => User::getSuperAdminEmail(),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($superAdmin);
        // Session bound to original IP 192.168.1.100
        session([
            'admin_session_ip' => '192.168.1.100',
            'admin_session_ua' => hash('sha256', 'Symfony'),
            'admin_last_activity_at' => now()->timestamp,
            'admin_2fa_verified' => true,
        ]);

        // Request arrives from a new/hijacked IP address (102.89.22.44)
        $response = $this->withServerVariables(['REMOTE_ADDR' => '102.89.22.44'])
            ->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
        $this->assertFalse(Auth::check());
    }

    public function test_super_admin_2fa_otp_lifecycle_and_verification(): void
    {
        config(['auth.admin_2fa_enabled' => true]);

        $superAdmin = User::factory()->create([
            'email' => User::getSuperAdminEmail(),
            'password' => Hash::make('SuperSecretKey123!'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);

        // 1. Authenticate with master credentials
        $responseLogin = $this->post(route('admin.login.post'), [
            'login' => $superAdmin->email,
            'password' => 'SuperSecretKey123!',
        ]);

        // Redirects to 2FA verification gate
        $responseLogin->assertRedirect(route('admin.verify_2fa'));
        $this->assertTrue(Auth::check());
        $this->assertFalse(session('admin_2fa_verified'));

        // 2. Attempting to bypass 2FA to access admin dashboard is blocked
        $responseBypass = $this->get(route('admin.dashboard'));
        $responseBypass->assertRedirect(route('admin.verify_2fa'));

        // 3. Test wrong OTP submission
        $responseWrongOtp = $this->post(route('admin.verify_2fa.post'), [
            'otp' => '000000',
        ]);
        $responseWrongOtp->assertSessionHasErrors(['otp']);
        $this->assertFalse(session('admin_2fa_verified'));

        // 4. Submit correct OTP
        $rawOtp = '654321';
        session(['admin_2fa_otp' => Hash::make($rawOtp)]);

        $responseCorrectOtp = $this->post(route('admin.verify_2fa.post'), [
            'otp' => $rawOtp,
        ]);

        $responseCorrectOtp->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(session('admin_2fa_verified'));

        // 5. Subsequent access to dashboard succeeds
        $responseDashboard = $this->get(route('admin.dashboard'));
        $responseDashboard->assertStatus(200);
    }
}
