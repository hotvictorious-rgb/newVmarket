<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Shop;
use App\Models\SupplierCatalogProduct;
use App\Models\SupplierOffer;
use App\Models\VendorProfile;
use App\Services\BcMathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorPrivateCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$vendor, $shop] = $this->supplierContext($request);

        $records = SupplierCatalogProduct::where('supplier_vendor_id', $vendor->id)
            ->where('supplier_shop_id', $shop->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $records->map(fn (SupplierCatalogProduct $record) => $this->supplierSafeRecord($record))->values(),
            'policy' => [
                'stock_status' => ['in_stock', 'out_of_stock'],
                'quantity_field' => null,
                'message' => 'Catalog stock is a binary signal only. Victorious MARKET confirms the exact quantity for each paid order line after payment verification.',
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->rejectProtectedFields($request);
        [$vendor, $shop] = $this->supplierContext($request);
        $data = $request->validate([
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

        $record = DB::transaction(function () use ($request, $vendor, $shop, $data) {
            $record = SupplierCatalogProduct::create([
                'supplier_vendor_id' => $vendor->id,
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

            $this->audit($request, $record, 'supplier_private_catalog_product_created', null, $this->auditValues($record));
            return $record;
        }, 3);

        return response()->json([
            'status' => true,
            'message' => 'Private supplier catalog record saved. It is not a public listing and does not reserve stock or bind an order.',
            'data' => $this->supplierSafeRecord($record),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->rejectProtectedFields($request);
        [$vendor, $shop] = $this->supplierContext($request);
        $data = $request->validate([
            'product_name' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'photo_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:120'],
            'model' => ['sometimes', 'nullable', 'string', 'max:180'],
            'variant' => ['sometimes', 'nullable', 'string', 'max:180'],
            'condition' => ['sometimes', 'nullable', 'in:brand_new,uk_used'],
            'package_tier' => ['sometimes', 'nullable', 'in:bike,keke,standard,bulky,fragile'],
            'specifications' => ['sometimes', 'nullable', 'array'],
            'source_price' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:100000000'],
            'is_in_stock' => ['sometimes', 'required', 'boolean'],
            'status' => ['sometimes', 'required', 'in:active,disabled'],
        ]);

        $record = DB::transaction(function () use ($request, $id, $vendor, $shop, $data) {
            $record = SupplierCatalogProduct::where('supplier_vendor_id', $vendor->id)
                ->where('supplier_shop_id', $shop->id)
                ->lockForUpdate()
                ->findOrFail($id);

            $old = $this->auditValues($record);
            $stockChanged = array_key_exists('is_in_stock', $data)
                && (bool) $data['is_in_stock'] !== (bool) $record->is_in_stock;
            $statusChanged = array_key_exists('status', $data) && $data['status'] !== $record->status;

            $changes = $data;
            foreach (['product_name', 'brand', 'model', 'variant'] as $field) {
                if (isset($changes[$field])) {
                    $changes[$field] = trim((string) $changes[$field]);
                }
            }
            if (isset($changes['source_price'])) {
                $changes['source_price'] = BcMathService::add((string) $changes['source_price'], '0.00');
            }
            $record->fill($changes)->save();

            $offer = SupplierOffer::where('supplier_catalog_product_id', $record->id)->lockForUpdate()->first();
            if ($offer) {
                $offerChanges = [
                    'wholesale_cost' => (string) $record->source_price,
                    'is_available' => $record->status === 'active' && (bool) $record->is_in_stock && !(bool) $offer->is_admin_disabled,
                ];
                if ($stockChanged || $statusChanged) {
                    // Any supplier stock-state transition invalidates the previous
                    // confirmation. In-stock is a signal, not order quantity evidence.
                    $offerChanges['availability_confirmed_at'] = null;
                    $offerChanges['availability_confirmed_by'] = null;
                    $offerChanges['availability_expires_at'] = null;
                }
                $offer->forceFill($offerChanges)->save();
            }

            $this->audit($request, $record, 'supplier_private_catalog_product_updated', $old, $this->auditValues($record) + [
                'stock_confirmation_invalidated' => $stockChanged || $statusChanged,
                'linked_offer_id' => $offer?->id,
            ]);
            return $record->fresh();
        }, 3);

        return response()->json([
            'status' => true,
            'message' => 'Private catalog record updated. Stock-state changes invalidate the previous offer confirmation; no quantity is stored and no order or financial state was changed.',
            'data' => $this->supplierSafeRecord($record),
        ]);
    }

    private function rejectProtectedFields(Request $request): void
    {
        foreach (['quantity', 'inventory_quantity'] as $field) {
            if ($request->exists($field)) {
                throw ValidationException::withMessages([
                    $field => 'Supplier catalog is binary-stock only. Do not submit quantities; use is_in_stock for the stock signal.',
                ]);
            }
        }

        foreach (['canonical_product_id', 'supplier_vendor_id', 'supplier_shop_id', 'supplier_offer_id'] as $field) {
            if ($request->exists($field)) {
                throw ValidationException::withMessages([
                    $field => 'Supplier ownership and canonical product matching are controlled by Victorious MARKET administrators.',
                ]);
            }
        }
    }

    private function supplierContext(Request $request): array
    {
        $user = $request->user();
        if (!$user || $user->status !== 'active' || !$user->isVendor()) {
            abort(403, 'Only an active supplier account owner can manage its private catalog.');
        }

        $vendor = VendorProfile::where('user_id', $user->id)->firstOrFail();
        if ($vendor->marketplace_status !== 'active' || !$vendor->isKycVerified()) {
            abort(403, 'Supplier account must be active, approved and verified.');
        }

        $shops = Shop::with(['lga.state'])
            ->where('vendor_id', $vendor->id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->get();

        if ($shops->count() !== 1) {
            throw ValidationException::withMessages(['shop' => 'Supplier catalog access requires exactly one active approved shop/pickup point.']);
        }
        $shop = $shops->first();
        if (strtolower(trim((string) ($shop->lga->name ?? ''))) !== 'uyo'
            || strtolower(trim((string) ($shop->lga->state->name ?? ''))) !== 'akwa ibom') {
            abort(403, 'Supplier catalog records must be maintained for the approved Uyo shop only.');
        }

        return [$vendor, $shop];
    }

    private function supplierSafeRecord(SupplierCatalogProduct $record): array
    {
        return [
            'id' => (int) $record->id,
            'product_name' => $record->product_name,
            'description' => $record->description,
            'photo_path' => $record->photo_path,
            'brand' => $record->brand,
            'model' => $record->model,
            'variant' => $record->variant,
            'condition' => $record->condition,
            'package_tier' => $record->package_tier,
            'specifications' => $record->specifications,
            'source_price' => (string) $record->source_price,
            'stock_status' => $record->is_in_stock ? 'in_stock' : 'out_of_stock',
            'status' => $record->status,
            'is_linked_to_market_listing' => $record->canonical_product_id !== null,
            'updated_at' => $record->updated_at?->toISOString(),
        ];
    }

    private function auditValues(SupplierCatalogProduct $record): array
    {
        return [
            'supplier_vendor_id' => $record->supplier_vendor_id,
            'supplier_shop_id' => $record->supplier_shop_id,
            'product_name' => $record->product_name,
            'source_price' => (string) $record->source_price,
            'is_in_stock' => (bool) $record->is_in_stock,
            'status' => $record->status,
            'canonical_product_id' => $record->canonical_product_id,
        ];
    }

    private function audit(Request $request, SupplierCatalogProduct $record, string $action, ?array $old, array $new): void
    {
        AdminAuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'target_type' => SupplierCatalogProduct::class,
            'target_id' => $record->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) ($request->userAgent() ?? 'Supplier/API'), 0, 1000),
        ]);
    }
}
