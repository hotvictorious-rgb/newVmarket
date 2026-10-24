<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Lga;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\SupplierCatalogProduct;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\SupplierOfferSelectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierPrivateCatalogPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $supplierUser;
    private VendorProfile $supplier;
    private Shop $shop;
    private User $admin;
    private Category $category;
    private Lga $uyo;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NG',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'is_active' => true,
        ]);
        $state = State::create([
            'country_id' => $country->id,
            'name' => 'Akwa Ibom',
            'code' => 'AK',
            'is_active' => true,
        ]);
        $this->uyo = Lga::create([
            'state_id' => $state->id,
            'name' => 'Uyo',
            'code' => 'UYO',
            'is_active' => true,
        ]);

        $this->supplierUser = User::create([
            'name' => 'Private Catalog Supplier',
            'email' => 'private-catalog-supplier@example.test',
            'phone' => '08031110001',
            'password' => bcrypt('password123'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->supplier = VendorProfile::create([
            'user_id' => $this->supplierUser->id,
            'business_name' => 'Private Catalog Supplier Ltd',
            'marketplace_status' => 'active',
            'kyc_status' => 'verified',
        ]);
        $this->shop = Shop::create([
            'vendor_id' => $this->supplier->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $this->uyo->id,
            'name' => 'Private Catalog Uyo Shop',
            'slug' => 'private-catalog-uyo-shop',
            'address' => 'Uyo, Akwa Ibom',
            'phone' => '08031110001',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);
        $this->admin = User::create([
            'name' => 'Catalog Super Admin',
            'email' => 'private-catalog-admin@example.test',
            'phone' => '08031110002',
            'password' => bcrypt('password123'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);
        $this->category = Category::create([
            'name' => 'Catalog Test',
            'slug' => 'catalog-test',
            'is_active' => true,
        ]);
    }

    public function test_supplier_private_catalog_is_binary_stock_only_and_not_public_until_admin_match(): void
    {
        $this->assertTrue(Schema::hasTable('supplier_catalog_products'));
        $this->assertFalse(Schema::hasColumn('supplier_catalog_products', 'quantity'));
        $this->assertFalse(Schema::hasColumn('supplier_catalog_products', 'inventory_quantity'));

        Sanctum::actingAs($this->supplierUser, ['*']);
        $created = $this->postJson('/api/v1/vendor/private-catalog', [
            'product_name' => 'Solar Power Bank 20000mAh',
            'description' => 'Supplier private source record.',
            'photo_path' => '/storage/private/solar-power-bank.jpg',
            'brand' => 'Example Brand',
            'model' => 'PB-20K',
            'variant' => '20000mAh USB-C',
            'condition' => 'brand_new',
            'package_tier' => 'bike',
            'specifications' => ['capacity' => '20000mAh', 'port' => 'USB-C'],
            'source_price' => '18000.00',
            'is_in_stock' => true,
        ]);

        $created->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.stock_status', 'in_stock')
            ->assertJsonPath('data.source_price', '18000.00')
            ->assertJsonPath('data.is_linked_to_market_listing', false)
            ->assertJsonPath('policy.quantity_field', null);

        $recordId = (int) $created->json('data.id');
        $record = SupplierCatalogProduct::findOrFail($recordId);
        $this->assertSame($this->supplier->id, (int) $record->supplier_vendor_id);
        $this->assertSame($this->shop->id, (int) $record->supplier_shop_id);
        $this->assertNull($record->canonical_product_id);
        $this->assertSame(0, SupplierOffer::count());

        $canonical = Product::create([
            'vendor_id' => null,
            'shop_id' => null,
            'category_id' => $this->category->id,
            'name' => 'Solar Power Bank 20000mAh',
            'slug' => 'solar-power-bank-20000mah',
            'unit_price' => '25000.00',
            'cost_price' => '0.00',
            'is_in_stock' => true,
            'inventory_quantity' => 0,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subMinute(),
            'freshness_confirmed_at' => Carbon::now()->subMinute(),
            'condition' => 'brand_new',
            'package_tier' => 'bike',
        ]);

        $this->actingAs($this->admin);
        $linked = $this->post(route('admin.supplier-catalog.private-products.link', $recordId), [
            'canonical_product_id' => $canonical->id,
            'match_notes' => 'Exact brand, model, capacity, port and condition reviewed.',
        ]);
        $linked->assertRedirect();
        $this->assertSessionHas('success');

        $record->refresh();
        $offer = SupplierOffer::where('supplier_catalog_product_id', $record->id)->firstOrFail();
        $this->assertSame((int) $canonical->id, (int) $record->canonical_product_id);
        $this->assertSame((int) $canonical->id, (int) $offer->product_id);
        $this->assertSame('18000.00', (string) $offer->wholesale_cost);
        $this->assertTrue($offer->is_available, 'The supplier binary signal can be stored on the offer.');
        $this->assertNull($offer->availability_confirmed_at, 'The stock signal must not impersonate admin confirmation.');
        $this->assertNull($offer->availability_confirmed_by);
        $this->assertNull(app(SupplierOfferSelectionService::class)->selectLowestCost($canonical, 'doorstep_delivery'));

        $publicProductData = $canonical->fresh()->toArray();
        $this->assertArrayNotHasKey('source_price', $publicProductData);
        $this->assertArrayNotHasKey('supplier_catalog_product_id', $publicProductData);
    }

    public function test_supplier_stock_toggle_invalidates_prior_offer_confirmation_and_cannot_change_other_supplier_records(): void
    {
        $record = SupplierCatalogProduct::create([
            'supplier_vendor_id' => $this->supplier->id,
            'supplier_shop_id' => $this->shop->id,
            'product_name' => 'USB-C Charger 65W',
            'source_price' => '7000.00',
            'is_in_stock' => true,
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->supplierUser, ['*']);
        $updated = $this->patchJson("/api/v1/vendor/private-catalog/{$record->id}", [
            'is_in_stock' => false,
        ]);
        $updated->assertOk()->assertJsonPath('data.stock_status', 'out_of_stock');
        $record->refresh();
        $this->assertFalse($record->is_in_stock);

        $otherUser = User::create([
            'name' => 'Other Supplier',
            'email' => 'other-private-catalog@example.test',
            'phone' => '08031110003',
            'password' => bcrypt('password123'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        Sanctum::actingAs($otherUser, ['*']);
        $this->patchJson("/api/v1/vendor/private-catalog/{$record->id}", [
            'product_name' => 'Attempted cross-supplier edit',
        ])->assertNotFound();
        $this->assertSame('USB-C Charger 65W', $record->fresh()->product_name);
    }
}
