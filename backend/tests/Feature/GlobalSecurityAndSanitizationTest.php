<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSecurityAndSanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\NigeriaGeographySeeder::class);
    }

    public function test_security_headers_are_present_on_all_http_responses(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=(self)');
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
    }

    public function test_script_tags_and_html_are_automatically_sanitized_from_inputs(): void
    {
        $payload = [
            'name' => 'John <script>alert("xss")</script>Doe',
            'email' => 'sanitize_test@vmarket.ng',
            'phone' => '08099887766',
            'password' => 'Secret123!@#',
            'password_confirmation' => 'Secret123!@#',
            'terms' => '1',
        ];

        $response = $this->post('/customer/register', $payload);

        $createdUser = User::where('email', 'sanitize_test@vmarket.ng')->first();
        $this->assertNotNull($createdUser);
        $this->assertStringNotContainsString('<script>', $createdUser->name);
        $this->assertEquals('John Doe', $createdUser->name);
    }

    public function test_dangerous_javascript_pseudo_protocols_are_neutralized(): void
    {
        $user = User::factory()->create([
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $sanitizer = new \App\Http\Middleware\SanitizeAndHardenInputMiddleware();
        $request = \Illuminate\Http\Request::create('/test', 'POST', [
            'name' => 'John Doe',
            'website' => 'javascript:stealCookies()',
            'bio' => 'Check this out: vbscript:runMacro()',
        ]);

        $sanitizer->handle($request, function ($req) {
            $this->assertEquals('blocked:stealCookies()', $req->input('website'));
            $this->assertEquals('Check this out: blocked:runMacro()', $req->input('bio'));
            return response('OK');
        });
    }

    public function test_passwords_are_preserved_without_alteration(): void
    {
        $passwordWithSpecialChars = 'P@ssw0rd!#$&*<>';

        $user = User::factory()->create([
            'email' => 'secure_shopper@vmarket.ng',
            'password' => bcrypt($passwordWithSpecialChars),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $response = $this->post('/customer/login', [
            'login' => 'secure_shopper@vmarket.ng',
            'password' => $passwordWithSpecialChars,
        ]);

        $this->assertAuthenticatedAs($user);
    }
}
