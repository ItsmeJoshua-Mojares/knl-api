<?php
// app/Exports/SalesReportExport.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: maatwebsite/excel's Export class pattern
//
// Instead of manually building an XLSX binary (a ZIP file full
// of XML — extremely tedious to do by hand), this package lets
// you define an Export class implementing simple interfaces:
//
//   FromCollection — what data goes in the rows
//   WithHeadings   — what the header row says
//   WithMapping    — how each model maps to a row of cells
//   WithStyles     — cell formatting (bold headers, currency, etc.)
//
// The package handles all the XLSX binary format complexity.
// You just describe WHAT data and HOW it should look.
// ─────────────────────────────────────────────────────────────

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    public function __construct(
        private string $from,
        private string $to,
    ) {}

    /**
     * The raw data — Eloquent collection of orders in the date range.
     */
    public function collection()
    {
        return Order::with('items', 'payment')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Column headers (row 1).
     */
    public function headings(): array
    {
        return [
            'Order Number', 'Date', 'Customer', 'Status',
            'Payment Method', 'Payment Status', 'Items',
            'Subtotal (₱)', 'Discount (₱)', 'Shipping (₱)',
            'Tax (₱)', 'Total (₱)',
        ];
    }

    /**
     * Maps ONE Order model into a row of cell values.
     * Called once per row — keeps the mapping logic separate
     * from the query logic above.
     */
    public function map($order): array
    {
        return [
            $order->order_number,
            $order->created_at->format('Y-m-d H:i'),
            "{$order->ship_first_name} {$order->ship_last_name}",
            ucfirst($order->status),
            ucfirst(str_replace('_', ' ', $order->payment?->payment_method ?? '—')),
            ucfirst($order->payment?->status ?? '—'),
            $order->items->sum('quantity'),
            (float) $order->subtotal,
            (float) $order->discount_amount,
            (float) $order->shipping_fee,
            (float) $order->tax_amount,
            (float) $order->grand_total,
        ];
    }

    /**
     * Cell styling — bold header row.
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    /**
     * Column widths so currency/customer columns aren't truncated.
     */
    public function columnWidths(): array
    {
        return [
            'A' => 18, 'B' => 16, 'C' => 24, 'D' => 12,
            'E' => 16, 'F' => 14, 'G' => 8,
            'H' => 14, 'I' => 14, 'J' => 14, 'K' => 12, 'L' => 14,
        ];
    }
}
