<?php
// app/Models/NewsletterSubscriber.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Newsletter subscribers
//
// One row per email address that has subscribed to the homepage
// "Stay Updated" form. `token` is a random per-subscriber string
// used for the one-click unsubscribe link in the email footer —
// it lets the user opt out without logging in. `is_subscribed`
// toggles to false on unsubscribe so we keep the row (and the
// token) but simply skip them in future broadcasts.
// ─────────────────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email',
        'token',
        'is_subscribed',
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
    ];

    public static function generateToken(): string
    {
        return Str::random(64);
    }
}
