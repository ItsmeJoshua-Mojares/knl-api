<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /** GET /api/categories */
    public function index(): JsonResponse
    {
        $categories = Category::active()
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $categories]);
    }

    /** GET /api/categories/{slug} */
    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->active()->firstOrFail();

        return response()->json(['success' => true, 'data' => ['category' => $category]]);
    }

    /** GET /api/categories/{slug}/products */
    public function products(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->active()->firstOrFail();

        $products = $category->products()
            ->active()
            ->with(['brand', 'primaryImage'])
            ->paginate(12);

        return response()->json(['success' => true, 'data' => $products]);
    }
}
