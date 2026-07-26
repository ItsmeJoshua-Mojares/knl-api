<?php
// app/Listeners/SendOrderConfirmationEmail.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Listeners
//
// A Listener is a class with ONE job: react to ONE event.
// This listener's only job is "when an order is placed, email
// the customer." It doesn't know about stock, payments, or
// anything else in OrderService.
//
// ShouldQueue on the LISTENER (not just the Mailable) means the
// listener itself runs in the background. This matters because
// even calling Mail::send() takes a moment to set up — queuing
// the listener means the OrderService transaction commits and
// returns to the controller instantly, with zero email-related
// delay whatsoever.
//
// Registering listeners: in Laravel 11+, listeners are
// auto-discovered if they're in app/Listeners and type-hint
// the event in their handle() method. No manual registration
// needed in EventServiceProvider.
// ─────────────────────────────────────────────────────────────

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmationEmail implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        // Send to the registered user's email, or a guest email
        // if you support guest checkout (not in this phase).
        $recipientEmail = $order->user?->email;

        if (!$recipientEmail) {
            Log::warning("Could not send order confirmation — no email for order {$order->order_number}");
            return;
        }

        Mail::to($recipientEmail)->send(new OrderConfirmationMail($order));
    }

    /**
     * Handle a failed job — retries happen automatically per the
     * queue connection config, but if all retries are exhausted
     * we want to know about it.
     */
    public function failed(OrderPlaced $event, \Throwable $exception): void
    {
        Log::error("Failed to send order confirmation for {$event->order->order_number}: {$exception->getMessage()}");
    }
}
