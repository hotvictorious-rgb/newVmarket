<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BannerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
        Cache::flush();
    }

    public function test_storefront_loads_with_dynamic_hero_banners_and_promo_strip(): void
    {
        // 1. Create active hero banner and active promo banner
        Banner::create([
            'banner_type' => 'hero_slider',
            'title' => 'Akwa Ibom Flash Market Special',
            'subtitle' => 'Live verified plaza merchants in Uyo and Eket',
            'badge_text' => 'FLASH SPECIAL',
            'cta_text' => 'Shop Now',
            'cta_url' => '#catalog',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Banner::create([
            'banner_type' => 'promo_strip',
            'title' => 'Earn 5% Instant Cashback Across All Orders',
            'subtitle' => 'Automatic rewards wallet payout',
            'badge_text' => 'REWARDS GUARANTEE',
            'cta_text' => 'Claim 5%',
            'cta_url' => '#catalog',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Akwa Ibom Flash Market Special');
        $response->assertSee('FLASH SPECIAL');
        $response->assertSee('Earn 5% Instant Cashback Across All Orders');
        $response->assertSee('REWARDS GUARANTEE');

        // Check for mobile category track
        $response->assertSee('vm-mobile-category-track');
        $response->assertSee('vm-mob-cat-chip');
    }

    public function test_super_admin_can_access_banner_management_desk(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/admin/banners');
        $response->assertStatus(200);
        $response->assertSee('Storefront Promotional Banners &amp; Hero Slider Desk', false);
        $response->assertSee('Hero Slider Banners');
        $response->assertSee('Publish New Storefront Banner');
    }

    public function test_non_super_admin_is_forbidden_from_banner_management(): void
    {
        $customer = User::where('user_type', 'customer')->first();
        $this->assertNotNull($customer);

        $response = $this->actingAs($customer)->get('/admin/banners');
        $response->assertStatus(403);
        $response->assertSee('Role Access Barrier');
    }

    public function test_super_admin_can_store_new_promotional_banner(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();

        $postData = [
            'banner_type' => 'hero_slider',
            'title' => 'Easter Plaza Mega Sale 2026',
            'subtitle' => 'Save up to 40% on electronics in Uyo Plaza',
            'badge_text' => 'SEASONAL DEAL',
            'bg_gradient' => 'linear-gradient(135deg, #FEF3C7 0%, #FFFBEB 100%)',
            'cta_text' => 'Explore Easter Deals',
            'cta_url' => '#catalog',
            'sort_order' => 5,
            'is_active' => '1',
        ];

        $response = $this->actingAs($admin)->post('/admin/banners', $postData);
        $response->assertRedirect(route('admin.banners.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('banners', [
            'title' => 'Easter Plaza Mega Sale 2026',
            'badge_text' => 'SEASONAL DEAL',
            'sort_order' => 5,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_toggle_banner_active_status(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();

        $banner = Banner::create([
            'banner_type' => 'hero_slider',
            'title' => 'Toggle Test Banner',
            'is_active' => true,
        ]);

        $this->assertTrue($banner->is_active);

        $response = $this->actingAs($admin)->post("/admin/banners/{$banner->id}/toggle");
        $response->assertRedirect(route('admin.banners.index'));

        $banner->refresh();
        $this->assertFalse($banner->is_active);

        // Toggle back to true
        $this->actingAs($admin)->post("/admin/banners/{$banner->id}/toggle");
        $banner->refresh();
        $this->assertTrue($banner->is_active);
    }

    public function test_super_admin_can_delete_banner(): void
    {
        $admin = User::where('user_type', 'super_admin')->first();

        $banner = Banner::create([
            'banner_type' => 'promo_strip',
            'title' => 'Temporary Discontinued Banner',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete("/admin/banners/{$banner->id}");
        $response->assertRedirect(route('admin.banners.index'));

        $this->assertDatabaseMissing('banners', [
            'id' => $banner->id,
        ]);
    }
}