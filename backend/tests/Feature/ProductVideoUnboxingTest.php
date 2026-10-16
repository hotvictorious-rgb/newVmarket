<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVideoUnboxingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\NigeriaGeographySeeder::class);
    }

    protected function createVendorContext(): array
    {
        $user = User::factory()->create([
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $vendor = VendorProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Apex Electronics Ltd',
            'kyc_status' => 'verified',
            'marketplace_status' => 'active',
        ]);

        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'name' => 'Apex Plaza Branch',
            'slug' => 'apex-plaza-branch-' . rand(100, 999),
            'address' => '14 Oron Road, Uyo',
            'country_id' => 1,
            'state_id' => 1,
            'lga_id' => 1,
            'pickup_enabled' => true,
        ]);

        $category = Category::create([
            'name' => 'Smartphones & Gadgets',
            'slug' => 'smartphones-gadgets-' . rand(100, 999),
            'is_active' => true,
        ]);

        return [$user, $vendor, $shop, $category];
    }

    public function test_product_model_correctly_extracts_youtube_video_id_from_various_formats(): void
    {
        // 1. Standard watch?v= URL
        $this->assertEquals('dQw4w9WgXcQ', Product::extractYoutubeVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));

        // 2. Shortened youtu.be URL
        $this->assertEquals('eWpT35yFv4c', Product::extractYoutubeVideoId('https://youtu.be/eWpT35yFv4c'));

        // 3. YouTube Shorts URL
        $this->assertEquals('abcd1234_-X', Product::extractYoutubeVideoId('https://youtube.com/shorts/abcd1234_-X?feature=share'));

        // 4. Raw 11-char token
        $this->assertEquals('1234567890A', Product::extractYoutubeVideoId('1234567890A'));

        // 5. Malicious or non-matching URLs return null
        $this->assertNull(Product::extractYoutubeVideoId('https://malicious-site.com/video.mp4'));
        $this->assertNull(Product::extractYoutubeVideoId('<script>alert("xss")</script>'));
        $this->assertNull(Product::extractYoutubeVideoId('http://127.0.0.1:8000/internal'));
        $this->assertNull(Product::extractYoutubeVideoId(''));
        $this->assertNull(Product::extractYoutubeVideoId(null));
    }

    public function test_product_accessors_generate_deterministic_cdn_and_embed_urls(): void
    {
        [$user, $vendor, $shop, $category] = $this->createVendorContext();

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Google Pixel 8 Pro',
            'slug' => 'google-pixel-8-pro',
            'unit_price' => '850000.00',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'youtube_video_id' => 'eWpT35yFv4c',
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => now(),
        ]);

        $this->assertEquals('https://img.youtube.com/vi/eWpT35yFv4c/hqdefault.jpg', $product->video_thumbnail_url);
        $this->assertEquals('https://www.youtube-nocookie.com/embed/eWpT35yFv4c', $product->video_embed_url);

        // Product without video returns null for accessors
        $productWithoutVideo = Product::create([
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'Standard USB Cable',
            'slug' => 'standard-usb-cable',
            'unit_price' => '2500.00',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'youtube_video_id' => null,
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => now(),
        ]);

        $this->assertNull($productWithoutVideo->video_thumbnail_url);
        $this->assertNull($productWithoutVideo->video_embed_url);
    }

    public function test_vendor_can_publish_product_with_valid_youtube_video_url(): void
    {
        [$user, $vendor, $shop, $category] = $this->createVendorContext();

        $payload = [
            'name' => 'Sony WH-1000XM5 Headphones',
            'unit_price' => 380000,
            'inventory_quantity' => 4,
            'category_id' => $category->id,
            'description' => 'Flagship active noise canceling headphones.',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/vendor/products', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.youtube_video_id', 'dQw4w9WgXcQ')
            ->assertJsonPath('data.video_thumbnail_url', 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg')
            ->assertJsonPath('data.video_embed_url', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');

        $this->assertDatabaseHas('products', [
            'name' => 'Sony WH-1000XM5 Headphones',
            'youtube_video_id' => 'dQw4w9WgXcQ',
        ]);
    }

    public function test_vendor_publishing_with_malicious_or_non_youtube_video_url_is_strictly_rejected(): void
    {
        [$user, $vendor, $shop, $category] = $this->createVendorContext();

        // 1. Phishing / non-YouTube URL
        $response = $this->actingAs($user)->postJson('/api/v1/vendor/products', [
            'name' => 'Suspicious Item',
            'unit_price' => 50000,
            'inventory_quantity' => 2,
            'category_id' => $category->id,
            'video_url' => 'https://phishing-domain.com/exploit.mp4',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video_url']);

        // 2. SSRF / Localhost probe
        $response2 = $this->actingAs($user)->postJson('/api/v1/vendor/products', [
            'name' => 'SSRF Probe Item',
            'unit_price' => 50000,
            'inventory_quantity' => 2,
            'category_id' => $category->id,
            'video_url' => 'http://169.254.169.254/latest/meta-data',
        ]);

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['video_url']);

        // 3. XSS injection
        $response3 = $this->actingAs($user)->postJson('/api/v1/vendor/products', [
            'name' => 'XSS Item',
            'unit_price' => 50000,
            'inventory_quantity' => 2,
            'category_id' => $category->id,
            'video_url' => '<script>window.location="http://evil.com"</script>',
        ]);

        $response3->assertStatus(422)
            ->assertJsonValidationErrors(['video_url']);
    }

    public function test_public_product_api_returns_video_metadata_for_customer_storefront(): void
    {
        [$user, $vendor, $shop, $category] = $this->createVendorContext();

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'name' => 'MacBook Pro M3 Max',
            'slug' => 'macbook-pro-m3-max',
            'unit_price' => '2950000.00',
            'inventory_quantity' => 2,
            'is_in_stock' => true,
            'youtube_video_id' => 'eWpT35yFv4c',
            'listing_status' => 'listed',
            'is_approved' => true,
            'listing_confirmed_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.youtube_video_id', 'eWpT35yFv4c')
            ->assertJsonPath('data.video_thumbnail_url', 'https://img.youtube.com/vi/eWpT35yFv4c/hqdefault.jpg')
            ->assertJsonPath('data.video_embed_url', 'https://www.youtube-nocookie.com/embed/eWpT35yFv4c');
    }
}
