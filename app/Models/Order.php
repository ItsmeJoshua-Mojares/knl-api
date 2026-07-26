<?php
// app/Models/Order.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Static factory methods on models
//
// generateOrderNumber() is a static method that creates the
// KNL-2025-00001 format. Static means you call it on the class,
// not an instance: Order::generateOrderNumber()
//
// We use LPAD (or str_pad in PHP) to zero-pad the sequence number
// so "1" becomes "00001" — this keeps orders sortable as strings.
//
// CONCEPT: Model events via boot()
//
// The static boot() method lets you hook into Eloquent lifecycle
// events. 'creating' fires right before INSERT.
// We auto-generate the order number here so every order
// always has one — no controller needs to remember to set it.
// ─────────────────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'order_number', 'status',
        'ship_first_name', 'ship_last_name', 'ship_phone',
        'ship_address_line1', 'ship_address_line2',
        'ship_city', 'ship_province', 'ship_postal_code', 'ship_country',
        'subtotal', 'discount_amount', 'shipping_fee', 'tax_amount', 'grand_total',
        'coupon_id', 'coupon_code',
        'shipping_method', 'tracking_number',
        'shipped_at', 'delivered_at',
        'customer_notes', 'admin_notes', 'ip_address',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_fee'    => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'grand_total'     => 'decimal:2',
        'shipped_at'      => 'datetime',
        'delivered_at'    => 'datetime',
    ];

    // ── Auto-generate order number before INSERT ──────────────
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    // ── Order number generator ────────────────────────────────
    // Format: KNL-2025-00001
    public static function generateOrderNumber(): string
    {
        $year  = date('Y');
        $prefix = "KNL-{$year}-";

        // Find the last order this year and increment
        $last = self::where('order_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('order_number');

        $next = $last
            ? (int) substr($last, strlen($prefix)) + 1
            : 1;

        // Zero-pad to 5 digits: 1 → 00001
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    // ── Relationships ─────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // ── Helpers ───────────────────────────────────────────────

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    public function isPaid(): bool
    {
        return $this->payments()
            ->where('status', 'paid')
            ->exists();
    }
}
