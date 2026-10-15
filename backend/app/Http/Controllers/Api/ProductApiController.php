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
}
