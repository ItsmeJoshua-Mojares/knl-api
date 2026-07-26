<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /** GET /api/wishlist */
    public function index(Request $request): JsonResponse
    {
        // Wishlist is stored client-side in Zustand (localStorage).
        // This endpoint returns product details for a list of IDs
        // sent as ?ids[]=1&ids[]=2 so the frontend can hydrate.
        $ids      = $request->input('ids', []);
        $products = Product::active()
            ->with(['brand', 'primaryImage'])
            ->whereIn('id', $ids)
            ->get();

        return response()->json(['success' => true, 'data' => $products]);
    }

    /** POST /api/wishlist/{product} — toggle saved state */
    public function toggle(Request $request, Product $product): JsonResponse
    {
        // Wishlist is managed client-side in Phase 3.
        // This endpoint exists for future server-side wishlist sync.
        return response()->json([
            'success' => true,
            'data'    => ['product_id' => $product->id],
        ]);
    }
}
