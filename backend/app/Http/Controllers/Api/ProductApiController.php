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
            ->with(['category:id,name,slug']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('lga_id')) {
            $requestedLga = \App\Models\Lga::with('state')->find($request->integer('lga_id'));
            if (!$requestedLga
                || strtolower(trim((string) $requestedLga->name)) !== 'uyo'
                || strtolower(trim((string) ($requestedLga->state->name ?? ''))) !== 'akwa ibom') {
                return response()->json([
                    'status' => false,
                    'message' => 'Victorious MARKET currently serves customers only within Uyo, Akwa Ibom State.',
                    'data' => [],
                ], 422);
            }
        }

        // Public catalog queries cannot select a private supplier shop.
        if ($request->filled('shop_id')) {
            return response()->json([
                'status' => false,
                'message' => 'Supplier shop filtering is not supported by Victorious MARKET.',
            ], 410);
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

        $products = $query->paginate(20);
        $products->getCollection()->each(function (Product $product): void {
            $product->makeHidden([
                'vendor_id',
                'shop_id',
                'cost_price',
                'supplierOffers',
                'vendor',
                'shop',
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Victorious MARKET products retrieved successfully.',
            'data' => $products,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::marketplaceEligible()
            ->with(['category:id,name,slug'])
            ->find($id);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found or not currently listed.',
            ], 404);
        }

        $product->makeHidden([
            'vendor_id',
            'shop_id',
            'cost_price',
            'supplierOffers',
            'vendor',
            'shop',
        ]);

        return response()->json([
            'status' => true,
            'data' => $product,
        ]);
    }
    public function shops(Request $request): JsonResponse
    {
        // Private supplier locations are not a customer-facing marketplace directory.
        return response()->json([
            'status' => false,
            'message' => 'Supplier shop discovery is retired. Browse products through Victorious MARKET; any pickup exception must be arranged through central support.',
            'data' => [],
        ], 410);
    }
}
