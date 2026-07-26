<?php
// app/Http/Controllers/Api/Admin/DashboardController.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Aggregate queries for reporting
//
// This controller doesn't return individual records — it returns
// COMPUTED NUMBERS: total revenue, order counts, top products.
// These use SQL aggregate functions (SUM, COUNT, AVG) instead of
// pulling thousands of rows into PHP and looping. The database
// is much faster at this than PHP would be.
//
// whereBetween + a date range lets the SAME query power "today",
// "this week", "this month" simply by changing the bounds —
// no separate query logic needed per time period.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Order, Product, User, Payment};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard
     *
     * Everything the admin dashboard's overview page needs in
     * ONE request — summary cards, revenue chart data, recent
     * orders, low-stock alerts, top products.
     */
    public function index(Request $request): JsonResponse
    {
        $period = $request->get('period', '30days'); // today, 7days, 30days, year
        [$from, $to] = $this->resolvePeriod($period);

        return response()->json([
            'success' => true,
            'data' => [
                'summary'        => $this->getSummary($from, $to),
                'revenue_chart'  => $this->getRevenueChart($from, $to),
                'order_status'   => $this->getOrderStatusBreakdown(),
                'recent_orders'  => $this->getRecentOrders(),
                'low_stock'      => $this->getLowStockProducts(),
                'top_products'   => $this->getTopProducts($from, $to),
                'pending_payments' => $this->getPendingPaymentsCount(),
            ],
        ]);
    }

    // ── Summary cards (revenue, orders, customers, AOV) ────────
    private function getSummary(Carbon $from, Carbon $to): array
    {
        $orders = Order::whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled');

        $revenue    = (clone $orders)->sum('grand_total');
        $orderCount = (clone $orders)->count();
        $newCustomers = User::whereHas('role', fn ($q) => $q->where('name', 'customer'))
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return [
            'revenue'            => round((float) $revenue, 2),
            'order_count'        => $orderCount,
            'new_customers'      => $newCustomers,
            'average_order_value'=> $orderCount > 0 ? round($revenue / $orderCount, 2) : 0,
        ];
    }

    // ── Revenue over time (for the line chart) ─────────────────
    private function getRevenueChart(Carbon $from, Carbon $to): array
    {
        // DATE() truncates the timestamp to just the day, so we
        // can GROUP BY day and get one row per date with its total.
        $rows = Order::selectRaw('DATE(created_at) as date, SUM(grand_total) as total, COUNT(*) as orders')
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($row) => [
            'date'   => $row->date,
            'total'  => round((float) $row->total, 2),
            'orders' => (int) $row->orders,
        ])->toArray();
    }

    // ── Order status breakdown (for the pie/donut chart) ───────
    private function getOrderStatusBreakdown(): array
    {
        return Order::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    // ── Recent orders (last 10, for the activity feed) ─────────
    private function getRecentOrders()
    {
        return Order::with('user:id,first_name,last_name')
            ->latest()
            ->limit(8)
            ->get(['id', 'order_number', 'user_id', 'status', 'grand_total', 'created_at']);
    }

    // ── Low stock alerts ────────────────────────────────────────
    private function getLowStockProducts()
    {
        return Product::active()
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->where('stock_quantity', '>', 0)
            ->orderBy('stock_quantity')
            ->limit(10)
            ->get(['id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold']);
    }

    // ── Top-selling products in the period ──────────────────────
    private function getTopProducts(Carbon $from, Carbon $to)
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled')
            ->select(
                'order_items.product_id',
                'order_items.product_name',
                'order_items.product_sku',
                DB::raw('SUM(order_items.quantity) as units_sold'),
                DB::raw('SUM(order_items.total_price) as revenue')
            )
            ->groupBy('order_items.product_id', 'order_items.product_name', 'order_items.product_sku')
            ->orderByDesc('units_sold')
            ->limit(5)
            ->get();
    }

    // ── Pending payment verifications count (for the badge) ────
    private function getPendingPaymentsCount(): int
    {
        return Payment::where('status', 'pending')
            ->whereIn('payment_method', ['gcash', 'maya', 'bank_transfer'])
            ->count();
    }

    // ── Helper: turn a period string into a date range ──────────
    private function resolvePeriod(string $period): array
    {
        $to = now();

        $from = match ($period) {
            'today'   => now()->startOfDay(),
            '7days'   => now()->subDays(7)->startOfDay(),
            '30days'  => now()->subDays(30)->startOfDay(),
            'year'    => now()->startOfYear(),
            default   => now()->subDays(30)->startOfDay(),
        };

        return [$from, $to];
    }
}
