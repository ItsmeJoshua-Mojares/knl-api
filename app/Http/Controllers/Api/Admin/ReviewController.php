<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Review, ActivityLog};
use Illuminate\Http\{JsonResponse, Request};

class ReviewController extends Controller
{
    /**
     * GET /api/admin/reviews
     */
    public function index(Request $request): JsonResponse
    {
        $query = Review::with(['product', 'user']);

        if ($request->filled('status')) {
            $request->status === 'pending'
                ? $query->pending()
                : $query->approved();
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(fn ($q) =>
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('body', 'like', "%{$term}%")
            );
        }

        $reviews = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $reviews]);
    }

    /**
     * PUT /api/admin/reviews/{review}/approve
     */
    public function approve(Review $review): JsonResponse
    {
        $review->update(['is_approved' => true]);

        ActivityLog::record($review, 'approved', ['product_id' => $review->product_id]);

        // Update product rating
        $this->updateProductRating($review->product_id);

        return response()->json([
            'success' => true,
            'message' => 'Review approved.',
            'data'    => ['review' => $review->fresh()->load(['product', 'user'])],
        ]);
    }

    /**
     * PUT /api/admin/reviews/{review}/reject
     */
    public function reject(Review $review): JsonResponse
    {
        $review->update(['is_approved' => false]);

        ActivityLog::record($review, 'rejected', ['product_id' => $review->product_id]);

        $this->updateProductRating($review->product_id);

        return response()->json([
            'success' => true,
            'message' => 'Review rejected.',
            'data'    => ['review' => $review->fresh()->load(['product', 'user'])],
        ]);
    }

    /**
     * DELETE /api/admin/reviews/{review}
     */
    public function destroy(Review $review): JsonResponse
    {
        $productId = $review->product_id;
        $review->delete();

        ActivityLog::record($review, 'deleted', ['product_id' => $productId]);

        $this->updateProductRating($productId);

        return response()->json([
            'success' => true,
            'message' => 'Review deleted.',
        ]);
    }

    /**
     * PUT /api/admin/reviews/{review}/reply
     * Body: { "admin_reply": "Thank you for your feedback!" }
     */
    public function reply(Request $request, Review $review): JsonResponse
    {
        $request->validate([
            'admin_reply' => 'required|string|max:2000',
        ]);

        $review->update(['admin_reply' => $request->admin_reply]);

        ActivityLog::record($review, 'replied', ['product_id' => $review->product_id]);

        return response()->json([
            'success' => true,
            'message' => 'Reply saved.',
            'data'    => ['review' => $review->fresh()->load(['product', 'user'])],
        ]);
    }

    private function updateProductRating(int $productId): void
    {
        $stats = Review::where('product_id', $productId)
            ->approved()
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();

        \App\Models\Product::where('id', $productId)->update([
            'rating_avg'   => round($stats->avg_rating ?? 0, 2),
            'rating_count' => $stats->cnt ?? 0,
        ]);
    }
}
