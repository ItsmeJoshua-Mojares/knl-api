<?php
// database/migrations/2025_01_01_000003_create_commerce_tables.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Snapshot pattern for orders
//
// When a customer places an order, we COPY the product name,
// SKU, and price into the order_items table. We don't just
// store the product_id.
//
// Why? Because products change. If Seiko raises the price
// of SSK001 tomorrow, you don't want old orders to show the
// new price. The snapshot captures what the customer actually
// paid at the time of purchase.
//
// This is a fundamental e-commerce pattern — always snapshot
// price and product info into your order items.
// ─────────────────────────────────────────────────────────────

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Coupons ──────────────────────────────────────────────
        Schema::create('coupons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 50)->unique();
            $table->string('description', 255)->nullable();
            $table->enum('type', ['percentage', 'fixed', 'free_shipping']);
            $table->decimal('value', 10, 2);
            $table->decimal('min_order_amount', 12, 2)->default(0);
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // ── Cart ──────────────────────────────────────────────────
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id', 128)->nullable(); // for guest carts
            $table->unsignedInteger('coupon_id')->nullable();
            $table->timestamps();

            $table->foreign('coupon_id')
                  ->references('id')->on('coupons')
                  ->onDelete('set null');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2); // price when added to cart
            $table->timestamps();

            $table->unique(['cart_id', 'product_id']); // one row per product per cart
        });

        // ── Orders ────────────────────────────────────────────────
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // KNL-2025-00001 format
            $table->string('order_number', 30)->unique();

            $table->enum('status', [
                'pending', 'confirmed', 'processing',
                'shipped', 'delivered', 'cancelled', 'returned', 'refunded'
            ])->default('pending');

            // Shipping address snapshot (copied at order time)
            $table->string('ship_first_name', 80)->nullable();
            $table->string('ship_last_name', 80)->nullable();
            $table->string('ship_phone', 20)->nullable();
            $table->string('ship_address_line1', 255)->nullable();
            $table->string('ship_address_line2', 255)->nullable();
            $table->string('ship_city', 100)->nullable();
            $table->string('ship_province', 100)->nullable();
            $table->string('ship_postal_code', 20)->nullable();
            $table->string('ship_country', 80)->default('Philippines');

            // Pricing
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);

            // Coupon
            $table->unsignedInteger('coupon_id')->nullable();
            $table->string('coupon_code', 50)->nullable();

            // Shipping
            $table->string('shipping_method', 80)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Notes
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('coupon_id')
                  ->references('id')->on('coupons')
                  ->onDelete('set null');

            $table->index('status');
            $table->index('order_number');
        });

        // ── Order Items (snapshot pattern) ────────────────────────
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');

            // SNAPSHOT — copied from product at order time
            $table->string('product_name', 255);
            $table->string('product_sku', 80)->nullable();
            $table->string('product_image', 512)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('total_price', 12, 2);
        });

        // ── Payments ──────────────────────────────────────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->enum('payment_method', [
                'gcash', 'maya', 'bank_transfer', 'cod', 'stripe', 'paypal'
            ]);
            $table->enum('status', [
                'pending', 'paid', 'failed', 'refunded', 'partially_refunded'
            ])->default('pending');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('PHP');
            $table->string('transaction_id', 255)->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ── Wishlist ──────────────────────────────────────────────
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'product_id']); // can't save same product twice
        });

        // ── Reviews ───────────────────────────────────────────────
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->string('title', 255)->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_verified')->default(false); // verified purchase
            $table->boolean('is_approved')->default(false); // admin approved
            $table->text('admin_reply')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();

            // One review per user per product
            $table->unique(['user_id', 'product_id']);
        });

        // ── Addresses ─────────────────────────────────────────────
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('label', 50)->default('Home');
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('phone', 20)->nullable();
            $table->string('address_line1', 255);
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100);
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 80)->default('Philippines');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('coupons');
    }
};
