<?php
// database/migrations/2025_01_01_000002_create_catalog_tables.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Relationships in the database
//
// One-to-Many: One Category has many Products.
//   categories.id  ←  products.category_id
//
// Many-to-One: Many Products belong to one Brand.
//   brands.id  ←  products.brand_id
//
// Foreign key constraints enforce these relationships.
// If you try to delete a Category that still has Products,
// MySQL throws an error instead of silently breaking your data.
//
// ->json('specifications') stores watch specs as a JSON column:
//   {"diameter":"42.5mm","movement":"Automatic","crystal":"Hardlex"}
// This lets us store flexible specs without adding 20 columns.
// ─────────────────────────────────────────────────────────────

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Categories ──────────────────────────────────────────
        Schema::create('categories', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('parent_id')->nullable(); // for sub-categories
            $table->string('name', 100);
            $table->string('slug', 120)->unique();       // URL-friendly: "watches"
            $table->text('description')->nullable();
            $table->string('image_url', 512)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('meta_title', 160)->nullable();
            $table->string('meta_desc', 320)->nullable();
            $table->timestamps();

            $table->foreign('parent_id')
                  ->references('id')->on('categories')
                  ->onDelete('set null');
        });

        // ── Brands ──────────────────────────────────────────────
        Schema::create('brands', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 100)->unique();       // "Seiko"
            $table->string('slug', 120)->unique();       // "seiko"
            $table->string('logo_url', 512)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Products ─────────────────────────────────────────────
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('category_id');
            $table->unsignedSmallInteger('brand_id')->nullable();

            // Core info
            $table->string('name', 255);
            $table->string('slug', 280)->unique();
            $table->string('sku', 80)->unique();         // "SSK001"
            $table->string('ref_number', 80)->nullable(); // "SSK001" (watch ref)
            $table->string('caliber_number', 40)->nullable(); // "4R34"
            $table->string('short_desc', 500)->nullable();
            $table->longText('description')->nullable();

            // Specs stored as JSON (flexible — works for watches, shoes, fragrances)
            $table->json('specifications')->nullable();

            // Pricing (DECIMAL for money — never use float for currency!)
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();

            // Inventory
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedSmallInteger('low_stock_threshold')->default(5);
            $table->string('condition_status', 30)->default('New');

            // Flags
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_bestseller')->default(false);

            // Ratings (denormalized for fast reads — updated by trigger/job)
            $table->decimal('rating_avg', 3, 2)->default(0.00);
            $table->unsignedInteger('rating_count')->default(0);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('meta_title', 160)->nullable();
            $table->string('meta_desc', 320)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')
                  ->references('id')->on('categories');
            $table->foreign('brand_id')
                  ->references('id')->on('brands')
                  ->onDelete('set null');

            // Indexes for fast filtering
            $table->index('is_featured');
            $table->index('is_bestseller');
            $table->index('category_id');
        });

        // ── Product Images ────────────────────────────────────────
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('image_url', 512);
            $table->string('thumbnail_url', 512)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
