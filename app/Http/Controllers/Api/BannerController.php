<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\{JsonResponse, Request};

class BannerController extends Controller
{
    /**
     * GET /api/banners
     *
     * Public endpoint — returns active banners for the homepage.
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $query = Banner::active();

        if ($request->filled('position')) {
            $query->position($request->position);
        }

        $banners = $query->orderBy('sort_order')->get();

        return response()->json(['success' => true, 'data' => $banners]);
    }
}
