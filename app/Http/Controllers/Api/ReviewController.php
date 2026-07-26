<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /** GET /api/products/{product}/reviews */
    public function index(Product $product): JsonResponse
    {
        $reviews = $product->reviews()
            ->approved()
            ->with('user:id,first_name,last_name')
            ->latest()
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $reviews]);
    }

    /** POST /api/products/{product}/reviews */
    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title'  => 'nullable|string|max:100',
            'body'   => 'required|string|min:10|max:2000',
        ]);

        // Check the user actually bought this product
        $purchased = $request->user()
            ->orders()
            ->where('status', 'delivered')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        $review = Review::create([
            'product_id'  => $product->id,
            'user_id'     => $request->user()->id,
            'rating'      => $request->rating,
            'title'       => $request->title,
            'body'        => $request->body,
            'is_verified' => $purchased,
            'is_approved' => false, // admin must approve
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted and awaiting approval.',
            'data'    => ['review' => $review],
        ], 201);
    }
}
