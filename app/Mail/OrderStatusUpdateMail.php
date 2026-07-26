<?php
// app/Mail/OrderStatusUpdateMail.php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    // Human-friendly subject lines per status
    private const SUBJECTS = [
        'confirmed'  => 'Your order has been confirmed',
        'processing' => 'Your order is being prepared',
        'shipped'    => 'Your order is on its way!',
        'delivered'  => 'Your order has been delivered',
        'cancelled'  => 'Your order has been cancelled',
    ];

    public function __construct(
        public Order $order,
        public string $newStatus,
    ) {}

    public function envelope(): Envelope
    {
        $subject = self::SUBJECTS[$this->newStatus] ?? 'Order status update';

        return new Envelope(
            subject: "{$subject} — {$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-status-update',
            with: [
                'order'      => $this->order,
                'status'     => $this->newStatus,
                'trackingNumber' => $this->order->tracking_number,
                'trackUrl'   => config('app.frontend_url') . "/dashboard?order={$this->order->order_number}",
            ],
        );
    }
}
