<?php
// database/migrations/2025_01_01_000001_create_users_table.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Migrations
//
// A migration is a PHP class with two methods:
//   up()   — creates or modifies the table (run with `php artisan migrate`)
//   down() — undoes what up() did (run with `php artisan migrate:rollback`)
//
// Laravel tracks which migrations have already run in a special
// `migrations` table. So `php artisan migrate` only runs NEW ones.
//
// Blueprint methods you'll see:
//   ->id()            = BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
//   ->string('name')  = VARCHAR(255)
//   ->text('bio')     = TEXT
//   ->boolean('active') = TINYINT(1)
//   ->timestamp('...')  = TIMESTAMP NULL
//   ->timestamps()    = created_at + updated_at (both TIMESTAMP)
//   ->softDeletes()   = deleted_at (soft delete, not real delete)
//   ->foreignId('role_id')->constrained('roles') = FK with constraint
// ─────────────────────────────────────────────────────────────

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Roles table first (users reference it)
        Schema::create('roles', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 50)->unique();        // 'super_admin', 'admin', 'customer'
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // Users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();                                // BIGINT UNSIGNED PK
            $table->unsignedTinyInteger('role_id')->default(3); // default: customer
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 191)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('password');                  // hashed by bcrypt
            $table->string('avatar_url', 512)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();                        // created_at, updated_at
            $table->softDeletes();                       // deleted_at (soft delete)

            $table->foreign('role_id')
                  ->references('id')->on('roles')
                  ->onDelete('restrict');
        });

        // Password reset tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 191)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
