<!-- resources/views/pdf/order-invoice.blade.php -->
{{--
    CONCEPT: dompdf rendering constraints

    dompdf renders HTML+CSS to PDF using its own layout engine —
    NOT a real browser. It supports a subset of CSS2/early CSS3:
    no flexbox, no grid, limited @media support. Table-based
    layout (same as the email templates) is the most reliable
    approach here too, for the same reasons.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #222; }
        .header { background-color: #152618; color: #fff; padding: 24px; }
        .header h1 { margin: 0; font-size: 22px; color: #5cb85c; }
        .header p { margin: 2px 0 0; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #c9a84c; }
        .meta { padding: 20px 24px 0; }
        .meta table { width: 100%; }
        .meta td { vertical-align: top; padding-bottom: 16px; }
        .label { font-size: 9px; text-transform: uppercase; color: #888; letter-spacing: 1px; }
        .value { font-size: 13px; font-weight: bold; color: #000; margin-top: 2px; }
        table.items { width: 100%; border-collapse: collapse; margin: 0 24px; width: calc(100% - 48px); }
        table.items th { background-color: #f0f0f0; text-align: left; padding: 8px; font-size: 10px; text-transform: uppercase; border-bottom: 2px solid #ccc; }
        table.items td { padding: 8px; border-bottom: 1px solid #eee; font-size: 11px; }
        table.totals { width: 280px; margin: 16px 24px 0 auto; }
        table.totals td { padding: 4px 0; font-size: 12px; }
        table.totals .grand { font-weight: bold; font-size: 15px; border-top: 2px solid #000; padding-top: 8px; }
        .footer { margin-top: 30px; padding: 16px 24px; background-color: #f5f5f5; font-size: 9px; color: #888; text-align: center; }
    </style>
</head>
<body>

    <div class="header">
        <h1>KNL</h1>
        <p>Atelier &amp; Co.</p>
    </div>

    <div class="meta">
        <table>
            <tr>
                <td width="50%">
                    <div class="label">Invoice For</div>
                    <div class="value">{{ $order->order_number }}</div>
                </td>
                <td width="50%">
                    <div class="label">Date Issued</div>
                    <div class="value">{{ $order->created_at->format('F j, Y') }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Bill To</div>
                    <div class="value">{{ $order->ship_first_name }} {{ $order->ship_last_name }}</div>
                    <div>{{ $order->ship_address_line1 }}@if($order->ship_address_line2), {{ $order->ship_address_line2 }}@endif</div>
                    <div>{{ $order->ship_city }}, {{ $order->ship_province }} {{ $order->ship_postal_code }}</div>
                    <div>{{ $order->ship_phone }}</div>
                </td>
                <td>
                    <div class="label">Payment Method</div>
                    <div class="value">{{ ucfirst(str_replace('_', ' ', $order->payment?->payment_method ?? '—')) }}</div>
                    <div class="label" style="margin-top:8px;">Status</div>
                    <div class="value">{{ ucfirst($order->status) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th style="text-align:center;">Qty</th>
                <th style="text-align:right;">Unit Price</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->product_sku }}</td>
                <td style="text-align:center;">{{ $item->quantity }}</td>
                <td style="text-align:right;">&#8369;{{ number_format($item->unit_price, 2) }}</td>
                <td style="text-align:right;">&#8369;{{ number_format($item->total_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td style="text-align:right;">&#8369;{{ number_format($order->subtotal, 2) }}</td></tr>
        @if($order->discount_amount > 0)
        <tr><td>Discount ({{ $order->coupon_code }})</td><td style="text-align:right;">&minus;&#8369;{{ number_format($order->discount_amount, 2) }}</td></tr>
        @endif
        <tr><td>Shipping</td><td style="text-align:right;">{{ $order->shipping_fee > 0 ? '₱'.number_format($order->shipping_fee, 2) : 'FREE' }}</td></tr>
        <tr><td>VAT (12%)</td><td style="text-align:right;">&#8369;{{ number_format($order->tax_amount, 2) }}</td></tr>
        <tr class="grand"><td>Total</td><td style="text-align:right;">&#8369;{{ number_format($order->grand_total, 2) }}</td></tr>
    </table>

    <div class="footer">
        KNL Atelier &amp; Co. &middot; 123 Anywhere St., Any City, Philippines &middot; hello@reallygreatsite.com
    </div>

</body>
</html>
