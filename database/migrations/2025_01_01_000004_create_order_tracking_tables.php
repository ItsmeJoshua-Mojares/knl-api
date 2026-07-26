<?php
// database/migrations/2025_01_01_000004_create_order_tracking_tables.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Why these tables weren't in the original commerce migration
//
// order_status_history and inventory_logs are AUDIT tables —
// they exist purely to answer "what happened and when?" rather
// than to drive the application's current state.
//
// We separate them from the core commerce migration because:
//   1. They're append-only (never UPDATE, only INSERT)
//   2. They can be archived/pruned independently of live data
//   3. Conceptually they're "logs", not "state"
//
// This keeps each migration focused on one responsibility,
// which makes the migration history easier to read later.
// ─────────────────────────────────────────────────────────────

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Order status history (audit trail) ────────────────
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('status', 50);
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->nullable()
                  ->constrained('users')->onDelete('set null');
            $table->timestamp('created_at')->useCurrent();

            $table->index('order_id');
        });

        // ── Inventory logs (stock movement audit trail) ───────
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()
                  ->constrained()->onDelete('set null');
            $table->enum('type', ['purchase', 'sale', 'adjustment', 'return', 'import']);
            $table->integer('quantity_before');
            $table->integer('quantity_change'); // can be negative
            $table->integer('quantity_after');
            $table->unsignedBigInteger('reference_id')->nullable(); // order_id, etc.
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('product_id');
            $table->index(['type', 'created_at']);
        });

        // ── Notifications (for admin low-stock alerts, etc.) ──
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type', 100);   // 'OrderPlaced', 'LowStock', etc.
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('inventory_logs');
        Schema::dropIfExists('order_status_history');
    }
};
