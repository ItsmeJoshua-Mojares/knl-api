<?php
// app/Listeners/NotifyAdminOfNewOrder.php
// ─────────────────────────────────────────────────────────────
// This is the SECOND listener reacting to the SAME OrderPlaced
// event. Notice OrderService never changed to support this —
// we just added a new listener class. This is the payoff of
// the event-driven pattern: features compose without coupling.
// ─────────────────────────────────────────────────────────────

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyAdminOfNewOrder implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        // Notify every admin and super_admin user
        $admins = User::whereHas('role', function ($q) {
            $q->whereIn('name', ['admin', 'super_admin']);
        })->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new NewOrderNotification($event->order));
    }
}
