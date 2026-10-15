<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Admin authentication model for the separate admin guard.
 *
 * This model backs the 'admin' guard defined in config/auth.php and the
 * 'admins' provider (eloquent driver, admins table). It provides a fully
 * isolated authentication context from the regular User model so that:
 *
 *  - Super-admins and support staff log in through a dedicated admin
 *    login route using their own credentials table.
 *  - IP whitelisting and two-factor can be enforced independently of the
 *    tenant-level User settings.
 *  - Admin sessions are scoped to the 'admin' guard, preventing accidental
 *    session sharing with the regular 'web' guard.
 *
 * Note: The User model also carries an `is_admin` flag, which is used to
 * show/hide the admin nav link in the tenant sidebar. That flag does NOT
 * replace this model -- it simply indicates that a particular User may
 * navigate to the admin area, whereas *authentication* within that area
 * relies on this Admin model and the 'admin' guard.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property string      $password
 * @property string      $role                       super_admin | support
 * @property array|null  $ip_whitelist
 * @property bool        $two_factor_enabled
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $remember_token
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Admin extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guard = 'admin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'ip_whitelist',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ip_whitelist' => 'array',
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
        ];
    }

    // Helpers

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isSupport(): bool
    {
        return $this->role === 'support';
    }

    public function canManageAdmins(): bool
    {
        return $this->role === 'super_admin';
    }
}
