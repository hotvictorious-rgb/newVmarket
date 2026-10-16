<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReviewService
{
    /**
     * Submit or update a verified product review.
     * Enforces mathematical verified-buyer invariant:
     * - Order must belong to the customer.
     * - Order must be delivered.
     * - Product must be in order items.
     * - Item must NOT have been rejected or returned.
     */
    public function submitReview(User $customer, array $data): Review
    {
        $orderId = (int)$data['order_id'];
        $productId = (int)$data['product_id'];
        $rating = (int)$data['rating'];

        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Star rating must be an integer between 1 and 5.');
        }

        return DB::transaction(function () use ($customer, $orderId, $productId, $rating, $data) {
            $order = Order::with('items')->where('id', $orderId)->lockForUpdate()->first();

            if (!$order) {
                throw new InvalidArgumentException('Order not found.');
            }

            if ((int)$order->customer_id !== (int)$customer->id) {
                throw new InvalidArgumentException('Unauthorized: You can only review products from your own purchases.');
            }

            // Must be delivered
            $isDelivered = $order->order_status === 'delivered' || (bool)$order->delivery_confirmed_by_customer;
            if (!$isDelivered) {
                throw new InvalidArgumentException('Only successfully delivered orders are eligible for product reviews.');
            }

            // Verify product was purchased in this order
            $orderItem = ($order->items ?? $order->orderItems)->firstWhere('product_id', $productId);
            if (!$orderItem) {
                throw new InvalidArgumentException('This product was not purchased in the specified order.');
            }

            // Verify item was not doorstep rejected
            if ($orderItem->item_delivery_status === 'rejected' || ($orderItem->rejected_quantity ?? 0) >= $orderItem->quantity) {
                throw new InvalidArgumentException('Reviews cannot be submitted for doorstep-rejected or returned items.');
            }

            // Verify no active/approved return case exists for this item
            $hasReturn = \App\Models\ReturnItem::where('product_id', $productId)
                ->whereHas('returnCase', function ($q) use ($orderId) {
                    $q->where('order_id', $orderId)
                        ->whereNotIn('status', ['rejected', 'closed']);
                })
                ->exists();

            if ($hasReturn) {
                throw new InvalidArgumentException('Reviews cannot be submitted for items currently in a return or refund process.');
            }

            $product = Product::findOrFail($productId);

            $headline = !empty($data['headline']) ? strip_tags(trim($data['headline'])) : null;
            $comment = strip_tags(trim($data['comment'] ?? ''));

            if (empty($comment)) {
                throw new InvalidArgumentException('Review comment cannot be empty.');
            }

            $review = Review::updateOrCreate(
                [
                    'order_id' => $orderId,
                    'product_id' => $productId,
                ],
                [
                    'customer_id' => $customer->id,
                    'rating' => $rating,
                    'headline' => $headline,
                    'comment' => $comment,
                    'is_verified_buyer' => true,
                    'status' => 'active',
                    'revoked_reason' => null,
                ]
            );

            // Recalculate product rating and total reviews
            $product->recalculateRating();

            return $review;
        });
    }

    /**
     * Invalidate and revoke reviews when an order or item is returned.
     * Mathematical Guarantee: If returned, review is removed and rating is recalculated.
     */
    public function revokeReviewsForOrder(int $orderId, ?int $productId = null, string $reason = 'Order or item was returned'): int
    {
        return DB::transaction(function () use ($orderId, $productId, $reason) {
            $query = Review::where('order_id', $orderId)->where('status', 'active');

            if ($productId !== null) {
                $query->where('product_id', $productId);
            }

            $reviews = $query->lockForUpdate()->get();

            if ($reviews->isEmpty()) {
                return 0;
            }

            $productIds = $reviews->pluck('product_id')->unique();

            foreach ($reviews as $review) {
                $review->update([
                    'status' => 'revoked_returned',
                    'revoked_reason' => $reason,
                ]);
            }

            // Recalculate all affected products
            foreach ($productIds as $pId) {
                $product = Product::find($pId);
                if ($product) {
                    $product->recalculateRating();
                }
            }

            return $reviews->count();
        });
    }
}