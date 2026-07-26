<!-- resources/views/pdf/sales-report.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #222; }
        .header { background-color: #152618; color: #fff; padding: 24px; }
        .header h1 { margin: 0; font-size: 20px; color: #5cb85c; }
        .header p { margin: 4px 0 0; font-size: 11px; color: #c9a84c; }
        .summary { display: table; width: 100%; margin: 20px 24px; }
        .summary-card { display: table-cell; width: 25%; padding: 12px; background-color: #f5f5f5; text-align: center; }
        .summary-card .label { font-size: 9px; text-transform: uppercase; color: #888; }
        .summary-card .value { font-size: 18px; font-weight: bold; margin-top: 4px; }
        table.orders { width: calc(100% - 48px); margin: 0 24px; border-collapse: collapse; }
        table.orders th { background-color: #f0f0f0; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; border-bottom: 2px solid #ccc; }
        table.orders td { padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 10px; }
        .footer { margin-top: 24px; padding: 12px 24px; font-size: 9px; color: #888; text-align: center; }
    </style>
</head>
<body>

    <div class="header">
        <h1>KNL Atelier &amp; Co. — Sales Report</h1>
        <p>{{ \Carbon\Carbon::parse($summary['from'])->format('M j, Y') }} &ndash; {{ \Carbon\Carbon::parse($summary['to'])->format('M j, Y') }}</p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="label">Total Orders</div>
            <div class="value">{{ $summary['total_orders'] }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Total Revenue</div>
            <div class="value">&#8369;{{ number_format($summary['total_revenue'], 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Avg Order Value</div>
            <div class="value">&#8369;{{ number_format($summary['avg_order'], 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Generated</div>
            <div class="value" style="font-size:12px;">{{ now()->format('M j, Y') }}</div>
        </div>
    </div>

    <table class="orders">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Status</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $order)
            <tr>
                <td>{{ $order->order_number }}</td>
                <td>{{ $order->created_at->format('M j, Y') }}</td>
                <td>{{ $order->ship_first_name }} {{ $order->ship_last_name }}</td>
                <td>{{ ucfirst($order->status) }}</td>
                <td style="text-align:right;">&#8369;{{ number_format($order->grand_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        KNL Atelier &amp; Co. &middot; Confidential &mdash; Internal Use Only
    </div>

</body>
</html>
