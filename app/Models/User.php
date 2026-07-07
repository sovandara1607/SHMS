<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Staff login account. Authenticates against the `password_hash` column.
 * The `role` column drives all RBAC. `super_admin`/`admin` are protected
 * wildcard roles (config/permissions.php); every other role's grants live in
 * the `role_permissions` table, editable via the Roles & Permissions screen.
 */
class User extends Authenticatable
{
    use HasBusinessKey, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';
    public string $idPrefix = 'USR';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'staff_id', 'email', 'password_hash', 'role', 'status',
    ];

    protected $hidden = ['password_hash', 'remember_token'];

    /** Laravel auth reads the hash from here instead of a `password` column. */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function displayName(): string
    {
        return $this->staff
            ? trim($this->staff->first_name . ' ' . $this->staff->last_name)
            : $this->email;
    }

    /**
     * Permissions granted to this user's role. super_admin/admin are
     * protected wildcards (config/permissions.php); other roles are
     * editable and stored in the role_permissions table, always topped up
     * with the baseline capabilities every logged-in role needs.
     */
    public function permissions(): array
    {
        $protected = config("permissions.permissions.{$this->role}", []);
        if (in_array('*', $protected, true)) {
            return ['*'];
        }

        $baseline = ['dashboard.view', 'profile.view', 'profile.update'];
        $stored = RolePermission::where('role', $this->role)->pluck('capability')->all();

        return array_values(array_unique(array_merge($baseline, $stored)));
    }

    /** Does the role grant a capability? '*' (admin) grants everything. */
    public function hasPermission(string $permission): bool
    {
        $grants = $this->permissions();
        return in_array('*', $grants, true) || in_array($permission, $grants, true);
    }
}
