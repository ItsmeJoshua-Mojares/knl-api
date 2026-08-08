<?php
// app/Mail/NewArrivalMail.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: New-arrival notification email
//
// One email type: "we added a new product." It carries the
// Product (for the hero image, name, price) and the recipient's
// NewsletterSubscriber (for their personal unsubscribe token).
//
// ShouldQueue — sending over SMTP can take 1-3s per recipient.
// Queuing each email means a product save with 1,000 subscribers
// responds instantly and the emails stream out in the background.
// ─────────────────────────────────────────────────────────────

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewArrivalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Product $product,
        public NewsletterSubscriber $subscriber,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Arrival: {$this->product->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            // Points to resources/views/emails/new-arrival.blade.php
            view: 'emails.new-arrival',
            with: [
                'product'        => $this->product,
                'productUrl'     => config('app.frontend_url') . "/product/{$this->product->slug}",
                'unsubscribeUrl' => config('app.frontend_url') . "/newsletter/unsubscribe?token={$this->subscriber->token}",
                'imageUrl'       => $this->product->primaryImage?->image_url,
            ],
        );
    }
}
