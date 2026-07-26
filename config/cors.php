<?php
// config/cors.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: CORS (Cross-Origin Resource Sharing)
//
// By default browsers BLOCK JavaScript on one domain from
// calling APIs on a different domain. This is a security rule.
//
// Our setup:
//   Frontend → http://localhost:3000  (Next.js)
//   Backend  → http://localhost:8000  (Laravel)
//
// These are different "origins" (different ports), so without
// CORS the browser would block every API call with an error.
//
// This config tells Laravel to send special HTTP headers that
// say "yes, I allow requests from localhost:3000".
//
// In production, replace localhost:3000 with your real domain.
// ─────────────────────────────────────────────────────────────

return [
    // Which URL patterns this applies to — all /api/* routes
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Which HTTP methods are allowed
    'allowed_methods' => ['*'],

    // Which frontend origins are allowed to call this API
    // In production: ['https://knlatelier.com']
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
    ],

    'allowed_origins_patterns' => [],

    // Which request headers the frontend can send
    'allowed_headers' => ['*'],

    // Which response headers the frontend can read
    'exposed_headers' => [],

    // How long the browser caches the CORS preflight response (seconds)
    'max_age' => 0,

    // Allow cookies (needed for httpOnly JWT cookie storage)
    'supports_credentials' => true,
];
