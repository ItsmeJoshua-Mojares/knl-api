<?php
// app/Http/Controllers/Api/CartController.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Why a server-side cart, when Phase 3 already has
// Zustand managing cart state in the browser?
//
// The Zustand cart (Phase 3) is great for instant UI feedback —
// no network delay when adding items. But OrderService needs a
// SERVER-SIDE source of truth to validate stock and lock in
// prices at order time. We can't trust the browser's cart blindly
// — a malicious user could tamper with prices in localStorage.
//
// The pattern: Zustand cart drives the UI instantly. When the
// user proceeds to checkout, the frontend SYNCS the Zustand cart
// to this server-side cart (via the `add` endpoint, called once
// per item). Then OrderService reads from the server cart, which
// has authoritative prices pulled fresh from the products table.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Cart, Product};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * GET /api/cart
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request->user()->id);

        return response()->json([
            'success' => true,
            'data'    => ['cart' => $cart->load('items.product')],
        ]);
    }

    /**
     * POST /api/cart/add
     * Body: { "product_id": 1, "quantity": 1 }
     */
    public function add(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'integer|min:1|max:99',
        ]);

        $cart    = $this->getOrCreateCart($request->user()->id);
        $product = Product::active()->findOrFail($request->product_id);

        if ($product->stock_quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Only {$product->stock_quantity} unit(s) available.",
            ], 422);
        }

        // updateOrCreate — if the product is already in the cart,
        // bump quantity; otherwise insert a new row. Avoids
        // duplicate rows for the same product.
        $existing = $cart->items()->where('product_id', $product->id)->first();

        if ($existing) {
            $existing->increment('quantity', $request->quantity);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity'   => $request->quantity,
                // Lock in the CURRENT price — server is the source of truth
                'unit_price' => $product->price,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Added to cart.',
            'data'    => ['cart' => $cart->fresh()->load('items.product')],
        ]);
    }

    /**
     * PUT /api/cart/{item}
     * Body: { "quantity": 3 }
     */
    public function update(Request $request, int $itemId): JsonResponse
    {
        $request->validate(['quantity' => 'required|integer|min:1|max:99']);

        $cart = $this->getOrCreateCart($request->user()->id);
        $item = $cart->items()->findOrFail($itemId);

        if ($item->product->stock_quantity < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Only {$item->product->stock_quantity} unit(s) available.",
            ], 422);
        }

        $item->update(['quantity' => $request->quantity]);

        return response()->json([
            'success' => true,
            'data'    => ['cart' => $cart->fresh()->load('items.product')],
        ]);
    }

    /**
     * DELETE /api/cart/{item}
     */
    public function remove(Request $request, int $itemId): JsonResponse
    {
        $cart = $this->getOrCreateCart($request->user()->id);
        $cart->items()->where('id', $itemId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed.',
            'data'    => ['cart' => $cart->fresh()->load('items.product')],
        ]);
    }

    /**
     * DELETE /api/cart
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->getOrCreateCart($request->user()->id);
        $cart->items()->delete();

        return response()->json(['success' => true, 'message' => 'Cart cleared.']);
    }

    // ── Helper ────────────────────────────────────────────────
    private function getOrCreateCart(int $userId): Cart
    {
        return Cart::firstOrCreate(['user_id' => $userId]);
    }
}
