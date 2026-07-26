<?php
// app/Models/InventoryLog.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Why log every stock change instead of just storing
// the current stock_quantity?
//
// products.stock_quantity tells you WHAT the stock is right now.
// inventory_logs tells you WHY it changed and WHEN.
//
// This matters for real e-commerce because you'll eventually
// need to answer questions like:
//   "Why did SSK001 go from 10 to 7 units last Tuesday?"
//   "How many units were sold vs manually adjusted this month?"
//
// Without a log, that history is gone forever once stock_quantity
// is overwritten. With a log, every change is permanent and
// auditable — exactly like a bank transaction ledger.
// ─────────────────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    public $timestamps = false;
    const UPDATED_AT = null;

    protected $fillable = [
        'product_id', 'user_id', 'type',
        'quantity_before', 'quantity_change', 'quantity_after',
        'reference_id', 'note',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
