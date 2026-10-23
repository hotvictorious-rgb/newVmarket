<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
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
            'supplierOffers' => fn ($offers) => $offers->with(['vendor.user', 'shop.lga'])->orderBy('id'),
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

        return view('admin.supplier_catalog', compact('products', 'categories', 'shops'));
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
        ]);

        DB::transaction(function () use ($request, $offerId, $data) {
            $offer = SupplierOffer::query()->lockForUpdate()->findOrFail($offerId);
            $product = Product::query()->find($offer->product_id);
            if (!$product || BcMathService::comp((string) $data['wholesale_cost'], (string) $product->unit_price) >= 0) {
                throw ValidationException::withMessages(['wholesale_cost' => 'Wholesale cost must be lower than Victorious MARKET retail price.']);
            }
            $old = ['wholesale_cost' => (string) $offer->wholesale_cost];
            $offer->forceFill(['wholesale_cost' => $data['wholesale_cost']])->save();
            $this->audit($request, 'supplier_offer_wholesale_cost_updated', SupplierOffer::class, $offer->id, $old, [
                'wholesale_cost' => (string) $offer->wholesale_cost,
                'product_id' => $offer->product_id,
                'vendor_id' => $offer->vendor_id,
                'shop_id' => $offer->shop_id,
            ]);
        }, 3);

        return back()->with('success', 'Wholesale cost updated and audited. Availability confirmation was not changed.');
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
                'availability_expires_at' => $offer->availability_expires_at?->toISOString(),
            ];
            $offer->forceFill([
                'is_available' => false,
                'notes' => trim($data['reason']),
            ])->save();

            $this->audit($request, 'supplier_offer_admin_disabled', SupplierOffer::class, $offer->id, $old, [
                'is_available' => false,
                'reason' => trim($data['reason']),
                'product_id' => $offer->product_id,
                'vendor_id' => $offer->vendor_id,
                'shop_id' => $offer->shop_id,
            ]);
        }, 3);

        return back()->with('success', 'Offer disabled for new allocations. Existing paid order snapshots and assignments were not changed. Reconfirm availability before reactivation.');
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
