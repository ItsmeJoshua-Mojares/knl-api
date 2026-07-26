<?php
// app/Http/Controllers/Api/Admin/BrandController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Brand, ActivityLog};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $brands = Brand::withCount('products')
            ->orderBy('name')
            ->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $brands]);
    }

    public function show(Brand $brand): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['brand' => $brand]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:brands,name',
            'logo_url'    => 'nullable|string|max:512',
            'website'     => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $brand = Brand::create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
        ]);

        ActivityLog::record($brand, 'created', ['name' => $brand->name]);

        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully.',
            'data'    => ['brand' => $brand],
        ], 201);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:100',
            'logo_url'    => 'nullable|string|max:512',
            'website'     => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $brand->update($validated);
        ActivityLog::record($brand, 'updated', $validated);

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully.',
            'data'    => ['brand' => $brand->fresh()],
        ]);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        if ($brand->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a brand that still has products.',
            ], 422);
        }

        $name = $brand->name;
        $brand->delete();
        ActivityLog::record($brand, 'deleted', ['name' => $name]);

        return response()->json(['success' => true, 'message' => 'Brand deleted successfully.']);
    }
}
