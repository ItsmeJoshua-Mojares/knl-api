<!-- resources/views/emails/order-status-update.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order Update</title>
</head>
<body style="margin:0; padding:0; background-color:#0e0e0e; font-family:Arial, Helvetica, sans-serif;">

@php
    // Status-specific copy — defined once, used below
    $statusInfo = [
        'confirmed'  => ['emoji' => '✅', 'title' => 'Order Confirmed',        'body' => 'Your payment has been verified and your order is confirmed.'],
        'processing' => ['emoji' => '📦', 'title' => 'Preparing Your Order',   'body' => 'We are carefully packaging your items.'],
        'shipped'    => ['emoji' => '🚚', 'title' => 'Your Order Has Shipped', 'body' => 'Your package is on its way to you.'],
        'delivered'  => ['emoji' => '🎉', 'title' => 'Order Delivered',       'body' => 'Your order has arrived. We hope you love it!'],
        'cancelled'  => ['emoji' => '❌', 'title' => 'Order Cancelled',        'body' => 'Your order has been cancelled as requested.'],
    ][$status] ?? ['emoji' => '📋', 'title' => 'Order Update', 'body' => 'Your order status has changed.'];
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0e0e0e; padding: 40px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                   style="background-color:#141414; border-radius:16px; overflow:hidden; border:1px solid #2a2a2a;">

                <tr>
                    <td align="center" style="padding: 40px 40px 24px; background: linear-gradient(135deg,#0d1f10,#152618);">
                        <div style="font-family:Georgia, serif; font-size:28px; font-weight:bold; color:#5cb85c;">KNL</div>
                        <div style="font-size:9px; letter-spacing:3px; text-transform:uppercase; color:#c9a84c; margin-top:4px;">Atelier &amp; Co.</div>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding: 36px 40px 20px;">
                        <div style="font-size:48px; margin-bottom:12px;">{{ $statusInfo['emoji'] }}</div>
                        <h1 style="font-size:22px; color:#ffffff; margin:0 0 12px; font-weight:600;">
                            {{ $statusInfo['title'] }}
                        </h1>
                        <p style="font-size:14px; color:#a0a0a0; line-height:1.6; margin:0 0 24px;">
                            {{ $statusInfo['body'] }}
                        </p>

                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                            <tr>
                                <td style="background-color:#1a1a1a; border-radius:10px; border:1px solid #2a2a2a; padding:14px 24px;">
                                    <p style="font-size:10px; letter-spacing:2px; text-transform:uppercase; color:#666; margin:0 0 4px;">Order</p>
                                    <p style="font-size:16px; font-weight:bold; color:#ffffff; margin:0;">{{ $order->order_number }}</p>
                                </td>
                            </tr>
                        </table>

                        @if($status === 'shipped' && $trackingNumber)
                        <p style="font-size:13px; color:#c0c0c0; margin:20px 0 0;">
                            Tracking Number: <strong style="color:#ffffff;">{{ $trackingNumber }}</strong>
                        </p>
                        @endif
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding: 8px 40px 40px;">
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="border-radius:30px; background-color:#2d6a35;">
                                    <a href="{{ $trackUrl }}"
                                       style="display:inline-block; padding:14px 32px; font-size:13px; font-weight:bold;
                                              letter-spacing:1px; text-transform:uppercase; color:#ffffff; text-decoration:none;">
                                        View Order
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding: 24px 40px; background-color:#0a0a0a; border-top:1px solid #2a2a2a;">
                        <p style="font-size:11px; color:#555; margin:0;">
                            KNL Atelier &amp; Co. &middot; hello@reallygreatsite.com
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
