<?php
// app/Notifications/NewOrderNotification.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Laravel Notifications vs Mailables
//
// A Mailable sends ONE email. A Notification can send through
// MULTIPLE channels at once (email, database, SMS, Slack) using
// the SAME class — you just list the channels in via().
//
// Here we use the 'database' channel, which INSERTS a row into
// the notifications table instead of sending an email. This
// powers the admin dashboard's notification bell — "🔔 3 new
// orders" — by querying $admin->unreadNotifications.
//
// toArray() defines what gets stored in the notifications.data
// JSON column. toMail() (if added) would define an email version
// of the SAME notification — useful when you want both an email
// AND an in-app notification from one trigger.
// ─────────────────────────────────────────────────────────────

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    /**
     * Which channels to deliver through.
     * 'database' = stored in the notifications table (read by admin dashboard)
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Data stored in notifications.data (JSON column).
     * The admin dashboard reads this to render the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type'         => 'new_order',
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'customer'     => $this->order->ship_first_name . ' ' . $this->order->ship_last_name,
            'total'        => (float) $this->order->grand_total,
            'message'      => "New order {$this->order->order_number} — ₱" . number_format((float) $this->order->grand_total, 2),
        ];
    }
}
