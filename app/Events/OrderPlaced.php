<?php
// app/Events/OrderPlaced.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Events vs direct method calls
//
// We COULD have OrderService directly call:
//   Mail::to($order->user)->send(new OrderConfirmation($order));
//   Notification::send($admins, new NewOrderAlert($order));
//
// But that couples OrderService to email logic. Every time you
// add a new "thing that happens when an order is placed" (SMS,
// Slack alert, loyalty points, analytics tracking), you'd have
// to edit OrderService again.
//
// Events solve this. OrderService just announces "this happened":
//   event(new OrderPlaced($order));
//
// Then ANY NUMBER of Listeners can react to it independently.
// OrderService doesn't know or care how many listeners exist.
// This is called "decoupling" — one of the most important
// principles in software architecture.
//
// SerializesModels — if this event is queued (run in the
// background instead of immediately), Laravel needs to convert
// the Order model to/from a simple ID for storage. This trait
// handles that conversion automatically.
// ─────────────────────────────────────────────────────────────

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
