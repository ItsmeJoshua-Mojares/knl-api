<?php
// app/Listeners/SendNewArrivalEmail.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Broadcasting to many recipients
//
// When a new product is created, we loop over every subscriber
// with is_subscribed = true and send each of them a NewArrivalMail.
//
// The Mailable implements ShouldQueue, so every Mail::send() call
// pushes ONE queued job per recipient instead of sending inline.
// The admin's "save product" request returns instantly; the emails
// go out moments later via `php artisan queue:work`.
//
// With QUEUE_CONNECTION=sync (local dev) everything just runs
// inline — which is fine for testing.
// ─────────────────────────────────────────────────────────────

namespace App\Listeners;

use App\Events\NewProductAdded;
use App\Mail\NewArrivalMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNewArrivalEmail implements ShouldQueue
{
    public function handle(NewProductAdded $event): void
    {
        $subscribers = NewsletterSubscriber::where('is_subscribed', true)->get();

        if ($subscribers->isEmpty()) {
            return;
        }

        foreach ($subscribers as $subscriber) {
            Mail::to($subscriber->email)->send(new NewArrivalMail($event->product, $subscriber));
        }
    }

    /**
     * Ran only if all retries are exhausted.
     */
    public function failed(NewProductAdded $event, \Throwable $exception): void
    {
        Log::error("Failed to send new-arrival email for product {$event->product->id}: {$exception->getMessage()}");
    }
}
