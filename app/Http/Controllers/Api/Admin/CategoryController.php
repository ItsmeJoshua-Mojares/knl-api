<?php
// app/Http/Controllers/Api/Admin/CategoryController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Category, ActivityLog};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::withCount('products')
            ->orderBy('sort_order')
            ->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['category' => $category]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'parent_id'   => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'image_url'   => 'nullable|string|max:512',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
            'meta_title'  => 'nullable|string|max:160',
            'meta_desc'   => 'nullable|string|max:320',
        ]);

        $category = Category::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name']),
        ]);

        ActivityLog::record($category, 'created', ['name' => $category->name]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data'    => ['category' => $category],
        ], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:100',
            'parent_id'   => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'image_url'   => 'nullable|string|max:512',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $category->update($validated);
        ActivityLog::record($category, 'updated', $validated);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data'    => ['category' => $category->fresh()],
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->withTrashed()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a category that still has products. Reassign products first.',
            ], 422);
        }

        $name = $category->name;
        $category->delete();
        ActivityLog::record($category, 'deleted', ['name' => $name]);

        return response()->json(['success' => true, 'message' => 'Category deleted successfully.']);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }
}
