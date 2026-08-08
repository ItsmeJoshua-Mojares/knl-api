<?php
// app/Events/NewProductAdded.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Event-driven notifications
//
// The admin product controller doesn't know (or care) about
// newsletters. It just announces "a new product was added" by
// dispatching this event. Any number of listeners can react
// independently — right now that's the new-arrival email, but
// tomorrow it could also be a Slack alert, an activity log entry,
// or a sitewide "NEW" badge without touching the controller.
// ─────────────────────────────────────────────────────────────

namespace App\Events;

use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewProductAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(public Product $product) {}
}
