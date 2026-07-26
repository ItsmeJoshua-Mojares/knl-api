<?php
// app/Models/OrderItem.php
// Snapshot of the product at time of purchase.
// Even if the product price changes later, the order item
// always shows what the customer actually paid.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'product_id',
        'product_name', 'product_sku', 'product_image',
        'unit_price', 'quantity', 'total_price',
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity'    => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // The product may be null if deleted — that's fine,
    // the snapshot columns hold all the info we need.
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
