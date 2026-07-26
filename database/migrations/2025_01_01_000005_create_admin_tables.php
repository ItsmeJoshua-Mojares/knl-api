<?php
// database/migrations/2025_01_01_000005_create_admin_tables.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Polymorphic-style audit logging
//
// activity_logs doesn't have a fixed "subject" table — it can log
// changes to Products, Orders, Coupons, or anything else. Instead
// of a strict foreign key, we store subject_type (the model class
// name, e.g. "App\Models\Product") and subject_id (its primary key)
// as plain columns. This is Laravel's "morph" pattern, done manually
// here for clarity rather than using MorphTo (which adds complexity
// you don't need until you have 5+ loggable model types).
//
// properties is a JSON column storing what changed:
//   {"before": {"price": 22999}, "after": {"price": 24999}}
// This lets the admin UI show a real diff, not just "Product updated".
// ─────────────────────────────────────────────────────────────

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Activity / audit log ───────────────────────────────
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()
                  ->constrained()->onDelete('set null');
            $table->string('subject_type', 100); // 'App\Models\Product'
            $table->unsignedBigInteger('subject_id');
            $table->string('event', 50);         // 'created','updated','deleted'
            $table->json('properties')->nullable(); // before/after diff
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
            $table->index('created_at');
        });

        // ── Homepage banners (admin-managed hero/promo images) ──
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('subtitle', 255)->nullable();
            $table->string('image_url', 512);
            $table->string('link_url', 512)->nullable();
            $table->enum('position', ['hero', 'sidebar', 'promo'])->default('hero');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
        Schema::dropIfExists('activity_logs');
    }
};
