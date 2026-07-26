<?php
// app/Http/Controllers/Api/ProductController.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: RESTful API design
//
// REST maps HTTP verbs + URLs to actions:
//   GET    /api/products         → index()   list all
//   GET    /api/products/{slug}  → show()    get one
//   POST   /api/products         → store()   create (admin only)
//   PUT    /api/products/{id}    → update()  edit (admin only)
//   DELETE /api/products/{id}    → destroy() delete (admin only)
//
// CONCEPT: Eager loading (N+1 problem prevention)
//
// The N+1 problem:
//   // BAD — runs 1 query for products + 1 per product for images = N+1
//   $products = Product::all();
//   foreach ($products as $p) { echo $p->images->first()->url; }
//
//   // GOOD — runs 2 queries total (products + all their images at once)
//   $products = Product::with('images', 'category', 'brand')->get();
//
// Always use ->with() when you know you'll access a relationship.
//
// CONCEPT: Pagination
// ->paginate(12) returns 12 items per page and includes metadata:
//   { current_page: 1, total: 48, last_page: 4, data: [...] }
// The frontend uses this to render page numbers.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/products
     *
     * List products with filtering, sorting, and pagination.
     *
     * Query params supported:
     *   ?category=watches
     *   ?brand=seiko
     *   ?min_price=10000&max_price=30000
     *   ?search=batman
     *   ?sort=price_asc | price_desc | newest | popular
     *   ?featured=1
     *   ?page=2
     */
    public function index(Request $request): JsonResponse
    {
        // Start with a base query — only active, non-deleted products
        $query = Product::active()
            ->with(['category', 'brand', 'primaryImage']);

        // ── Filters ──────────────────────────────────────────
        // Each filter is applied only if the query param is present.
        // This pattern is called "conditional query building".

        if ($request->filled('category')) {
            $query->inCategory($request->category); // uses the scope we defined
        }

        if ($request->filled('brand')) {
            $query->whereHas('brand', fn ($q) =>
                $q->where('slug', $request->brand)
            );
        }

        if ($request->filled('min_price')) {
            $query->minPrice((float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->maxPrice((float) $request->max_price);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            // Search across name, SKU, and nickname (stored in specifications)
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('sku', 'like', "%{$term}%")
                  ->orWhere('ref_number', 'like', "%{$term}%");
            });
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        if ($request->boolean('in_stock')) {
            $query->inStock();
        }

        // ── Sorting ───────────────────────────────────────────
        match ($request->get('sort', 'newest')) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'popular'    => $query->orderBy('rating_count', 'desc'),
            'rating'     => $query->orderBy('rating_avg', 'desc'),
            default      => $query->orderBy('created_at', 'desc'), // newest
        };

        // ── Paginate ──────────────────────────────────────────
        // perPage() is capped at 48 to prevent huge queries
        $perPage = min((int) $request->get('per_page', 12), 48);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

    /**
     * GET /api/products/{slug}
     *
     * Get a single product with all its details.
     * We load more relationships here (all images, reviews, etc.)
     * because the product detail page needs them.
     */
    public function show(string $slug): JsonResponse
    {
        // firstOrFail() returns 404 automatically if not found
        $product = Product::active()
            ->where('slug', $slug)
            ->with([
                'category',
                'brand',
                'images',              // ALL images (not just primary)
                'reviews' => fn ($q) => $q->approved()
                                          ->with('user:id,first_name,last_name')
                                          ->latest()
                                          ->limit(10),
            ])
            ->firstOrFail();

        // Also get related products (same category, excluding this one)
        $related = Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with('primaryImage', 'brand')
            ->inStock()
            ->limit(4)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'product' => $product,
                'related' => $related,
            ],
        ]);
    }

    /**
     * GET /api/products/featured
     *
     * Products for the homepage featured section.
     */
    public function featured(): JsonResponse
    {
        $products = Product::active()
            ->featured()
            ->with(['brand', 'primaryImage'])
            ->orderBy('sort_order')
            ->limit(8)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => ['products' => $products],
        ]);
    }

    /**
     * GET /api/products/bestsellers
     *
     * Best selling products for the homepage.
     */
    public function bestsellers(): JsonResponse
    {
        $products = Product::active()
            ->bestSeller()
            ->with(['brand', 'primaryImage'])
            ->orderBy('rating_count', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => ['products' => $products],
        ]);
    }
}
