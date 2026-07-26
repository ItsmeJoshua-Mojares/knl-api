<?php
// app/Services/ExportService.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Streamed CSV vs loaded-into-memory exports
//
// For CSV, we use Laravel's StreamedResponse — it writes rows
// directly to the HTTP response as they're generated, rather
// than building the entire file in PHP memory first. This matters
// because exporting 50,000 orders as a regular array would try
// to hold the whole file in RAM before sending a single byte.
// Streaming sends data continuously, so memory stays flat
// regardless of how many rows you're exporting.
//
// For Excel (.xlsx) we use maatwebsite/excel, the standard
// Laravel package — XLSX is a ZIP-based binary format, so it
// can't be hand-written like CSV; you need a real library.
//
// For PDF we use barryvdh/laravel-dompdf, which renders a Blade
// HTML template to PDF — so PDF reports actually reuse the same
// view templating you already know from the email system in Phase 4.
//
// Required composer packages (add to composer.json):
//   composer require maatwebsite/excel
//   composer require barryvdh/laravel-dompdf
// ─────────────────────────────────────────────────────────────

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportService
{
    /**
     * Stream a CSV export of orders within a date range.
     * Memory-safe even for tens of thousands of rows.
     */
    public function exportOrdersCsv(?string $from = null, ?string $to = null): StreamedResponse
    {
        $query = Order::with('items')->orderBy('created_at');

        if ($from) $query->whereDate('created_at', '>=', $from);
        if ($to)   $query->whereDate('created_at', '<=', $to);

        $filename = 'orders-export-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        // The callback runs lazily — rows are written to the output
        // stream chunk by chunk as the database cursor yields them.
        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'Order Number', 'Date', 'Customer', 'Status',
                'Payment Method', 'Items', 'Subtotal', 'Discount',
                'Shipping', 'Tax', 'Total',
            ]);

            // cursor() streams rows one at a time from the DB instead
            // of loading the entire result set into memory with get()
            foreach ($query->cursor() as $order) {
                fputcsv($handle, [
                    $order->order_number,
                    $order->created_at->format('Y-m-d H:i'),
                    "{$order->ship_first_name} {$order->ship_last_name}",
                    $order->status,
                    $order->payment?->payment_method ?? '—',
                    $order->items->sum('quantity'),
                    number_format((float) $order->subtotal, 2),
                    number_format((float) $order->discount_amount, 2),
                    number_format((float) $order->shipping_fee, 2),
                    number_format((float) $order->tax_amount, 2),
                    number_format((float) $order->grand_total, 2),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Stream a CSV export of the product catalog — useful for
     * bulk price/stock review outside the admin UI.
     */
    public function exportProductsCsv(): StreamedResponse
    {
        $filename = 'products-export-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'SKU', 'Name', 'Category', 'Brand', 'Price',
                'Stock', 'Status', 'Featured', 'Created',
            ]);

            foreach (Product::with('category', 'brand')->cursor() as $product) {
                fputcsv($handle, [
                    $product->sku,
                    $product->name,
                    $product->category?->name ?? '—',
                    $product->brand?->name ?? '—',
                    $product->price,
                    $product->stock_quantity,
                    $product->is_active ? 'Active' : 'Inactive',
                    $product->is_featured ? 'Yes' : 'No',
                    $product->created_at->format('Y-m-d'),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Generate an Excel (.xlsx) sales report.
     * Excel is preferred over CSV when admins need formatting
     * (currency formatting, totals row, conditional formatting)
     * that plain CSV can't represent.
     */
    public function exportSalesExcel(string $from, string $to)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\SalesReportExport($from, $to),
            'sales-report-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Generate a PDF invoice for a single order.
     * Reuses a Blade view, just like the email templates in Phase 4.
     */
    public function generateOrderInvoicePdf(Order $order)
    {
        $order->load('items', 'user', 'payment');

        $pdf = Pdf::loadView('pdf.order-invoice', ['order' => $order]);

        return $pdf->download("invoice-{$order->order_number}.pdf");
    }

    /**
     * Generate a PDF sales summary report for a date range.
     */
    public function generateSalesReportPdf(string $from, string $to)
    {
        $orders = Order::whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->get();

        $summary = [
            'from'         => $from,
            'to'           => $to,
            'total_orders' => $orders->count(),
            'total_revenue'=> $orders->sum('grand_total'),
            'avg_order'    => $orders->count() > 0 ? $orders->avg('grand_total') : 0,
        ];

        $pdf = Pdf::loadView('pdf.sales-report', [
            'summary' => $summary,
            'orders'  => $orders,
        ]);

        return $pdf->download('sales-report-' . now()->format('Y-m-d') . '.pdf');
    }
}
