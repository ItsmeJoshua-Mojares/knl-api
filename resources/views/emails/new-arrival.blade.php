<!-- resources/views/emails/new-arrival.blade.php -->
{{--
    CONCEPT: New-arrival broadcast email

    Sent to every active newsletter subscriber whenever an admin
    adds a new (active) product. Same table-based, inline-styled
    layout as the order emails for maximum client compatibility.

    The unsubscribe link is unique per subscriber (their token),
    satisfying "No spam. Unsubscribe anytime." from the homepage.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Arrival — {{ $product->name }}</title>
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

                {{-- Eyebrow + headline --}}
                <tr>
                    <td align="center" style="padding: 36px 40px 20px;">
                        <p style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#c9a84c; margin:0 0 8px;">
                            New Arrival
                        </p>
                        <h1 style="font-size:26px; color:#ffffff; margin:0; font-weight:600; line-height:1.25;">
                            {{ $product->name }}
                        </h1>
                        @if($product->nickname)
                        <p style="font-size:14px; color:#a0a0a0; margin:8px 0 0; font-style:italic;">
                            "{{ $product->nickname }}"
                        </p>
                        @endif
                    </td>
                </tr>

                {{-- Product image (if available) --}}
                @if($imageUrl)
                <tr>
                    <td align="center" style="padding: 0 40px 8px;">
                        <img src="{{ $imageUrl }}" alt="{{ $product->name }}"
                             width="420" style="max-width:100%; height:auto; border-radius:12px; border:1px solid #2a2a2a; display:block; background-color:#1a1a1a;" />
                    </td>
                </tr>
                @endif

                {{-- SKU + price + description --}}
                <tr>
                    <td align="center" style="padding: 24px 40px 8px;">
                        <p style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#666; margin:0 0 6px;">
                            {{ $product->sku }} &middot; {{ $product->brand->name ?? 'KNL' }}
                        </p>
                        <p style="font-size:24px; color:#ffffff; font-weight:bold; margin:0 0 12px;">
                            &#8369;{{ number_format($product->price, 2) }}
                        </p>
                        @if($product->short_desc)
                        <p style="font-size:14px; color:#a0a0a0; line-height:1.7; margin:0;">
                            {{ $product->short_desc }}
                        </p>
                        @endif
                    </td>
                </tr>

                {{-- CTA button --}}
                <tr>
                    <td align="center" style="padding: 28px 40px 40px;">
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="border-radius:30px; background-color:#2d6a35;">
                                    <a href="{{ $productUrl }}"
                                       style="display:inline-block; padding:14px 32px; font-size:13px; font-weight:bold;
                                              letter-spacing:1px; text-transform:uppercase; color:#ffffff; text-decoration:none;">
                                        Shop Now
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
                            KNL Atelier &amp; Co. &middot; Authentic luxury, yours to wear.
                        </p>
                        <p style="font-size:11px; color:#555; margin:0 0 14px;">
                            You're receiving this because you subscribed on knlatelier.com.
                        </p>
                        <p style="font-size:11px; margin:0;">
                            <a href="{{ $unsubscribeUrl }}" style="color:#888; text-decoration:underline;">
                                Unsubscribe
                            </a>
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
