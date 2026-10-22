<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Lga;
use App\Models\LogisticsCompanyProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\ImageOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImageOptimizationAndProofAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $riderUser;
    protected RiderProfile $rider;
    protected LogisticsCompanyProfile $company;
    protected Shop $shop;
    protected VendorProfile $vendor;
    protected Product $product;
    protected State $state;
    protected Lga $lga;
    protected ImageOptimizationService $optimizer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->optimizer = app(ImageOptimizationService::class);

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NGA',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'currency_symbol' => '₦',
            'is_active' => true,
        ]);

        $this->state = State::create([
            'country_id' => $country->id,
            'name' => 'Akwa Ibom',
            'state_code' => 'AK',
            'is_active' => true,
        ]);

        $this->lga = Lga::create(['state_id' => $this->state->id, 'name' => 'Uyo', 'is_active' => true]);

        $fleetUser = User::create([
            'name' => 'Speedy Logistics',
            'email' => 'speedy@fleet.ng',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'logistics_company',
            'status' => 'active',
        ]);

        $this->company = LogisticsCompanyProfile::create([
            'user_id' => $fleetUser->id,
            'company_name' => 'Speedy Logistics',
            'company_phone' => '08012345678',
            'status' => 'active',
            'is_verified' => true,
        ]);

        $this->riderUser = User::create([
            'name' => 'Rider John',
            'email' => 'rider@speedy.ng',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'rider',
            'status' => 'active',
        ]);

        $this->rider = RiderProfile::create([
            'user_id' => $this->riderUser->id,
            'logistics_company_id' => $this->company->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'rider_code' => 'RIDER-001',
            'phone' => '08055550000',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $vendorUser = User::create([
            'name' => 'Vendor Test',
            'email' => 'vendor@shop.ng',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);

        $this->vendor = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'business_name' => 'Test Vendor Store',
            'business_phone' => '08077770000',
            'marketplace_status' => 'active',
        ]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'country_id' => $country->id,
            'state_id' => $this->state->id,
            'lga_id' => $this->lga->id,
            'name' => 'Test Shop Plaza',
            'slug' => 'test-shop-plaza',
            'address' => '10 Plaza Road',
            'phone' => '08077770000',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics', 'is_active' => true]);

        $this->product = Product::create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'category_id' => $category->id,
            'name' => 'Blender 500W',
            'slug' => 'blender-500w',
            'sku' => 'BLD-001',
            'unit_price' => '15000.00',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => now(),
        ]);
    }

    /**
     * 1. Proof optimization converts to WebP, generates thumbnail, and reports bytes saved.
     */
    public function test_uploaded_proof_photo_is_optimized_and_compressed_to_webp(): void
    {
        $file = UploadedFile::fake()->image('doorstep_proof.jpg', 800, 600);

        $result = $this->optimizer->optimizeAndStoreProof($file, 'doorstep_deliveries');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('doorstep_deliveries/', $result['path']);
        $this->assertStringEndsWith('.webp', $result['path']);
        $this->assertNotNull($result['thumbnail_path']);
        $this->assertStringContainsString('thumbs/', $result['thumbnail_path']);
        $this->assertGreaterThan(0, $result['optimized_size']);
        $this->assertEquals(800, $result['width']);
        $this->assertEquals(600, $result['height']);
        $this->assertEquals('webp', $result['format']);

        $relativePath = str_replace('storage/', '', $result['path']);
        Storage::disk('public')->assertExists($relativePath);
    }

    /**
     * 2. Oversized mobile camera photos (e.g. 2400x1800) are constrained to max dimension.
     */
    public function test_uploaded_proof_photo_scales_down_oversized_images(): void
    {
        $file = UploadedFile::fake()->image('huge_mobile_photo.jpg', 2400, 1800);

        $result = $this->optimizer->optimizeAndStoreProof($file, 'pickup_proofs', [
            'max_dimension' => 1600,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(1600, $result['width']);
        $this->assertEquals(1200, $result['height']);
    }

    /**
     * 3. Rider Terminal verify pickup OTP endpoint stores an optimized proof photo.
     */
    public function test_rider_pickup_otp_stores_optimized_proof_photo(): void
    {
        $customer = User::create([
            'name' => 'Shopper Grace',
            'email' => 'grace@test.com',
            'password' => bcrypt('Pass123!'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-OPT-PICKUP-01',
            'customer_id' => $customer->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'rider_id' => $this->rider->id,
            'logistics_company_id' => $this->company->id,
            'order_type' => 'delivery',
            'fulfillment_type' => 'doorstep_delivery',
            'payment_status' => 'paid',
            'order_status' => 'ready_for_pickup',
            'pickup_verification_code' => '445566',
            'merchandise_subtotal' => '15000.00',
            'delivery_fee' => '1500.00',
            'grand_total' => '16500.00',
            'total_amount' => '16500.00',
            'vendor_net_90' => '13500.00',
            'customer_cashback_5' => '750.00',
            'platform_margin_5' => '750.00',
            'logistics_fee_retained' => '1350.00',
            'platform_delivery_fee' => '150.00',
            'origin_lga_id' => $this->lga->id,
            'destination_lga_id' => $this->lga->id,
        ]);

        Sanctum::actingAs($this->riderUser);

        $response = $this->postJson('/api/v1/rider/orders/verify-pickup-otp', [
            'order_id' => $order->id,
            'otp' => '445566',
            'proof_photo' => UploadedFile::fake()->image('merchant_counter_proof.jpg', 1200, 900),
        ]);

        $response->assertStatus(200);
        $order->refresh();
        $this->assertNotNull($order->pickup_proof_image);
        $this->assertStringContainsString('pickup_proofs', $order->pickup_proof_image);
        $this->assertStringEndsWith('.webp', $order->pickup_proof_image);

        $storedRelative = str_replace('storage/', '', $order->pickup_proof_image);
        Storage::disk('public')->assertExists($storedRelative);
    }

    /**
     * 4. Verify client-side zero-latency optimizer asset exists and exports required methods.
     */
    public function test_client_image_optimizer_asset_exists_and_implements_compression_api(): void
    {
        $assetPath = public_path('assets/js/client-image-optimizer.js');
        $this->assertFileExists($assetPath);

        $jsContent = file_get_contents($assetPath);
        $this->assertStringContainsString('ClientImageOptimizer', $jsContent);
        $this->assertStringContainsString('compressFile', $jsContent);
        $this->assertStringContainsString('compressFiles', $jsContent);
        $this->assertStringContainsString('createFileList', $jsContent);
        $this->assertStringContainsString('isWebpSupported', $jsContent);
        $this->assertStringContainsString('toBlob', $jsContent);
    }

    /**
     * 5. Verify vendor catalog studio modal and scripts contain preview and client optimization hooks.
     */
    public function test_vendor_product_studio_blade_integrates_client_compression_and_preview(): void
    {
        $modalPath = resource_path('views/vendor/partials/_product_studio_modal.blade.php');
        $scriptsPath = resource_path('views/vendor/partials/_product_studio_scripts.blade.php');

        $this->assertFileExists($modalPath);
        $this->assertFileExists($scriptsPath);

        $modalContent = file_get_contents($modalPath);
        $scriptsContent = file_get_contents($scriptsPath);

        // Preview & stats elements exist
        $this->assertStringContainsString('id="fbThumbnailGallery"', $modalContent);
        $this->assertStringContainsString('id="fbOptimizationStats"', $modalContent);
        $this->assertStringContainsString('id="fbOptimizingSpinner"', $modalContent);

        // Scripts include optimizer and handle client compression
        $this->assertStringContainsString('client-image-optimizer.js', $scriptsContent);
        $this->assertStringContainsString('ClientImageOptimizer.compressFile', $scriptsContent);
        $this->assertStringContainsString('totalOriginalBytes', $scriptsContent);
    }

    /**
     * 6. Verify courier pickup terminal integrates preview and on-device compression.
     */
    public function test_rider_pickup_blade_integrates_client_compression_and_preview(): void
    {
        $viewPath = resource_path('views/rider/pickup.blade.php');
        $this->assertFileExists($viewPath);

        $viewContent = file_get_contents($viewPath);
        $this->assertStringContainsString('client-image-optimizer.js', $viewContent);
        $this->assertStringContainsString('id="pickupPhotoPreviewContainer"', $viewContent);
        $this->assertStringContainsString('id="pickupPhotoPreviewImg"', $viewContent);
        $this->assertStringContainsString('id="pickupOptBadge"', $viewContent);
        $this->assertStringContainsString('ClientImageOptimizer.compressFile', $viewContent);
    }

    /**
     * 7. Verify customer doorstep status view integrates preview and on-device compression.
     */
    public function test_storefront_order_status_blade_integrates_client_compression_and_preview(): void
    {
        $viewPath = resource_path('views/storefront/order_status.blade.php');
        $this->assertFileExists($viewPath);

        $viewContent = file_get_contents($viewPath);
        $this->assertStringContainsString('client-image-optimizer.js', $viewContent);
        $this->assertStringContainsString('id="proofPhotoPreviewContainer"', $viewContent);
        $this->assertStringContainsString('id="proofPhotoPreviewImg"', $viewContent);
        $this->assertStringContainsString('id="customerProofOptBadge"', $viewContent);
        $this->assertStringContainsString('ClientImageOptimizer.compressFile', $viewContent);
    }

    /**
     * 8. Verify admin banner studio integrates preview simulator and on-device compression.
     */
    public function test_admin_banners_blade_integrates_client_compression_and_preview(): void
    {
        $viewPath = resource_path('views/admin/banners.blade.php');
        $this->assertFileExists($viewPath);

        $viewContent = file_get_contents($viewPath);
        $this->assertStringContainsString('client-image-optimizer.js', $viewContent);
        $this->assertStringContainsString('id="bannerOptBadge"', $viewContent);
        $this->assertStringContainsString('id="bannerCompressing"', $viewContent);
        $this->assertStringContainsString('ClientImageOptimizer.compressFile', $viewContent);
    }
}
