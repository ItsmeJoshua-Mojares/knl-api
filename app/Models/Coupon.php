<?php
// app/Models/Coupon.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Business logic on the Model
//
// calculateDiscount() belongs on the Coupon model because
// it's purely about the coupon's own data — it needs the
// coupon's type, value, and cap to compute a discount.
//
// Rule of thumb: if a method only needs data from this model
// and its relationships, it belongs here. If it needs data
// from multiple unrelated models, it belongs in a Service.
// ─────────────────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value',
        'min_order_amount', 'max_discount_amount',
        'usage_limit', 'used_count',
        'is_active', 'starts_at', 'expires_at',
    ];

    protected $casts = [
        'value'               => 'decimal:2',
        'min_order_amount'    => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'is_active'           => 'boolean',
        'starts_at'           => 'datetime',
        'expires_at'          => 'datetime',
    ];

    // ── Scopes ────────────────────────────────────────────────

    /** Only valid coupons (active, within date range, not over usage limit) */
    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereColumn('used_count', '<', 'usage_limit');
            });
    }

    // ── Business logic ────────────────────────────────────────

    /**
     * Calculate the discount amount for a given order subtotal.
     *
     * @param  float $subtotal  The order subtotal in PHP
     * @return float            The discount amount (never negative)
     */
    public function calculateDiscount(float $subtotal): float
    {
        // Check minimum order requirement
        if ($subtotal < (float) $this->min_order_amount) {
            return 0.0;
        }

        $discount = match ($this->type) {
            'percentage'   => $subtotal * ((float) $this->value / 100),
            'fixed'        => (float) $this->value,
            'free_shipping'=> 0.0, // handled separately in OrderService
            default        => 0.0,
        };

        // Apply maximum discount cap if set
        if ($this->max_discount_amount && $discount > (float) $this->max_discount_amount) {
            $discount = (float) $this->max_discount_amount;
        }

        // Discount can never exceed the subtotal
        return min($discount, $subtotal);
    }

    /**
     * Check if the coupon is currently usable.
     */
    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;
        return true;
    }

    /**
     * Increment the used_count atomically (prevents race conditions).
     */
    public function incrementUsage(): void
    {
        // increment() is a single UPDATE query — atomic, safe under concurrent load
        $this->increment('used_count');
    }
}
