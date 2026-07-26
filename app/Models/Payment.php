<?php
// app/Models/Payment.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: JSON casting for gateway responses
//
// Payment gateways (GCash, Maya, Stripe) return different JSON
// structures. Instead of adding 10 columns for every possible
// field, we store the full raw response as JSON.
//
// $casts => ['gateway_response' => 'array'] means Laravel
// automatically encodes to JSON on save and decodes on read.
// You access it as a PHP array: $payment->gateway_response['status']
// ─────────────────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'payment_method', 'status',
        'amount', 'currency', 'transaction_id',
        'gateway_response', 'paid_at', 'refunded_at',
        'refund_amount', 'notes',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'refund_amount'    => 'decimal:2',
        'gateway_response' => 'array',   // JSON ↔ PHP array auto-conversion
        'paid_at'          => 'datetime',
        'refunded_at'      => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Convenience: was this payment successful?
    public function isSuccessful(): bool
    {
        return $this->status === 'paid';
    }
}
