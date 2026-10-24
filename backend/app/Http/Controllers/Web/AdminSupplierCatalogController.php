<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SupplierOffer;
use App\Models\SupplierCatalogProduct;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BcMathService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSupplierCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->with([
            'category',
            'supplierOffers' => fn ($offers) => $offers->withTrashed()->with(['vendor.user', 'shop.lga'])->orderBy('id'),
        ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($products) use ($search) {
                $products->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('listing_status') && in_array($request->input('listing_status'), ['listed', 'unlisted', 'delisted'], true)) {
            $query->where('listing_status', $request->input('listing_status'));
        }

        $products = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();
        $supplierCatalogProducts = SupplierCatalogProduct::withTrashed()
            ->with(['supplier.user', 'shop.lga', 'canonicalProduct', 'linkedOffer' => fn ($offers) => $offers->withTrashed()])
            ->orderByDesc('updated_at')
            ->paginate(20, ['*'], 'supplier_catalog_page')
            ->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $shops = Shop::query()
            ->with(['vendor.user', 'lga.state'])
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereHas('vendor', function ($vendor) {
                $vendor->whereNull('deleted_at')
                    ->where('marketplace_status', 'active')
                    ->where('kyc_status', 'verified')
                    ->whereHas('user', fn ($user) => $user->where('status', 'active'));
            })
            ->whereHas('lga', function ($lga) {
                $lga->whereRaw('LOWER(TRIM(lgas.name)) = ?', ['uyo'])
                    ->whereHas('state', fn ($state) => $state->whereRaw('LOWER(TRIM(states.name)) = ?', ['akwa ibom']));
            })
            ->orderBy('name')
            ->get()
            ->filter(fn (Shop $shop) => Shop::where('vendor_id', $shop->vendor_id)->whereNull('deleted_at')->where('is_active', true)->count() === 1)
            ->values();

        return view('admin.supplier_catalog', compact('products', 'categories', 'shops', 'supplierCatalogProducts'));
    }

    public function storeSupplierCatalogProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'product_name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'photo_path' => ['nullable', 'string', 'max:2048'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:180'],
            'variant' => ['nullable', 'string', 'max:180'],
            'condition' => ['nullable', 'in:brand_new,uk_used'],
            'package_tier' => ['nullable', 'in:bike,keke,standard,bulky,fragile'],
            'specifications' => ['nullable', 'array'],
            'source_price' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'is_in_stock' => ['required', 'boolean'],
        ]);

        $shop = Shop::with(['vendor.user', 'lga.state'])->findOrFail($data['shop_id']);
        $this->assertApprovedUyoShop($shop);

        $record = DB::transaction(function () use ($request, $data, $shop) {
            $record = SupplierCatalogProduct::create([
                'supplier_vendor_id' => $shop->vendor_id,
                'supplier_shop_id' => $shop->id,
                'product_name' => trim($data['product_name']),
                'description' => $data['description'] ?? null,
                'photo_path' => $data['photo_path'] ?? null,
                'brand' => isset($data['brand']) ? trim($data['brand']) : null,
                'model' => isset($data['model']) ? trim($data['model']) : null,
                'variant' => isset($data['variant']) ? trim($data['variant']) : null,
                'condition' => $data['condition'] ?? null,
                'package_tier' => $data['package_tier'] ?? null,
                'specifications' => $data['specifications'] ?? null,
                'source_price' => BcMathService::add((string) $data['source_price'], '0.00'),
                'is_in_stock' => (bool) $data['is_in_stock'],
                'status' => 'active',
            ]);

            $this->audit($request, 'supplier_private_catalog_product_created', SupplierCatalogProduct::class, $record->id, null, [
                'supplier_vendor_id' => $record->supplier_vendor_id,
                'supplier_shop_id' => $record->supplier_shop_id,
                'product_name' => $record->product_name,
                'source_price' => (string) $record->source_price,
                'is_in_stock' => (bool) $record->is_in_stock,
                'canonical_product_id' => null,
            ]);

            return $record;
        }, 3);

        return back()->with('success', "Private supplier catalog record #{$record->id} created. It is not public and has not been matched to a canonical product.");
    }

    public function linkSupplierCatalogProduct(Request $request, int $catalogProductId): RedirectResponse
    {
        $data = $request->validate([
            'canonical_product_id' => ['required', 'integer', 'exists:products,id'],
            'match_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $catalogProductId, $data) {
            $record = SupplierCatalogProduct::query()->lockForUpdate()->findOrFail($catalogProductId);
            if ($record->status !== 'active' || $record->trashed()) {
                throw ValidationException::withMessages(['canonical_product_id' => 'Only an active private supplier catalog record can be matched.']);
            }

            $shop = Shop::with(['vendor.user', 'lga.state'])->lockForUpdate()->findOrFail($record->supplier_shop_id);
            $this->assertApprovedUyoShop($shop);
            if ((int) $shop->vendor_id !== (int) $record->supplier_vendor_id) {
                throw ValidationException::withMessages(['canonical_product_id' => 'The supplier record no longer belongs to its approved shop.']);
            }

            $product = Product::query()->lockForUpdate()->findOrFail($data['canonical_product_id']);
            if (BcMathService::comp((string) $record->source_price, (string) $product->unit_price) >= 0) {
                throw ValidationException::withMessages(['canonical_product_id' => 'This match would have no positive retail margin. Set a viable retail price before linking the record.']);
            }

            if ($record->canonical_product_id !== null && (int) $record->canonical_product_id !== (int) $product->id) {
                throw ValidationException::withMessages(['canonical_product_id' => 'This supplier record is already linked to another canonical product. Resolve the existing match through an audited correction; it cannot be attached to two products.']);
            }

            $offer = SupplierOffer::withTrashed()
                ->where('supplier_catalog_product_id', $record->id)
                ->lockForUpdate()
                ->first();

            if (!$offer) {
                $offer = SupplierOffer::withTrashed()
                    ->where('product_id', $product->id)
                    ->where('vendor_id', $record->supplier_vendor_id)
                    ->where('shop_id', $record->supplier_shop_id)
                    ->lockForUpdate()
                    ->first();
            }

            if ($offer && $offer->supplier_catalog_product_id
                && (int) $offer->supplier_catalog_product_id !== (int) $record->id) {
                throw ValidationException::withMessages(['canonical_product_id' => 'Another private supplier catalog record already owns this supplier offer.']);
            }
            if ($offer && (int) $offer->product_id !== (int) $product->id) {
                throw ValidationException::withMessages(['canonical_product_id' => 'The existing supplier offer belongs to a different canonical product. Retire and reconcile it before rematching.']);
            }

            $old = [
                'canonical_product_id' => $record->canonical_product_id,
                'supplier_offer_id' => $offer?->id,
                'source_price' => (string) $record->source_price,
            ];

            if (!$offer) {
                $offer = new SupplierOffer();
                $offer->product_id = $product->id;
                $offer->vendor_id = $record->supplier_vendor_id;
                $offer->shop_id = $record->supplier_shop_id;
            } elseif ($offer->trashed()) {
                $offer->restore();
            }

            $offer->forceFill([
                'supplier_catalog_product_id' => $record->id,
                'product_id' => $product->id,
                'vendor_id' => $record->supplier_vendor_id,
                'shop_id' => $record->supplier_shop_id,
                'wholesale_cost' => BcMathService::add((string) $record->source_price, '0.00'),
                'is_available' => (bool) $record->is_in_stock && !(bool) $offer->is_admin_disabled,
                // A supplier's binary stock signal is not the admin's fresh
                // availability confirmation and cannot make this offer selectable.
                'availability_confirmed_at' => null,
                'availability_confirmed_by' => null,
                'availability_expires_at' => null,
            ])->save();

            $record->forceFill([
                'canonical_product_id' => $product->id,
                'linked_by_admin_id' => $request->user()->id,
                'linked_at' => now(),
                'match_notes' => isset($data['match_notes']) ? trim($data['match_notes']) : null,
            ])->save();

            $this->audit($request, 'supplier_private_catalog_product_matched', SupplierCatalogProduct::class, $record->id, $old, [
                'canonical_product_id' => $product->id,
                'supplier_offer_id' => $offer->id,
                'supplier_vendor_id' => $record->supplier_vendor_id,
                'supplier_shop_id' => $record->supplier_shop_id,
                'source_price' => (string) $record->source_price,
                'offer_available_signal' => (bool) $offer->is_available,
                'availability_confirmation_cleared' => true,
                'match_notes' => $record->match_notes,
            ]);
        }, 3);

        return back()->with('success', 'Private supplier record matched to the canonical product. Its binary stock signal is recorded, but the offer remains unselectable until fresh admin availability confirmation.');
    }

    private function assertApprovedUyoShop(Shop $shop): void
    {
        $vendor = $shop->vendor;
        if (!$vendor || $vendor->trashed() || $vendor->marketplace_status !== 'active'
            || $vendor->kyc_status !== 'verified' || !$vendor->user || $vendor->user->status !== 'active'
            || $shop->trashed() || !$shop->is_active
            || strtolower(trim((string) ($shop->lga->name ?? ''))) !== 'uyo'
            || strtolower(trim((string) ($shop->lga->state->name ?? ''))) !== 'akwa ibom'
            || Shop::where('vendor_id', $shop->vendor_id)->whereNull('deleted_at')->where('is_active', true)->count() !== 1) {
            throw ValidationException::withMessages([
                'shop_id' => 'Choose the active, verified supplier’s single approved shop in Uyo, Akwa Ibom State.',
            ]);
        }
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit_price' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'condition' => ['required', 'in:brand_new,uk_used'],
            'package_tier' => ['required', 'in:bike,keke,standard,bulky,fragile'],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
        ]);

        $name = trim($data['name']);
        $slug = Str::slug($name);
        if ($slug === '') {
            throw ValidationException::withMessages(['name' => 'Enter a product name that can be used as a catalog identifier.']);
        }

        $duplicate = Product::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->where('condition', $data['condition'])
            ->where('package_tier', $data['package_tier'])
            ->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => "A matching catalog product already exists (product #{$duplicate->id}). Attach the supplier offer to that product instead of creating a duplicate.",
            ]);
        }

        if (Product::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'name' => 'This product name already has a catalog slug. Search for the existing product and attach an offer, or refine the name to identify the actual variant.',
            ]);
        }

        $product = DB::transaction(function () use ($data, $name, $slug, $request) {
            $product = Product::create([
                'vendor_id' => null,
                'shop_id' => null,
                'category_id' => $data['category_id'],
                'name' => $name,
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'thumbnail' => $data['thumbnail'] ?? null,
                'unit_price' => $data['unit_price'],
                'condition' => $data['condition'],
                'package_tier' => $data['package_tier'],
                'is_in_stock' => true,
                // Zero means no legacy product-level stock counter. Supplier availability
                // is recorded on SupplierOffer; the public offer gate still fails closed.
                'inventory_quantity' => 0,
                'listing_status' => 'unlisted',
                'listing_confirmed_at' => null,
                'is_approved' => false,
                'is_vmarket_flagship' => false,
            ]);

            $this->audit($request, 'canonical_product_created', Product::class, $product->id, null, [
                'name' => $product->name,
                'slug' => $product->slug,
                'category_id' => $product->category_id,
                'unit_price' => (string) $product->unit_price,
                'condition' => $product->condition,
                'package_tier' => $product->package_tier,
                'listing_status' => $product->listing_status,
            ]);

            return $product;
        }, 3);

        return redirect()->route('admin.supplier-catalog.index')
            ->with('success', "Canonical product #{$product->id} created as an unpublished draft. Attach and confirm an eligible supplier offer before it can be purchased.");
    }

    public function storeOffer(Request $request, int $productId): RedirectResponse
    {
        $data = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'wholesale_cost' => ['required', 'numeric', 'gt:0', 'max:100000000'],
        ]);

        $product = Product::query()->findOrFail($productId);
        if (BcMathService::comp((string) $data['wholesale_cost'], (string) $product->unit_price) >= 0) {
            throw ValidationException::withMessages(['wholesale_cost' => 'Wholesale cost must be lower than Victorious MARKET retail price.']);
        }

        $shop = Shop::query()->with(['vendor.user', 'lga.state'])->findOrFail($data['shop_id']);
        $vendor = $shop->vendor;

        if (!$vendor || $vendor->trashed() || $vendor->marketplace_status !== 'active'
            || $vendor->kyc_status !== 'verified' || !$vendor->user || $vendor->user->status !== 'active'
            || $shop->trashed() || !$shop->is_active
            || strtolower(trim((string) ($shop->lga->name ?? ''))) !== 'uyo'
            || strtolower(trim((string) ($shop->lga->state->name ?? ''))) !== 'akwa ibom'
            || Shop::where('vendor_id', $shop->vendor_id)->whereNull('deleted_at')->where('is_active', true)->count() !== 1) {
            throw ValidationException::withMessages([
                'shop_id' => 'Choose the active, verified supplier’s single approved shop in Uyo, Akwa Ibom State.',
            ]);
        }

        $offer = DB::transaction(function () use ($request, $product, $shop, $data) {
            Product::query()->lockForUpdate()->findOrFail($product->id);
            $existing = SupplierOffer::withTrashed()
                ->where('product_id', $product->id)
                ->where('vendor_id', $shop->vendor_id)
                ->where('shop_id', $shop->id)
                ->first();

            if ($existing && !$existing->trashed()) {
                throw ValidationException::withMessages([
                    'shop_id' => 'This supplier already has an offer attached to this product. Edit or manage the existing offer instead.',
                ]);
            }

            if ($existing && $existing->trashed()) {
                $old = ['deleted_at' => $existing->deleted_at?->toISOString(), 'wholesale_cost' => (string) $existing->wholesale_cost];
                $existing->restore();
                $existing->forceFill([
                    'wholesale_cost' => $data['wholesale_cost'],
                    'is_available' => false,
                    'availability_confirmed_at' => null,
                    'availability_confirmed_by' => null,
                    'availability_expires_at' => null,
                ])->save();
                $offer = $existing;
                $action = 'supplier_offer_restored_unconfirmed';
            } else {
                $old = null;
                $offer = SupplierOffer::create([
                    'product_id' => $product->id,
                    'vendor_id' => $shop->vendor_id,
                    'shop_id' => $shop->id,
                    'wholesale_cost' => $data['wholesale_cost'],
                    'is_available' => false,
                    'availability_confirmed_at' => null,
                    'availability_confirmed_by' => null,
                    'availability_expires_at' => null,
                    'notes' => null,
                ]);
                $action = 'supplier_offer_attached_unconfirmed';
            }

            $this->audit($request, $action, SupplierOffer::class, $offer->id, $old, [
                'product_id' => $product->id,
                'vendor_id' => $shop->vendor_id,
                'shop_id' => $shop->id,
                'wholesale_cost' => (string) $offer->wholesale_cost,
                'is_available' => false,
            ]);

            return $offer;
        }, 3);

        return redirect()->route('admin.supplier-catalog.index')
            ->with('success', "Supplier offer #{$offer->id} attached. It remains unavailable until an administrator records a current availability confirmation.");
    }

    public function updateOfferCost(Request $request, int $offerId): RedirectResponse
    {
        $data = $request->validate([
            'wholesale_cost' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $offerId, $data) {
            $offer = SupplierOffer::query()->lockForUpdate()->findOrFail($offerId);
            $product = Product::query()->find($offer->product_id);
            if (!$product || BcMathService::comp((string) $data['wholesale_cost'], (string) $product->unit_price) >= 0) {
                throw ValidationException::withMessages(['wholesale_cost' => 'Wholesale cost must be lower than Victorious MARKET retail price.']);
            }
            $old = ['wholesale_cost' => (string) $offer->wholesale_cost];
            $catalogRecord = $offer->supplier_catalog_product_id
                ? SupplierCatalogProduct::whereKey($offer->supplier_catalog_product_id)->lockForUpdate()->first()
                : null;

            $offer->forceFill(['wholesale_cost' => $data['wholesale_cost']])->save();
            if ($catalogRecord) {
                $old['supplier_catalog_source_price'] = (string) $catalogRecord->source_price;
                $catalogRecord->forceFill(['source_price' => $data['wholesale_cost']])->save();
                $this->audit($request, 'admin_supplier_source_price_corrected', SupplierCatalogProduct::class, $catalogRecord->id, $old, [
                    'source_price' => (string) $catalogRecord->source_price,
                    'supplier_offer_id' => $offer->id,
                    'reason' => trim($data['reason']),
                ]);
            }
            $this->audit($request, 'supplier_offer_wholesale_cost_updated', SupplierOffer::class, $offer->id, $old, [
                'wholesale_cost' => (string) $offer->wholesale_cost,
                'product_id' => $offer->product_id,
                'vendor_id' => $offer->vendor_id,
                'shop_id' => $offer->shop_id,
                'reason' => trim($data['reason']),
                'linked_private_catalog_record' => $catalogRecord !== null,
            ]);
        }, 3);

        return back()->with('success', 'Source price correction recorded with a required reason. Availability confirmation was not changed.');
    }

    public function disableOffer(Request $request, int $offerId): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $offerId, $data) {
            $offer = SupplierOffer::query()->lockForUpdate()->findOrFail($offerId);
            $old = [
                'is_available' => (bool) $offer->is_available,
                'is_admin_disabled' => (bool) $offer->is_admin_disabled,
                'admin_disabled_reason' => $offer->admin_disabled_reason,
                'availability_expires_at' => $offer->availability_expires_at?->toISOString(),
            ];
            $offer->forceFill([
                'is_available' => false,
                'is_admin_disabled' => true,
                'admin_disabled_reason' => trim($data['reason']),
                'notes' => trim($data['reason']),
            ])->save();

            $this->audit($request, 'supplier_offer_admin_disabled', SupplierOffer::class, $offer->id, $old, [
                'is_available' => false,
                'is_admin_disabled' => true,
                'reason' => trim($data['reason']),
                'product_id' => $offer->product_id,
                'vendor_id' => $offer->vendor_id,
                'shop_id' => $offer->shop_id,
            ]);
        }, 3);

        return back()->with('success', 'Offer disabled for new allocations. Existing paid order snapshots and assignments were not changed. Reconfirm availability before reactivation.');
    }

    public function enableOffer(Request $request, int $offerId): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $offerId, $data) {
            $offer = SupplierOffer::query()->lockForUpdate()->findOrFail($offerId);
            if (!$offer->is_admin_disabled) {
                throw ValidationException::withMessages(['reason' => 'This offer is not administratively disabled.']);
            }

            $catalogRecord = $offer->supplier_catalog_product_id
                ? SupplierCatalogProduct::query()->whereKey($offer->supplier_catalog_product_id)->lockForUpdate()->first()
                : null;
            $stockSignalAvailable = $catalogRecord
                ? ($catalogRecord->status === 'active' && (bool) $catalogRecord->is_in_stock)
                : true;
            $old = [
                'is_available' => (bool) $offer->is_available,
                'is_admin_disabled' => true,
                'admin_disabled_reason' => $offer->admin_disabled_reason,
            ];

            $offer->forceFill([
                'is_admin_disabled' => false,
                'admin_disabled_reason' => null,
                'is_available' => $stockSignalAvailable,
                'availability_confirmed_at' => null,
                'availability_confirmed_by' => null,
                'availability_expires_at' => null,
                'notes' => trim($data['reason']),
            ])->save();

            $this->audit($request, 'supplier_offer_admin_reenabled_unconfirmed', SupplierOffer::class, $offer->id, $old, [
                'is_available' => (bool) $offer->is_available,
                'is_admin_disabled' => false,
                'confirmation_cleared' => true,
                'reason' => trim($data['reason']),
                'supplier_catalog_product_id' => $offer->supplier_catalog_product_id,
                'product_id' => $offer->product_id,
                'vendor_id' => $offer->vendor_id,
                'shop_id' => $offer->shop_id,
            ]);
        }, 3);

        return back()->with('success', 'Offer re-enabled with an audit reason. Fresh admin availability confirmation is required before sourcing can use it; existing SKU cooldowns remain in force.');
    }

    public function setListing(Request $request, int $productId): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:list,delist']]);

        DB::transaction(function () use ($request, $productId, $data) {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            $old = [
                'is_approved' => (bool) $product->is_approved,
                'listing_status' => $product->listing_status,
                'listing_confirmed_at' => $product->listing_confirmed_at?->toISOString(),
            ];

            if ($data['action'] === 'list') {
                $product->forceFill([
                    'is_approved' => true,
                    'listing_status' => 'listed',
                    'listing_confirmed_at' => now(),
                    'is_in_stock' => true,
                ])->save();
            } else {
                $product->forceFill(['listing_status' => 'delisted'])->save();
            }

            $this->audit($request, $data['action'] === 'list' ? 'canonical_product_listed' : 'canonical_product_manually_delisted',
                Product::class, $product->id, $old, [
                    'is_approved' => (bool) $product->is_approved,
                    'listing_status' => $product->listing_status,
                    'listing_confirmed_at' => $product->listing_confirmed_at?->toISOString(),
                ]);
        }, 3);

        return back()->with('success', $data['action'] === 'list'
            ? 'Product listing approved. It will be purchasable only while at least one supplier offer is eligible.'
            : 'Product manually delisted. Supplier availability will not override this decision.');
    }

    private function audit(Request $request, string $action, string $targetType, int $targetId, ?array $old, array $new): void
    {
        AdminAuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
