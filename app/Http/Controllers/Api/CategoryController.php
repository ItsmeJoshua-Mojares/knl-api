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
            // Load the first active product + its primary image so the
            // frontend can show a real photo for each category (e.g.
            // Watches → a watch shot). Categories with no products get
            // image_url: null.
            ->with(['products' => function ($q) {
                $q->active()
                    ->with('primaryImage')
                    ->orderBy('sort_order')
                    ->limit(1);
            }])
            ->orderBy('sort_order')
            ->get();

        $data = $categories->map(function (Category $category) {
            $firstProduct = $category->products->first();

            return [
                'id'             => $category->id,
                'name'           => $category->name,
                'slug'           => $category->slug,
                'description'    => $category->description,
                'image_url'      => $firstProduct?->primaryImage?->image_url,
                'products_count' => $category->products_count,
                'sort_order'     => $category->sort_order,
                'is_active'      => $category->is_active,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
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
