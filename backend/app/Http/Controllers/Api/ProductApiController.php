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
            ->whereHas('shop.lga', function ($q) {
                $q->where('name', 'Uyo')
                    ->whereHas('state', function ($stateQuery) {
                        $stateQuery->where('name', 'Akwa Ibom');
                    });
            })
            // Supplier/shop identity is private in the single-retailer model.
            ->with(['shop:id,lga_id', 'category:id,name,slug']);

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

        // Ignore legacy shop_id filters: customers cannot select or enumerate private suppliers.

        $products = $query->paginate(20);
        $products->getCollection()->each(static function ($product): void {
            $product->makeHidden(['shop_id', 'shop']);
        });

        return response()->json([
            'status' => true,
            'message' => 'Products retrieved successfully.',
            'data' => $products,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::marketplaceEligible()
            ->whereHas('shop.lga', function ($q) {
                $q->where('name', 'Uyo')
                    ->whereHas('state', function ($stateQuery) {
                        $stateQuery->where('name', 'Akwa Ibom');
                    });
            })
            // Do not serialize supplier identity, address, slug, or pickup settings to customers.
            ->with(['shop:id,lga_id', 'category:id,name,slug'])
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
        // Supplier shops are private fulfilment sources, not public marketplace storefronts.
        // Retain this endpoint for older clients, but return no private supplier records.
        return response()->json([
            'status' => true,
            'message' => 'Victorious MARKET is a single retailer. Supplier shop details are private. Service area: Uyo LGA, Akwa Ibom State only.',
            'data' => [],
        ]);
    }
}
