<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Product $product) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Low Stock Alert: {$this->product->name}")
            ->line("The product \"{$this->product->name}\" (SKU: {$this->product->sku}) is running low on stock.")
            ->line("Current stock: {$this->product->stock_quantity} units (threshold: {$this->product->low_stock_threshold})")
            ->action('View Product', url("/admin/products/{$this->product->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'low_stock',
            'product_id'   => $this->product->id,
            'product_name' => $this->product->name,
            'sku'          => $this->product->sku,
            'stock'        => $this->product->stock_quantity,
            'threshold'    => $this->product->low_stock_threshold,
            'message'      => "{$this->product->name} ({$this->product->sku}) has {$this->product->stock_quantity} units left.",
        ];
    }
}
