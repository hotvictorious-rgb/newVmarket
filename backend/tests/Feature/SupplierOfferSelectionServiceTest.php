<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Country;
use App\Models\Lga;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\SupplierOfferAvailabilityService;
use App\Services\SupplierOfferSelectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierOfferSelectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private VendorProfile $vendor;
    private Shop $shop;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create([
            'name' => 'Nigeria',
            'iso_code' => 'NGA',
            'phone_code' => '+234',
            'currency_code' => 'NGN',
            'currency_symbol' => '₦',
            'is_active' => true,
        ]);
        $state = State::create([
            'country_id' => $country->id,
            'name' => 'Akwa Ibom',
            'state_code' => 'AK',
            'is_active' => true,
        ]);
        $lga = Lga::create([
            'state_id' => $state->id,
            'name' => 'Uyo',
            'is_active' => true,
        ]);
        $this->admin = User::create([
            'name' => 'Sourcing Admin',
            'email' => 'sourcing-admin@example.test',
            'password' => bcrypt('test-password'),
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);
        $supplierUser = User::create([
            'name' => 'Supplier One',
            'email' => 'supplier-one@example.test',
            'password' => bcrypt('test-password'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $this->vendor = VendorProfile::create([
            'user_id' => $supplierUser->id,
            'business_name' => 'Supplier One Ltd',
            'business_phone' => '08030000001',
            'marketplace_status' => 'active',
            'kyc_status' => 'verified',
        ]);
        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'lga_id' => $lga->id,
            'name' => 'Supplier One Shop',
            'slug' => 'supplier-one-shop',
            'address' => 'Uyo',
            'phone' => '08030000001',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);
        $category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => true,
            'priority' => 1,
        ]);
        $this->product = Product::create([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'category_id' => $category->id,
            'name' => 'Canonical Test Product',
            'slug' => 'canonical-test-product',
            'unit_price' => '10000.00',
            'inventory_quantity' => 10,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subHour(),
        ]);
    }

    private function offer(array $overrides = []): SupplierOffer
    {
        return SupplierOffer::create(array_merge([
            'product_id' => $this->product->id,
            'vendor_id' => $this->vendor->id,
            'shop_id' => $this->shop->id,
            'wholesale_cost' => '5000.00',
            'is_available' => true,
            'availability_confirmed_at' => Carbon::now()->subMinutes(5),
            'availability_confirmed_by' => $this->admin->id,
            'availability_expires_at' => Carbon::now()->addMinutes(30),
        ], $overrides));
    }

    public function test_unverified_supplier_is_not_selectable_even_with_fresh_offer(): void
    {
        $offer = $this->offer();
        $this->vendor->update(['kyc_status' => 'submitted']);

        $selectedWhileUnverified = app(SupplierOfferSelectionService::class)
            ->selectLowestCost($this->product, 'doorstep_delivery');
        $this->assertNull($selectedWhileUnverified);

        $this->vendor->update(['kyc_status' => 'verified']);
        $selectedAfterVerification = app(SupplierOfferSelectionService::class)
            ->selectLowestCost($this->product, 'doorstep_delivery');
        $this->assertNotNull($selectedAfterVerification);
        $this->assertSame($offer->id, $selectedAfterVerification->id);
    }

    public function test_selects_lowest_cost_eligible_offer_with_deterministic_tie_break(): void
    {
        $higher = $this->offer(['wholesale_cost' => '6000.00']);
        $lowest = $this->offer(['wholesale_cost' => '4000.00']);
        $sameCostLaterId = $this->offer(['wholesale_cost' => '4000.00']);

        $selected = app(SupplierOfferSelectionService::class)
            ->selectLowestCost($this->product, 'doorstep_delivery');

        $this->assertNotNull($selected);
        $this->assertSame($lowest->id, $selected->id);
        $this->assertNotSame($higher->id, $selected->id);
        $this->assertNotSame($sameCostLaterId->id, $selected->id);
    }

    public function test_excludes_offers_without_confirmation_or_with_expired_evidence(): void
    {
        $unconfirmed = $this->offer([
            'availability_confirmed_at' => null,
            'availability_confirmed_by' => null,
            'availability_expires_at' => Carbon::now()->addHour(),
            'wholesale_cost' => '1000.00',
        ]);
        $expired = $this->offer([
            'availability_expires_at' => Carbon::now()->subSecond(),
            'wholesale_cost' => '2000.00',
        ]);
        $valid = $this->offer(['wholesale_cost' => '3000.00']);

        $selected = app(SupplierOfferSelectionService::class)
            ->selectLowestCost($this->product, 'doorstep_delivery');

        $this->assertNotNull($selected);
        $this->assertSame($valid->id, $selected->id);
        $this->assertNotSame($unconfirmed->id, $selected->id);
        $this->assertNotSame($expired->id, $selected->id);
    }

    public function test_excludes_unavailable_inactive_and_soft_deleted_supplier_offers(): void
    {
        $this->offer(['is_available' => false, 'wholesale_cost' => '1000.00']);

        $inactiveVendor = $this->createOtherSupplier('inactive');
        $this->offer([
            'vendor_id' => $inactiveVendor['vendor']->id,
            'shop_id' => $inactiveVendor['shop']->id,
            'wholesale_cost' => '2000.00',
        ]);

        $deletedVendor = $this->createOtherSupplier('deleted');
        $deletedVendor['vendor']->delete();
        $this->offer([
            'vendor_id' => $deletedVendor['vendor']->id,
            'shop_id' => $deletedVendor['shop']->id,
            'wholesale_cost' => '2500.00',
        ]);

        $valid = $this->offer(['wholesale_cost' => '4000.00']);
        $selected = app(SupplierOfferSelectionService::class)
            ->selectLowestCost($this->product, 'doorstep_delivery');

        $this->assertNotNull($selected);
        $this->assertSame($valid->id, $selected->id);
    }

    public function test_pickup_requires_active_pickup_enabled_shop_owned_by_offer_supplier(): void
    {
        $pickupDisabled = $this->createOtherSupplier('no-pickup');
        $pickupDisabled['shop']->update(['pickup_enabled' => false]);
        $this->offer([
            'vendor_id' => $pickupDisabled['vendor']->id,
            'shop_id' => $pickupDisabled['shop']->id,
            'wholesale_cost' => '1000.00',
        ]);

        $valid = $this->offer(['wholesale_cost' => '3500.00']);
        $selected = app(SupplierOfferSelectionService::class)
            ->selectLowestCost($this->product, 'in_shop_pickup');

        $this->assertNotNull($selected);
        $this->assertSame($valid->id, $selected->id);
    }



    public function test_availability_confirmation_is_audited_and_retry_is_idempotent(): void
    {
        $offer = $this->offer();
        $expiry = Carbon::now()->addHours(2)->startOfSecond();
        $service = app(SupplierOfferAvailabilityService::class);

        $first = $service->confirm($offer->id, $this->admin, $expiry, 'Supplier confirmed stock');
        $confirmedAt = $first->availability_confirmed_at->toISOString();

        $second = $service->confirm($offer->id, $this->admin, $expiry, 'Supplier confirmed stock');

        $this->assertSame($first->id, $second->id);
        $this->assertSame($confirmedAt, $second->availability_confirmed_at->toISOString());
        $this->assertSame(1, AdminAuditLog::where('action', 'supplier_offer_availability_confirmed')
            ->where('target_id', $offer->id)->count());
        $this->assertSame($this->admin->id, $second->availability_confirmed_by);
    }

    public function test_availability_confirmation_rejects_non_admin_and_expired_evidence(): void
    {
        $offer = $this->offer();
        $customer = User::create([
            'name' => 'Ordinary Customer',
            'email' => 'availability-customer@example.test',
            'password' => bcrypt('test-password'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);
        $service = app(SupplierOfferAvailabilityService::class);

        try {
            $service->confirm($offer->id, $customer, Carbon::now()->addHour());
            $this->fail('Non-admin availability confirmation must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('super administrator', $exception->getMessage());
        }

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->confirm($offer->id, $this->admin, Carbon::now()->subSecond());
    }

    public function test_excludes_offer_when_shop_is_not_owned_by_that_supplier(): void
    {
        $otherSupplier = $this->createOtherSupplier('mismatched');
        $mismatched = $this->offer([
            'vendor_id' => $this->vendor->id,
            'shop_id' => $otherSupplier['shop']->id,
            'wholesale_cost' => '1000.00',
        ]);
        $valid = $this->offer(['wholesale_cost' => '3000.00']);

        $selected = app(SupplierOfferSelectionService::class)
            ->selectLowestCost($this->product, 'doorstep_delivery');

        $this->assertNotNull($selected);
        $this->assertSame($valid->id, $selected->id);
        $this->assertNotSame($mismatched->id, $selected->id);
    }

    public function test_returns_null_when_no_offer_is_eligible_and_rejects_unknown_fulfillment(): void
    {
        $this->offer([
            'availability_confirmed_at' => null,
            'availability_confirmed_by' => null,
            'availability_expires_at' => null,
        ]);

        $service = app(SupplierOfferSelectionService::class);
        $this->assertNull($service->selectLowestCost($this->product, 'doorstep_delivery'));

        $this->expectException(\InvalidArgumentException::class);
        $service->selectLowestCost($this->product, 'customer_chooses_supplier');
    }

    public function test_only_super_admin_can_confirm_supplier_availability_through_web_route(): void
    {
        $offer = $this->offer([
            'availability_confirmed_at' => null,
            'availability_confirmed_by' => null,
            'availability_expires_at' => null,
            'is_available' => false,
        ]);

        $payload = [
            'availability_expires_at' => Carbon::now()->addHours(2)->startOfSecond()->toDateTimeString(),
            'notes' => 'Phone confirmation received from supplier',
        ];

        $customer = User::create([
            'name' => 'Route Customer',
            'email' => 'route-customer@example.test',
            'password' => bcrypt('test-password'),
            'user_type' => 'customer',
            'status' => 'active',
        ]);

        $this->actingAs($customer)
            ->post(route('admin.supplier_offers.confirm_availability', $offer->id), $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('admin_audit_logs', [
            'action' => 'supplier_offer_availability_confirmed',
            'target_id' => $offer->id,
        ]);
        $this->assertFalse((bool) $offer->fresh()->is_available);

        $this->actingAs($this->admin)
            ->post(route('admin.supplier_offers.confirm_availability', $offer->id), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue((bool) $offer->fresh()->is_available);
        $this->assertSame(1, AdminAuditLog::where('action', 'supplier_offer_availability_confirmed')
            ->where('target_id', $offer->id)->count());
    }

    public function test_web_availability_route_rejects_expired_confirmation_without_writing_audit(): void
    {
        $offer = $this->offer([
            'availability_confirmed_at' => null,
            'availability_confirmed_by' => null,
            'availability_expires_at' => null,
            'is_available' => false,
        ]);

        $this->actingAs($this->admin)
            ->from('/admin')
            ->post(route('admin.supplier_offers.confirm_availability', $offer->id), [
                'availability_expires_at' => Carbon::now()->subMinute()->toDateTimeString(),
                'notes' => 'Expired evidence must not be accepted',
            ])
            ->assertSessionHasErrors('availability_expires_at');

        $this->assertFalse((bool) $offer->fresh()->is_available);
        $this->assertSame(0, AdminAuditLog::where('action', 'supplier_offer_availability_confirmed')
            ->where('target_id', $offer->id)->count());
    }

    public function test_catalogue_eligibility_requires_a_fresh_verified_offer_not_the_legacy_product_owner(): void
    {
        $this->assertFalse(
            Product::marketplaceEligible()->whereKey($this->product->id)->exists(),
            'A listed product without a verified eligible supplier offer must not be publicly eligible.'
        );

        $otherSupplier = $this->createOtherSupplier('catalogue-decoupling');
        $offer = $this->offer([
            'vendor_id' => $otherSupplier['vendor']->id,
            'shop_id' => $otherSupplier['shop']->id,
            'wholesale_cost' => '4200.00',
        ]);

        // The original legacy/default supplier is no longer active. The canonical
        // product remains eligible because a different verified supplier is active.
        $this->vendor->update(['marketplace_status' => 'suspended']);
        $this->shop->update(['is_active' => false]);

        $this->assertTrue(
            Product::marketplaceEligible()->whereKey($this->product->id)->exists()
        );

        $offer->update(['availability_expires_at' => Carbon::now()->subSecond()]);

        $this->assertFalse(
            Product::marketplaceEligible()->whereKey($this->product->id)->exists(),
            'Expired supplier evidence must remove the product from public sale eligibility.'
        );
    }

    public function test_canonical_product_can_exist_without_legacy_vendor_or_shop_ownership(): void
    {
        $canonical = Product::create([
            'vendor_id' => null,
            'shop_id' => null,
            'category_id' => $this->product->category_id,
            'name' => 'VM-Owned Canonical Product',
            'slug' => 'vm-owned-canonical-product',
            'unit_price' => '12000.00',
            'inventory_quantity' => 5,
            'is_in_stock' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subMinute(),
        ]);

        $this->offer([
            'product_id' => $canonical->id,
            'wholesale_cost' => '6500.00',
        ]);

        $this->assertNull($canonical->fresh()->vendor_id);
        $this->assertNull($canonical->fresh()->shop_id);
        $this->assertTrue(
            Product::marketplaceEligible()->whereKey($canonical->id)->exists()
        );
    }

    private function createOtherSupplier(string $suffix): array
    {
        $user = User::create([
            'name' => 'Supplier ' . $suffix,
            'email' => 'supplier-' . $suffix . '@example.test',
            'password' => bcrypt('test-password'),
            'user_type' => 'vendor',
            'status' => 'active',
        ]);
        $vendor = VendorProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Supplier ' . $suffix,
            'business_phone' => '08030000002',
            'marketplace_status' => $suffix === 'inactive' ? 'suspended' : 'active',
            'kyc_status' => $suffix === 'inactive' ? 'unverified' : 'verified',
        ]);
        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'country_id' => $this->shop->country_id,
            'state_id' => $this->shop->state_id,
            'lga_id' => $this->shop->lga_id,
            'name' => 'Shop ' . $suffix,
            'slug' => 'shop-' . $suffix,
            'address' => 'Uyo',
            'phone' => '08030000002',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);

        return ['vendor' => $vendor, 'shop' => $shop];
    }
    public function test_non_uyo_supplier_offer_is_not_selectable_or_confirmable(): void
    {
        $eket = Lga::create([
            'state_id' => $this->shop->state_id,
            'name' => 'Eket',
            'is_active' => true,
        ]);
        $outsideShop = Shop::create([
            'vendor_id' => $this->vendor->id,
            'country_id' => $this->shop->country_id,
            'state_id' => $this->shop->state_id,
            'lga_id' => $eket->id,
            'name' => 'Outside Uyo Shop',
            'slug' => 'outside-uyo-shop',
            'address' => 'Eket',
            'phone' => '08030000002',
            'is_active' => true,
            'pickup_enabled' => true,
        ]);
        $offer = $this->offer(['shop_id' => $outsideShop->id]);

        $selected = app(SupplierOfferSelectionService::class)
            ->selectLowestCost($this->product, 'doorstep_delivery');
        $this->assertNull($selected);

        try {
            app(SupplierOfferAvailabilityService::class)->confirm(
                $offer->id,
                $this->admin,
                Carbon::now()->addHour(),
                'Must be sourced from Uyo'
            );
            $this->fail('Offers from outside Uyo must not receive sourcing confirmation.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('supplier_offer', $exception->errors());
        }
    }

}
