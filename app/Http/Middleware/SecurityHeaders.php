<?php
// app/Http/Middleware/SecurityHeaders.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: HTTP Security Headers
//
// These headers are instructions your server sends to the browser
// with every response. They harden the app against common attacks
// without changing any application logic.
//
// HSTS (HTTP Strict Transport Security)
//   Tells browsers: "Always use HTTPS for this domain — never
//   HTTP, even if the user types http://". Protects against
//   man-in-the-middle attacks and protocol downgrade attacks.
//   max-age=31536000 = 1 year. Only enable after your SSL cert
//   is confirmed working, or you'll lock users out.
//
// X-Content-Type-Options: nosniff
//   Prevents MIME sniffing — browsers guessing content type.
//   Without it, a malicious .jpg that contains JavaScript could
//   be executed. With it, the browser trusts the Content-Type header.
//
// X-Frame-Options: DENY
//   Prevents your site from being embedded in an <iframe> on
//   another domain. Blocks "clickjacking" attacks where attackers
//   overlay invisible frames over your checkout button.
//
// Referrer-Policy
//   Controls how much of your URL is sent to other sites when
//   users click links. "strict-origin-when-cross-origin" sends
//   the full URL for same-site links, only the domain for
//   cross-site links. Prevents leaking internal URLs/tokens.
//
// Permissions-Policy
//   Disables browser features your app doesn't need.
//   If KNL never uses the camera API, disable it — so if a
//   dependency gets compromised, it can't access the camera.
//
// Content-Security-Policy (CSP)
//   The most powerful but also most complex header. It defines
//   exactly which domains are allowed to load scripts, styles,
//   images, and fonts. Prevents XSS by blocking scripts loaded
//   from unexpected sources.
//
//   For a real production deployment, tighten the CSP after you
//   know exactly which third-party domains you load from.
// ─────────────────────────────────────────────────────────────

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only add security headers to API responses (not file downloads)
        if ($this->shouldAddHeaders($request)) {
            $response->headers->set(
                'X-Content-Type-Options',
                'nosniff'
            );

            $response->headers->set(
                'X-Frame-Options',
                'DENY'
            );

            $response->headers->set(
                'Referrer-Policy',
                'strict-origin-when-cross-origin'
            );

            $response->headers->set(
                'Permissions-Policy',
                'camera=(), microphone=(), geolocation=(), payment=(self), usb=()'
            );

            // HSTS — only enable in production with a valid SSL cert
            if (app()->environment('production')) {
                $response->headers->set(
                    'Strict-Transport-Security',
                    'max-age=31536000; includeSubDomains'
                );
            }

            // Content-Security-Policy
            // Start permissive, tighten per-header as you confirm what's needed
            $csp = implode('; ', [
                "default-src 'self'",
                // Allow scripts from same origin + Cloudinary upload SDK
                "script-src 'self' 'unsafe-inline' https://connect.facebook.net https://www.googletagmanager.com https://widget.cloudinary.com",
                // Allow styles from same origin + Google Fonts
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                // Allow fonts from Google Fonts
                "font-src 'self' https://fonts.gstatic.com",
                // Allow images from same origin + Cloudinary + Google Analytics
                "img-src 'self' data: blob: https://res.cloudinary.com https://www.google-analytics.com",
                // Allow XHR/fetch to same origin + Cloudinary + your API
                "connect-src 'self' https://api.cloudinary.com https://www.google-analytics.com https://connect.facebook.net",
                // Disallow embedding in frames
                "frame-ancestors 'none'",
                // Only allow form submissions to same origin
                "form-action 'self'",
                // Upgrade HTTP requests to HTTPS automatically
                "upgrade-insecure-requests",
            ]);

            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }

    /**
     * Skip security headers for file download responses —
     * they have their own Content-Disposition headers that
     * can conflict with CSP.
     */
    private function shouldAddHeaders(Request $request): bool
    {
        $skipPaths = [
            'api/admin/reports',
            'api/admin/orders',  // invoice PDF downloads
        ];

        foreach ($skipPaths as $path) {
            if (str_contains($request->path(), $path) &&
                str_contains($request->path(), 'pdf') || str_contains($request->path(), 'csv') || str_contains($request->path(), 'excel')) {
                return false;
            }
        }

        return true;
    }
}
