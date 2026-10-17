<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductApiController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->orderBy('priority', 'asc')
            ->select('id', 'parent_id', 'name', 'slug', 'icon', 'image')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Categories retrieved successfully.',
            'data' => $categories,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Product::marketplaceEligible()
            ->with(['shop:id,name,slug,lga_id', 'category:id,name,slug']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('lga_id')) {
            $lgaId = $request->integer('lga_id');
            $query->whereHas('shop', function ($q) use ($lgaId) {
                $q->where('lga_id', $lgaId);
            });
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->string('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        if ($request->boolean('flagship_only')) {
            $query->where('is_vmarket_flagship', true);
        }

        if ($request->filled('condition')) {
            $condition = (string)$request->input('condition');
            if (in_array($condition, ['brand_new', 'uk_used'], true)) {
                $query->where('condition', $condition);
            }
        }

        if ($request->filled('package_tier')) {
            $tier = strtolower((string)$request->input('package_tier'));
            if (in_array($tier, ['bike', 'keke', 'standard', 'bulky'], true)) {
                $mappedTier = in_array($tier, ['keke', 'bulky'], true) ? ['keke', 'bulky'] : ['bike', 'standard'];
                $query->whereIn('package_tier', $mappedTier);
            }
        }

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->integer('shop_id'));
        }

        $products = $query->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'Products retrieved successfully.',
            'data' => $products,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::marketplaceEligible()
            ->with(['shop:id,name,slug,address,lga_id,pickup_enabled,pickup_opening_time,pickup_closing_time', 'category:id,name,slug'])
            ->find($id);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found or not currently listed.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $product,
        ]);
    }
    public function shops(Request $request): JsonResponse
    {
        $query = \App\Models\Shop::where('is_active', true)
            ->with(['lga:id,name', 'vendor:id,business_name,user_id'])
            ->orderBy('is_primary_branch', 'desc');

        if ($request->filled('lga_id')) {
            $query->where('lga_id', $request->integer('lga_id'));
        }

        $shops = $query->get()->map(function ($shop) {
            return [
                'id' => $shop->id,
                'name' => $shop->name ?: ($shop->vendor?->business_name ?? 'Merchant Shop'),
                'slug' => $shop->slug,
                'address' => $shop->address,
                'lga_id' => $shop->lga_id,
                'lga_name' => $shop->lga?->name,
                'logo_url' => url('assets/img/vm_icon.jpg'),
                'pickup_enabled' => (bool)$shop->pickup_enabled,
                'pickup_opening_time' => $shop->pickup_opening_time ? substr((string)$shop->pickup_opening_time, 0, 5) : '08:00',
                'pickup_closing_time' => $shop->pickup_closing_time ? substr((string)$shop->pickup_closing_time, 0, 5) : '18:00',
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Shops retrieved successfully.',
            'data' => $shops,
        ]);
    }
}
