<?php
// config/services.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Laravel config files vs env() directly
//
// You COULD call env('CLOUDINARY_CLOUD_NAME') directly in
// ImageService. But once you run php artisan config:cache (which
// you always do in production), all .env values are compiled into
// a single cached file and env() stops reading from .env.
//
// The correct pattern in Laravel:
//   .env → config file → app code
//
// That way config:cache works correctly AND your code has one
// consistent place to look up third-party credentials.
// ─────────────────────────────────────────────────────────────

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file stores the credentials for third party services used by
    | KNL Atelier. Add new services here rather than hard-coding env()
    | calls throughout the codebase.
    |
    */

    // ── Mailgun (transactional email) ─────────────────────────
    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    // ── Cloudinary (image storage and optimisation) ───────────
    // Used by ImageService for signed server-side uploads.
    // NEVER expose api_secret to the frontend.
    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME', ''),
        'api_key'    => env('CLOUDINARY_API_KEY', ''),
        'api_secret' => env('CLOUDINARY_API_SECRET', ''),
        // Default folder for product images
        'folder'     => env('CLOUDINARY_FOLDER', 'knl-atelier/products'),
    ],

    // ── Pusher (optional — real-time order status updates) ────
    'pusher' => [
        'beams_instance_id' => env('PUSHER_BEAMS_INSTANCE_ID'),
        'beams_secret_key'  => env('PUSHER_BEAMS_SECRET_KEY'),
        'app_id'            => env('PUSHER_APP_ID'),
        'app_key'           => env('PUSHER_APP_KEY'),
        'app_secret'        => env('PUSHER_APP_SECRET'),
        'host'              => env('PUSHER_HOST'),
        'port'              => env('PUSHER_PORT', 443),
        'scheme'            => env('PUSHER_SCHEME', 'https'),
        'app_cluster'       => env('PUSHER_APP_CLUSTER', 'ap1'),
    ],

];
