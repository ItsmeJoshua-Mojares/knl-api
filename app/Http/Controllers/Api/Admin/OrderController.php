<?php
// app/Http/Controllers/Api/Admin/OrderController.php
// ─────────────────────────────────────────────────────────────
// This controller is thin by design — all the actual status
// transition logic (which statuses can follow which, firing
// OrderStatusChanged, stock restoration on cancel) already lives
// in OrderService from Phase 4. We just call it here with admin
// context instead of duplicating any business rules.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\{JsonResponse, Request};

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    /**
     * GET /api/admin/orders
     *
     * Full order list for the admin dashboard, with filters.
     * Unlike the customer-facing OrderController (Phase 4), this
     * sees orders from ALL users, not just the authenticated one.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user:id,first_name,last_name,email', 'payment'])
            ->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_method')) {
            $query->whereHas('payment', fn ($q) =>
                $q->where('payment_method', $request->payment_method)
            );
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(fn ($q) =>
                $q->where('order_number', 'like', "%{$term}%")
                  ->orWhere('ship_first_name', 'like', "%{$term}%")
                  ->orWhere('ship_last_name', 'like', "%{$term}%")
                  ->orWhere('ship_phone', 'like', "%{$term}%")
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $orders]);
    }

    /**
     * GET /api/admin/orders/{id}
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::with([
            'user', 'items.product:id,name,sku',
            'payments', 'statusHistory.changedBy:id,first_name,last_name',
            'coupon',
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => ['order' => $order]]);
    }

    /**
     * PUT /api/admin/orders/{id}/status
     * Body: { "status": "shipped", "tracking_number": "ABC123", "note": "..." }
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status'           => 'required|in:confirmed,processing,shipped,delivered,cancelled,returned,refunded',
            'tracking_number'  => 'nullable|string|max:100',
            'note'             => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($id);

        // Set tracking number before updating status, so the
        // shipped-status email (Phase 4 listener) can include it
        if ($request->filled('tracking_number')) {
            $order->update(['tracking_number' => $request->tracking_number]);
        }

        try {
            $updated = $this->orderService->updateStatus($order, $request->status, $request->note);

            return response()->json([
                'success' => true,
                'message' => "Order status updated to '{$request->status}'.",
                'data'    => ['order' => $updated],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
