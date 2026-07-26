<?php
// app/Models/User.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Eloquent Models
//
// A Model is a PHP class that represents ONE database table.
// Eloquent (Laravel's ORM) automatically maps:
//   $user->email    →  SELECT email FROM users WHERE id = ?
//   $user->save()   →  UPDATE users SET ... WHERE id = ?
//   User::create()  →  INSERT INTO users ...
//
// No SQL needed. Eloquent handles it.
//
// Key concepts in this file:
//
// $fillable — the columns that can be mass-assigned (set all at
//   once from an array). Without this, User::create(['email'=>...])
//   would throw a MassAssignmentException as a security measure.
//
// $hidden — columns that are NEVER included in JSON responses.
//   The password should never leave the server.
//
// $casts — automatic type conversion. 'is_active' => 'boolean'
//   means PHP gets true/false instead of 1/0 from MySQL.
//
// Relationships: belongsTo, hasMany, hasOne define how models
//   connect. Eloquent generates the SQL JOIN for you.
//
// JWTSubject interface — required for the JWT package.
//   getJWTIdentifier() returns the user's ID (the "subject" of the token).
//   getJWTCustomClaims() lets you add extra data to the token payload.
// ─────────────────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    // ── Which columns can be set from an array ───────────────
    protected $fillable = [
        'role_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'avatar_url',
        'is_active',
    ];

    // ── Columns never included in API responses ──────────────
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ── Automatic type casting ───────────────────────────────
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',  // Laravel 10+ auto-hashes on set
    ];

    // ── Computed attribute ───────────────────────────────────
    // Accessed as $user->full_name — no column, calculated from two columns
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // ── Relationships ────────────────────────────────────────

    /** User belongs to one Role */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** User has many Orders */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** User has many Wishlist items */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /** User has many Addresses */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    // ── JWT Interface ─────────────────────────────────────────
    // These two methods are REQUIRED by the JWTSubject interface.

    /**
     * The "subject" of the JWT token — typically the user's primary key.
     * This is stored in the 'sub' claim of the token payload.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey(); // returns $this->id
    }

    /**
     * Extra custom claims to include in the JWT payload.
     * Available in every controller via auth()->user().
     * We include role_id so we can check permissions without
     * an extra database query.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role'  => $this->role_id,
            'email' => $this->email,
        ];
    }

    // ── Helper ────────────────────────────────────────────────

    /** Check if this user has a specific role */
    public function hasRole(string $roleName): bool
    {
        return $this->role?->name === $roleName;
    }

    /** Check if this user is an admin */
    public function isAdmin(): bool
    {
        return in_array($this->role?->name, ['admin', 'super_admin']);
    }
}
