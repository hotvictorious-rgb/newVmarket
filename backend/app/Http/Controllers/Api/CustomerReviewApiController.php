<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CustomerReviewApiController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService
    ) {}

    /**
     * Submit a verified product review.
     * Guaranteed: Only delivered, non-returned purchasers can review.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->filled('order_id') && $request->filled('order_number')) {
            $resolvedOrderId = Order::where('order_number', trim((string)$request->input('order_number')))->value('id');
            if ($resolvedOrderId) {
                $request->merge(['order_id' => $resolvedOrderId]);
            }
        }
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'product_id' => 'required|integer|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'headline' => 'nullable|string|max:150',
            'comment' => 'required|string|min:3|max:2000',
        ]);

        try {
            $review = $this->reviewService->submitReview($request->user(), $validated);

            return response()->json([
                'success' => true,
                'message' => 'Thank you! Your verified review has been posted.',
                'data' => [
                    'review' => $review,
                    'product_rating' => $review->product->rating,
                    'reviews_count' => $review->product->reviews_count,
                ],
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get active verified reviews for a product.
     */
    public function indexForProduct(int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $reviews = Review::where('product_id', $productId)
            ->where('status', 'active')
            ->with(['customer:id,name'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->id,
                'average_rating' => (float)$product->rating,
                'reviews_count' => (int)$product->reviews_count,
                'reviews' => $reviews,
            ],
        ]);
    }

    /**
     * List reviews written by the authenticated customer.
     */
    public function myReviews(Request $request): JsonResponse
    {
        $reviews = Review::where('customer_id', $request->user()->id)
            ->with(['product:id,name,slug,thumbnail'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }
}