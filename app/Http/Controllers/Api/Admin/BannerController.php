<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Banner, ActivityLog};
use Illuminate\Http\{JsonResponse, Request};

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Banner::query();

        if ($request->filled('position')) {
            $query->where('position', $request->position);
        }

        if ($request->filled('status')) {
            $request->status === 'active'
                ? $query->where('is_active', true)
                : $query->where('is_active', false);
        }

        $banners = $query->orderBy('sort_order')->latest()->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $banners]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'subtitle'    => 'nullable|string|max:255',
            'image_url'   => 'required|url|max:512',
            'link_url'    => 'nullable|url|max:512',
            'position'    => 'required|in:hero,sidebar,promo',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date|after_or_equal:starts_at',
        ]);

        $banner = Banner::create($validated);
        ActivityLog::record($banner, 'created', ['title' => $banner->title]);

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully.',
            'data'    => ['banner' => $banner],
        ], 201);
    }

    public function show(Banner $banner): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['banner' => $banner]]);
    }

    public function update(Request $request, Banner $banner): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'subtitle'    => 'nullable|string|max:255',
            'image_url'   => 'sometimes|required|url|max:512',
            'link_url'    => 'nullable|url|max:512',
            'position'    => 'sometimes|required|in:hero,sidebar,promo',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date',
        ]);

        $banner->update($validated);
        ActivityLog::record($banner, 'updated', ['title' => $banner->title]);

        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully.',
            'data'    => ['banner' => $banner->fresh()],
        ]);
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $title = $banner->title;
        $banner->delete();
        ActivityLog::record($banner, 'deleted', ['title' => $title]);

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully.',
        ]);
    }
}
