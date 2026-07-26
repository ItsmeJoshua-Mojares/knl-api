<?php
// app/Http/Controllers/Api/Admin/ReportController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ExportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ExportService $exportService) {}

    /**
     * GET /api/admin/reports/orders/csv?from=2025-01-01&to=2025-01-31
     */
    public function ordersCsv(Request $request)
    {
        return $this->exportService->exportOrdersCsv(
            $request->get('from'),
            $request->get('to')
        );
    }

    /**
     * GET /api/admin/reports/products/csv
     */
    public function productsCsv()
    {
        return $this->exportService->exportProductsCsv();
    }

    /**
     * GET /api/admin/reports/sales/excel?from=2025-01-01&to=2025-01-31
     */
    public function salesExcel(Request $request)
    {
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return $this->exportService->exportSalesExcel($request->from, $request->to);
    }

    /**
     * GET /api/admin/reports/sales/pdf?from=2025-01-01&to=2025-01-31
     */
    public function salesPdf(Request $request)
    {
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return $this->exportService->generateSalesReportPdf($request->from, $request->to);
    }

    /**
     * GET /api/admin/orders/{id}/invoice-pdf
     */
    public function orderInvoicePdf(int $id)
    {
        $order = Order::findOrFail($id);

        return $this->exportService->generateOrderInvoicePdf($order);
    }
}
