<?php
// app/Http/Controllers/Api/CouponController.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Validation endpoint pattern
//
// This endpoint doesn't CREATE or MODIFY anything — it just
// checks "is this coupon code valid for this cart total?" and
// returns the discount amount. The frontend calls this when the
// user clicks "Apply" on the cart page, BEFORE the order is
// actually placed.
//
// We don't increment used_count here — that only happens in
// OrderService::createOrder() once the order is truly placed.
// Otherwise a user could "apply" a single-use coupon 10 times
// by refreshing the cart page without ever checking out.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * POST /api/cart/coupon
     *
     * Validate a coupon code against the current cart subtotal.
     * Body: { "code": "WELCOME10", "subtotal": 22999 }
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code'     => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))
            ->valid() // scope: active + within date range + under usage limit
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'This coupon is invalid or has expired.',
            ], 404);
        }

        if ($request->subtotal < (float) $coupon->min_order_amount) {
            return response()->json([
                'success' => false,
                'message' => "This coupon requires a minimum order of ₱" .
                              number_format((float) $coupon->min_order_amount, 2) . ".",
            ], 422);
        }

        $discount = $coupon->calculateDiscount((float) $request->subtotal);

        return response()->json([
            'success' => true,
            'message' => 'Coupon applied successfully!',
            'data'    => [
                'code'     => $coupon->code,
                'type'     => $coupon->type,
                'discount' => round($discount, 2),
            ],
        ]);
    }
}
