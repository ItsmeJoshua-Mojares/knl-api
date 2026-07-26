<?php
// app/Http/Controllers/Api/Admin/CouponController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Coupon, ActivityLog};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query();

        if ($request->filled('status')) {
            match ($request->status) {
                'active'  => $query->where('is_active', true),
                'expired' => $query->where('expires_at', '<', now()),
                default   => null,
            };
        }

        $coupons = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $coupons]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'                 => 'required|string|max:50|unique:coupons,code',
            'description'          => 'nullable|string|max:255',
            'type'                 => 'required|in:percentage,fixed,free_shipping',
            'value'                => 'required|numeric|min:0',
            'min_order_amount'     => 'nullable|numeric|min:0',
            'max_discount_amount'  => 'nullable|numeric|min:0',
            'usage_limit'          => 'nullable|integer|min:1',
            'is_active'            => 'boolean',
            'starts_at'            => 'nullable|date',
            'expires_at'           => 'nullable|date|after:starts_at',
        ]);

        $validated['code'] = strtoupper($validated['code']);

        $coupon = Coupon::create($validated);
        ActivityLog::record($coupon, 'created', ['code' => $coupon->code]);

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully.',
            'data'    => ['coupon' => $coupon],
        ], 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $validated = $request->validate([
            'description'          => 'nullable|string|max:255',
            'value'                => 'sometimes|required|numeric|min:0',
            'min_order_amount'     => 'nullable|numeric|min:0',
            'max_discount_amount'  => 'nullable|numeric|min:0',
            'usage_limit'          => 'nullable|integer|min:1',
            'is_active'            => 'boolean',
            'starts_at'            => 'nullable|date',
            'expires_at'           => 'nullable|date',
        ]);

        $coupon->update($validated);
        ActivityLog::record($coupon, 'updated', $validated);

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully.',
            'data'    => ['coupon' => $coupon->fresh()],
        ]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $code = $coupon->code;
        $coupon->delete();
        ActivityLog::record($coupon, 'deleted', ['code' => $code]);

        return response()->json(['success' => true, 'message' => 'Coupon deleted successfully.']);
    }
}
