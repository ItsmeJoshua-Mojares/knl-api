<!-- resources/views/emails/order-confirmation.blade.php -->
{{--
    CONCEPT: Blade templating for emails

    Blade is Laravel's templating engine. {{ $variable }} echoes
    a PHP value (auto-escaped for security against XSS).
    @foreach / @endforeach loops over arrays just like PHP.
    @if / @endif handles conditionals.

    Email HTML is notoriously fragile across clients (Gmail,
    Outlook, Apple Mail all render differently). We use TABLE-based
    layout and INLINE styles because:
      - Many email clients strip <style> blocks entirely
      - Outlook uses Word's rendering engine, not a real browser
      - Tables are the only layout primitive guaranteed to work
        consistently everywhere

    This is the one place in the whole project where we abandon
    modern CSS (flexbox, grid) for 1990s-style table layouts —
    it's an email-specific constraint, not a step backward.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed</title>
</head>
<body style="margin:0; padding:0; background-color:#0e0e0e; font-family:Arial, Helvetica, sans-serif;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0e0e0e; padding: 40px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                   style="background-color:#141414; border-radius:16px; overflow:hidden; border:1px solid #2a2a2a;">

                {{-- Header / Logo --}}
                <tr>
                    <td align="center" style="padding: 40px 40px 24px; background: linear-gradient(135deg,#0d1f10,#152618);">
                        <div style="font-family:Georgia, serif; font-size:28px; font-weight:bold; color:#5cb85c; letter-spacing:-1px;">
                            KNL
                        </div>
                        <div style="font-size:9px; letter-spacing:3px; text-transform:uppercase; color:#c9a84c; margin-top:4px;">
                            Atelier &amp; Co.
                        </div>
                    </td>
                </tr>

                {{-- Confirmation message --}}
                <tr>
                    <td style="padding: 36px 40px 20px;">
                        <p style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#5cb85c; margin:0 0 8px;">
                            Order Confirmed
                        </p>
                        <h1 style="font-size:24px; color:#ffffff; margin:0 0 16px; font-weight:600;">
                            Thank you, {{ $customerName }}!
                        </h1>
                        <p style="font-size:14px; color:#a0a0a0; line-height:1.6; margin:0;">
                            We've received your order and we're getting it ready. You'll receive
                            another email once it ships.
                        </p>
                    </td>
                </tr>

                {{-- Order number badge --}}
                <tr>
                    <td style="padding: 0 40px 24px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="background-color:#1a1a1a; border-radius:10px; border:1px solid #2a2a2a;">
                            <tr>
                                <td style="padding:16px 20px;">
                                    <p style="font-size:10px; letter-spacing:2px; text-transform:uppercase; color:#666; margin:0 0 4px;">
                                        Order Number
                                    </p>
                                    <p style="font-size:18px; font-weight:bold; color:#ffffff; margin:0; letter-spacing:1px;">
                                        {{ $order->order_number }}
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Order items --}}
                <tr>
                    <td style="padding: 0 40px;">
                        <p style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#666; margin:0 0 12px;">
                            Order Summary
                        </p>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            @foreach($items as $item)
                            <tr>
                                <td style="padding:10px 0; border-bottom:1px solid #2a2a2a;">
                                    <p style="font-size:13px; color:#ffffff; margin:0; font-weight:500;">
                                        {{ $item->product_name }}
                                    </p>
                                    <p style="font-size:11px; color:#888; margin:2px 0 0;">
                                        SKU: {{ $item->product_sku }} &times; {{ $item->quantity }}
                                    </p>
                                </td>
                                <td align="right" style="padding:10px 0; border-bottom:1px solid #2a2a2a;">
                                    <p style="font-size:14px; color:#ffffff; margin:0; font-weight:bold;">
                                        &#8369;{{ number_format($item->total_price, 2) }}
                                    </p>
                                </td>
                            </tr>
                            @endforeach
                        </table>

                        {{-- Totals --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
                            <tr>
                                <td style="padding:4px 0; font-size:13px; color:#a0a0a0;">Subtotal</td>
                                <td align="right" style="padding:4px 0; font-size:13px; color:#ffffff;">&#8369;{{ number_format($order->subtotal, 2) }}</td>
                            </tr>
                            @if($order->discount_amount > 0)
                            <tr>
                                <td style="padding:4px 0; font-size:13px; color:#5cb85c;">Discount ({{ $order->coupon_code }})</td>
                                <td align="right" style="padding:4px 0; font-size:13px; color:#5cb85c;">&minus;&#8369;{{ number_format($order->discount_amount, 2) }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td style="padding:4px 0; font-size:13px; color:#a0a0a0;">Shipping</td>
                                <td align="right" style="padding:4px 0; font-size:13px; color:#ffffff;">
                                    {{ $order->shipping_fee > 0 ? '₱'.number_format($order->shipping_fee, 2) : 'FREE' }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:4px 0; font-size:13px; color:#a0a0a0;">VAT (12%)</td>
                                <td align="right" style="padding:4px 0; font-size:13px; color:#ffffff;">&#8369;{{ number_format($order->tax_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="padding:12px 0 0; font-size:15px; color:#ffffff; font-weight:bold; border-top:1px solid #2a2a2a;">Total</td>
                                <td align="right" style="padding:12px 0 0; font-size:18px; color:#ffffff; font-weight:bold; border-top:1px solid #2a2a2a;">&#8369;{{ number_format($order->grand_total, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Shipping address --}}
                <tr>
                    <td style="padding: 28px 40px 0;">
                        <p style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#666; margin:0 0 10px;">
                            Shipping To
                        </p>
                        <p style="font-size:13px; color:#c0c0c0; line-height:1.6; margin:0;">
                            {{ $order->ship_first_name }} {{ $order->ship_last_name }}<br>
                            {{ $order->ship_address_line1 }}@if($order->ship_address_line2), {{ $order->ship_address_line2 }}@endif<br>
                            {{ $order->ship_city }}, {{ $order->ship_province }} {{ $order->ship_postal_code }}<br>
                            {{ $order->ship_phone }}
                        </p>
                    </td>
                </tr>

                {{-- CTA button --}}
                <tr>
                    <td align="center" style="padding: 32px 40px 40px;">
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="border-radius:30px; background-color:#2d6a35;">
                                    <a href="{{ $trackUrl }}"
                                       style="display:inline-block; padding:14px 32px; font-size:13px; font-weight:bold;
                                              letter-spacing:1px; text-transform:uppercase; color:#ffffff; text-decoration:none;">
                                        Track Your Order
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding: 24px 40px; background-color:#0a0a0a; border-top:1px solid #2a2a2a;">
                        <p style="font-size:11px; color:#555; margin:0 0 6px;">
                            KNL Atelier &amp; Co. &middot; 123 Anywhere St., Any City, Philippines
                        </p>
                        <p style="font-size:11px; color:#555; margin:0;">
                            Questions? Email <a href="mailto:hello@reallygreatsite.com" style="color:#5cb85c;">hello@reallygreatsite.com</a>
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
