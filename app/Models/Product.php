<?php
// app/Models/Product.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Eloquent Scopes
//
// Scopes are reusable query conditions you define once on the model.
// Instead of repeating ->where('is_active', true) in every
// controller, you define scopeActive() here and call it as:
//   Product::active()->featured()->get()
//
// Laravel strips the "scope" prefix and lowercases the first
// letter, so scopeActive() becomes ->active().
//
// CONCEPT: JSON casting
// The 'specifications' column is stored as a JSON string in MySQL
// but cast to a PHP array automatically. You access it like:
//   $product->specifications['diameter']  // "42.5mm"
//
// CONCEPT: Accessors
// getDiscountPercentageAttribute() is an accessor — a computed
// property that doesn't exist in the database but appears in the
// model as $product->discount_percentage. Prefixed with get and
// suffixed with Attribute (legacy syntax).
// ─────────────────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'slug', 'sku',
        'ref_number', 'caliber_number', 'short_desc', 'description',
        'specifications', 'price', 'compare_at_price', 'cost_price',
        'stock_quantity', 'low_stock_threshold', 'condition_status',
        'is_active', 'is_featured', 'is_bestseller',
        'sort_order', 'meta_title', 'meta_desc',
    ];

    protected $casts = [
        'specifications'  => 'array',    // JSON → PHP array automatically
        'price'           => 'decimal:2',
        'compare_at_price'=> 'decimal:2',
        'cost_price'      => 'decimal:2',
        'rating_avg'      => 'decimal:2',
        'is_active'       => 'boolean',
        'is_featured'     => 'boolean',
        'is_bestseller'   => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** All images, ordered by sort_order */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /** Only the primary (hero) image */
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Inventory movement log — every stock change is recorded here.
     * Used by OrderService when an order is placed (sale) and when
     * an admin manually adjusts stock (adjustment/import).
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class)->orderByDesc('created_at');
    }

    // ── Query Scopes ─────────────────────────────────────────
    // Usage: Product::active()->featured()->orderBy('price')->get()

    /** Only active (visible) products */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Only featured products (for homepage) */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /** Only best sellers */
    public function scopeBestSeller(Builder $query): Builder
    {
        return $query->where('is_bestseller', true);
    }

    /** Products in a specific category (by slug) */
    public function scopeInCategory(Builder $query, string $slug): Builder
    {
        return $query->whereHas('category', fn ($q) => $q->where('slug', $slug));
    }

    /** Products with stock available */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /** Products at or below a maximum price */
    public function scopeMaxPrice(Builder $query, float $max): Builder
    {
        return $query->where('price', '<=', $max);
    }

    /** Products at or above a minimum price */
    public function scopeMinPrice(Builder $query, float $min): Builder
    {
        return $query->where('price', '>=', $min);
    }

    // ── Accessors ────────────────────────────────────────────

    /**
     * Discount percentage if compare_at_price is set.
     * $product->discount_percentage → 15 (meaning 15% off)
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        if (!$this->compare_at_price || $this->compare_at_price <= $this->price) {
            return null;
        }

        return (int) round(
            (($this->compare_at_price - $this->price) / $this->compare_at_price) * 100
        );
    }

    /**
     * Whether the product is low on stock.
     * $product->is_low_stock → true/false
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->stock_quantity <= $this->low_stock_threshold
            && $this->stock_quantity > 0;
    }

    /**
     * Whether the product is completely out of stock.
     */
    public function getIsOutOfStockAttribute(): bool
    {
        return $this->stock_quantity === 0;
    }
}
