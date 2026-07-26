<?php
// app/Mail/OrderConfirmationMail.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Mailable classes
//
// A Mailable is a PHP class that represents ONE type of email.
// It defines: who it's from, the subject line, and which Blade
// view renders the HTML body.
//
// ShouldQueue interface — when a Mailable implements this,
// Laravel doesn't send the email immediately during the request.
// Instead it pushes a job onto a QUEUE, and a separate background
// worker process sends it.
//
// Why this matters: sending email over SMTP can take 1-3 seconds.
// Without queuing, your checkout API would HANG for those seconds
// before responding to the customer. With queuing, the order API
// responds instantly, and the email goes out moments later in
// the background. The customer never waits for it.
//
// To actually process queued jobs, you run:
//   php artisan queue:work
// (In production this runs as a permanent background process,
//  often managed by Supervisor.)
// ─────────────────────────────────────────────────────────────

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order Confirmed — {$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            // Points to resources/views/emails/order-confirmation.blade.php
            view: 'emails.order-confirmation',
            with: [
                'order'      => $this->order,
                'customerName' => $this->order->ship_first_name,
                'items'      => $this->order->items,
                'trackUrl'   => config('app.frontend_url') . "/dashboard?order={$this->order->order_number}",
            ],
        );
    }
}
