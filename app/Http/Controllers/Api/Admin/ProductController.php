<?php
// app/Http/Controllers/Api/Admin/ProductController.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Admin CRUD controllers
//
// This is the OPPOSITE of the public ProductController from
// Phase 2 — that one only READS active products for customers.
// This one can read EVERYTHING (including inactive/out-of-stock
// products admins need to manage) and WRITE.
//
// Route::apiResource (set up in routes/api.php) maps these
// 5 methods automatically:
//   GET    /admin/products       → index
//   POST   /admin/products       → store
//   GET    /admin/products/{id}  → show
//   PUT    /admin/products/{id}  → update
//   DELETE /admin/products/{id}  → destroy
//
// CONCEPT: Route Model Binding
//
// Notice update(Request $request, Product $product) — Laravel
// automatically fetches the Product by the {product} URL segment
// and injects the full model. No manual Product::findOrFail()
// needed. If the ID doesn't exist, Laravel returns 404 automatically
// before your method even runs.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Events\NewProductAdded;
use App\Models\Product;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * GET /api/admin/products
     *
     * Unlike the public endpoint, this returns ALL products
     * (active, inactive, out of stock) so admins can manage everything.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand', 'primaryImage'])
            ->withCount('reviews');

        // Support viewing trashed products
        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }

        // Filters
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(fn ($q) =>
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('sku', 'like', "%{$term}%")
            );
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('status')) {
            match ($request->status) {
                'active'      => $query->where('is_active', true),
                'inactive'    => $query->where('is_active', false),
                'low_stock'   => $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->where('stock_quantity', '>', 0),
                'out_of_stock'=> $query->where('stock_quantity', 0),
                default       => null,
            };
        }

        $sortBy  = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $products = $query->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $products]);
    }

    /**
     * GET /api/admin/products/{product}
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'brand', 'images', 'inventoryLogs' => fn ($q) => $q->limit(20)]);

        return response()->json(['success' => true, 'data' => ['product' => $product]]);
    }

    /**
     * POST /api/admin/products
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge(['brand_id' => $request->input('brand_id') ?: null]);
        $validated = $request->validate($this->validationRules());

        $product = Product::create([
            ...$validated,
            'slug' => $this->generateUniqueSlug($validated['name']),
        ]);

        ActivityLog::record($product, 'created', ['name' => $product->name]);

        // Notify newsletter subscribers only for products that are
        // actually live (drafts/inactive products don't get broadcast).
        if ($product->is_active) {
            event(new NewProductAdded($product));
        }

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data'    => ['product' => $product],
        ], 201);
    }

    /**
     * PUT /api/admin/products/{product}
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $request->merge(['brand_id' => $request->input('brand_id') ?: null]);
        $rules = $this->validationRules($product->id);
        $validated = $request->validate($rules);

        // Capture before/after for the activity log diff
        $before = $product->only(array_keys($validated));

        $product->update($validated);

        $changed = array_diff_assoc(
            array_map(fn($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v, $validated),
            array_map(fn($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v, $before)
        );

        if (!empty($changed)) {
            ActivityLog::record($product, 'updated', [
                'before' => $before,
                'after'  => $validated,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data'    => ['product' => $product->fresh()],
        ]);
    }

    /**
     * DELETE /api/admin/products/{product}
     *
     * Soft delete — the model uses SoftDeletes, so this sets
     * deleted_at instead of removing the row. Order history
     * referencing this product (via withTrashed()) stays intact.
     */
    public function destroy(Product $product): JsonResponse
    {
        $name = $product->name;
        $product->delete();

        ActivityLog::record($product, 'deleted', ['name' => $name]);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    /**
     * POST /api/admin/products/bulk-delete
     * Body: { "ids": [1, 2, 3] }
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|exists:products,id']);

        $count = Product::whereIn('id', $request->ids)->delete();

        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'subject_type' => Product::class,
            'subject_id'   => 0,
            'event'        => 'bulk_deleted',
            'properties'   => ['ids' => $request->ids, 'count' => $count],
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$count} product(s) deleted.",
        ]);
    }

    /**
     * PUT /api/admin/products/bulk-update-status
     * Body: { "ids": [1,2,3], "is_active": false }
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids'       => 'required|array|min:1',
            'ids.*'     => 'integer|exists:products,id',
            'is_active' => 'required|boolean',
        ]);

        $count = Product::whereIn('id', $request->ids)
            ->update(['is_active' => $request->is_active]);

        return response()->json([
            'success' => true,
            'message' => "{$count} product(s) updated.",
        ]);
    }

    /**
     * POST /api/admin/products/{product}/adjust-stock
     * Body: { "quantity_change": 10, "note": "Restocked from supplier" }
     *
     * Manual stock adjustment — logged exactly like order-driven
     * changes, so the inventory_logs table stays the single source
     * of truth for every stock movement, regardless of cause.
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'quantity_change' => 'required|integer',
            'note'            => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($request, $product) {
            $before = $product->stock_quantity;
            $product->increment('stock_quantity', $request->quantity_change);

            $product->inventoryLogs()->create([
                'user_id'         => $request->user()->id,
                'type'            => 'adjustment',
                'quantity_before' => $before,
                'quantity_change' => $request->quantity_change,
                'quantity_after'  => $product->fresh()->stock_quantity,
                'note'            => $request->note,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully.',
                'data'    => ['product' => $product->fresh()],
            ]);
        });
    }

    /**
     * PUT /api/admin/products/{product}/restore
     *
     * Restore a soft-deleted product.
     */
    public function restore(int $id): JsonResponse
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $product->restore();

        ActivityLog::record($product, 'restored', ['name' => $product->name]);

        return response()->json([
            'success' => true,
            'message' => 'Product restored successfully.',
            'data'    => ['product' => $product],
        ]);
    }

    /**
     * DELETE /api/admin/products/{product}/force-delete
     *
     * Permanently delete a product (cannot be undone).
     */
    public function forceDelete(int $id): JsonResponse
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $name = $product->name;
        $product->forceDelete();

        ActivityLog::record($product, 'force_deleted', ['name' => $name]);

        return response()->json([
            'success' => true,
            'message' => 'Product permanently deleted.',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────

    private function validationRules(?int $productId = null): array
    {
        return [
            'category_id'        => 'required|exists:categories,id',
            'brand_id'           => 'nullable|exists:brands,id',
            'name'               => 'required|string|max:255',
            'sku'                => ['required', 'string', 'max:80', Rule::unique('products', 'sku')->withoutTrashed()->ignore($productId)],
            'ref_number'         => 'nullable|string|max:80',
            'caliber_number'     => 'nullable|string|max:40',
            'short_desc'         => 'nullable|string|max:500',
            'description'        => 'nullable|string',
            'specifications'     => 'nullable|array',
            'price'              => 'required|numeric|min:0',
            'compare_at_price'   => 'nullable|numeric|min:0',
            'cost_price'         => 'nullable|numeric|min:0',
            'stock_quantity'     => 'required|integer|min:0',
            'low_stock_threshold'=> 'nullable|integer|min:0',
            'condition_status'   => 'nullable|string|max:30',
            'is_active'          => 'boolean',
            'is_featured'        => 'boolean',
            'is_bestseller'      => 'boolean',
            'meta_title'         => 'nullable|string|max:160',
            'meta_desc'          => 'nullable|string|max:320',
        ];
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = \Illuminate\Support\Str::slug($name);
        $slug = $base;
        $i    = 1;

        while (\DB::table('products')->where('slug', $slug)->whereNull('deleted_at')->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
