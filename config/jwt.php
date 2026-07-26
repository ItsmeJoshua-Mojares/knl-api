<?php
// config/jwt.php  (trimmed — only the settings you need to understand)
// ─────────────────────────────────────────────────────────────
// CONCEPT: JWT (JSON Web Token)
//
// Traditional sessions store a session ID in a cookie and keep
// the actual data on the SERVER (in a file or database).
// Every request hits the database to check if the session is valid.
//
// JWT is different. When you log in, the server creates a token
// that CONTAINS your user info (encrypted + signed). The server
// sends this token to the client. On every request, the client
// sends the token back, and the server VERIFIES the signature —
// no database lookup needed.
//
// Token structure (three parts separated by dots):
//   HEADER.PAYLOAD.SIGNATURE
//   eyJ...  .eyJ...  .xyz...
//
// HEADER:  algorithm used (HS256)
// PAYLOAD: your data { user_id: 1, role: "customer", exp: ... }
// SIGNATURE: HMAC(header + payload, secret_key)
//
// If anyone tampers with the payload, the signature won't match,
// and Laravel rejects the token. This is the security guarantee.
// ─────────────────────────────────────────────────────────────

return [

    // The secret used to sign tokens — set by `php artisan jwt:secret`
    // NEVER commit this to git. It lives in .env as JWT_SECRET.
    'secret' => env('JWT_SECRET'),

    // Algorithm used to sign the token
    'algo' => env('JWT_ALGO', 'HS256'),

    // How long a token is valid (minutes)
    // 60 * 24 = 1440 minutes = 24 hours
    'ttl' => env('JWT_TTL', 1440),

    // How long after expiry the token can be refreshed (minutes)
    // 60 * 24 * 14 = 2 weeks
    'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),

    // Clock leeway — forgives clock drift of ±30 seconds between servers
    'leeway' => env('JWT_LEEWAY', 0),

    // Blacklist: when a token is refreshed or logged out,
    // add the old token here so it can't be reused
    'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),

    // Storage for the blacklist
    'blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 0),

    // Where to look for the token in incoming requests
    // 1. Authorization: Bearer <token>  header
    // 2. ?token=  query parameter (useful for testing)
    // 3. Cookie named 'token'
    'token_params' => ['query' => 'token'],

    'providers' => [
        'jwt'     => PHPOpenSourceSaver\JWTAuth\Providers\JWT\Lcobucci::class,
        'auth'    => PHPOpenSourceSaver\JWTAuth\Providers\Auth\Illuminate::class,
        'storage' => PHPOpenSourceSaver\JWTAuth\Providers\Storage\Illuminate::class,
    ],
];
