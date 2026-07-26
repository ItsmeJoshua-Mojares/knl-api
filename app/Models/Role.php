<?php
// app/Models/Role.php
// ─────────────────────────────────────────────────────────────
// Referenced by:
//   - User::role() relationship  (User.php)
//   - RoleMiddleware             (checks $user->role->name)
//   - AuthService login()        (eager-loads with $user->load('role'))
//   - AdminCustomerController    (whereHas('role', ...))
//   - Database seeder            (Role::firstOrCreate())
//
// The roles table was created in Migration 1 with three rows:
//   id=1  super_admin
//   id=2  admin
//   id=3  customer (default for new registrations)
// ─────────────────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'description'];

    /**
     * All users who have this role.
     * Usage: $role->users()->count()
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ── Convenience constants ─────────────────────────────────
    // Use these instead of magic strings scattered through the code:
    //   if ($user->role->name === Role::SUPER_ADMIN) { ... }
    const SUPER_ADMIN = 'super_admin';
    const ADMIN       = 'admin';
    const CUSTOMER    = 'customer';

    /**
     * Whether this role grants admin panel access.
     */
    public function isAdminRole(): bool
    {
        return in_array($this->name, [self::SUPER_ADMIN, self::ADMIN]);
    }
}
