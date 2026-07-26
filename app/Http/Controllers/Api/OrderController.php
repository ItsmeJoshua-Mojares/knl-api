<?php
// app/Http/Controllers/Api/OrderController.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Thin controller delegating to OrderService
//
// Notice how SHORT each method is. The controller's job is only:
//   1. Validate input (via Form Request)
//   2. Call the service
//   3. Catch expected exceptions and turn them into HTTP responses
//   4. Return JSON
//
// All the complex logic (transactions, stock checks, pricing
// math) lives in OrderService where it can be tested and reused
// independently of HTTP.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    /**
     * GET /api/orders
     *
     * List the authenticated user's order history.
     * Supports ?status=pending to filter.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::forUser($request->user()->id)
            ->with(['items', 'payment'])
            ->when($request->filled('status'), fn ($q) =>
                $q->byStatus($request->status)
            )
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    /**
     * GET /api/orders/{number}
     *
     * Get a single order by order_number.
     * Only the order owner can view it.
     */
    public function show(Request $request, string $number): JsonResponse
    {
        $order = Order::where('order_number', $number)
            ->with(['items', 'payment', 'statusHistory.changedBy:id,first_name,last_name'])
            ->firstOrFail();

        // Authorization check — users can only see their own orders
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404); // 404, not 403 — don't reveal the order exists
        }

        return response()->json([
            'success' => true,
            'data'    => ['order' => $order],
        ]);
    }

    /**
     * POST /api/orders
     *
     * Place a new order from the user's cart.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createOrder(
                $request->user()->id,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully.',
                'data'    => [
                    'order'        => $order->load('items', 'payment'),
                    'order_number' => $order->order_number,
                ],
            ], 201);

        } catch (ValidationException $e) {
            // Stock or product availability errors
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/orders/{number}/cancel
     *
     * Customer-initiated order cancellation.
     */
    public function cancel(Request $request, string $number): JsonResponse
    {
        $order = Order::where('order_number', $number)->firstOrFail();

        try {
            $cancelled = $this->orderService->cancelOrder($order, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully.',
                'data'    => ['order' => $cancelled],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
