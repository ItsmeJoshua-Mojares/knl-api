<?php
// app/Listeners/SendOrderStatusUpdateEmail.php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Mail\OrderStatusUpdateMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendOrderStatusUpdateEmail implements ShouldQueue
{
    // Only email the customer for these "meaningful" status changes.
    // We skip emailing for every minor internal transition.
    private const NOTIFY_STATUSES = ['confirmed', 'shipped', 'delivered', 'cancelled'];

    public function handle(OrderStatusChanged $event): void
    {
        if (!in_array($event->newStatus, self::NOTIFY_STATUSES)) {
            return;
        }

        $recipientEmail = $event->order->user?->email;
        if (!$recipientEmail) return;

        Mail::to($recipientEmail)->send(
            new OrderStatusUpdateMail($event->order, $event->newStatus)
        );
    }
}
