<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Send verification email — skip entirely if SMTP not configured.
     */
    public function sendEmailVerificationNotification(): void
    {
        if ($this->hasVerifiedEmail()) return;
        // Skip if mail is set to log/array or SMTP host is not configured
        $mailer = config('mail.default');
        $smtpHost = config('mail.mailers.smtp.host');

        if (in_array($mailer, ['log', 'array']) || empty($smtpHost) || $smtpHost === 'mailpit' || $smtpHost === '127.0.0.1') {
            $this->markEmailAsVerified();
            return;
        }

        try {
            parent::sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            $this->markEmailAsVerified();
        }
    }

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'recovery_phrase_hash',
        'signup_ip',
        'phone',
        'avatar_path',
        'timezone',
        'locale',
        'language',
        'currency_code',
        'status',
        'referral_code',
        'referred_by',
        'active_workspace_id',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_method',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'recovery_phrase_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'force_password_reset' => 'boolean',
            'suspended_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'notification_preferences' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
        ];
    }

    /**
     * Determine if the user has fully confirmed 2FA (secret set + verified with a valid code).
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled
            && $this->two_factor_secret
            && $this->two_factor_confirmed_at !== null;
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (!$user->isDirty('phone')) return;
            $digits = preg_replace('/\\D+/', '', (string) $user->phone);
            $user->phone = $digits === '' ? null : '+' . $digits;
            if ($user->phone && static::where('phone', $user->phone)->when($user->exists, fn ($q) => $q->whereKeyNot($user->id))->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['phone_national' => 'This mobile number is already registered to another account.']);
            }
        });
        static::creating(function (User $user) {
            $user->uuid = $user->uuid ?? Str::uuid();
            $user->referral_code = $user->referral_code ?? strtoupper(Str::random(8));
        });
    }

    // Relationships

    public function activeWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'active_workspace_id');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role', 'status', 'available_for_assignment')
            ->withTimestamps();
    }

    public function ownedWorkspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role')
            ->wherePivot('role', 'owner');
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function emailAccounts(): HasMany
    {
        return $this->hasMany(EmailAccount::class);
    }

    /** True for accounts created through the @dahimail.com registration flow. */
    public function hasMailbox(): bool
    {
        return $this->username !== null;
    }

    /**
     * Get the user's current plan through their active workspace's subscription.
     *
     * NOTE: We use orderByDesc instead of latestOfMany because Eloquent's
     * latestOfMany() generates a sub-query with a dot-aliased aggregate
     * column (`subscriptions.id_aggregate`) which MariaDB rejects with a
     * 1064 syntax error. Plain orderByDesc on the through table works on
     * both MySQL and MariaDB and HasOneThrough auto-limits to one row.
     */
    public function getUsernameAttribute($value): ?string
    {
        return $value === null ? null : \App\Support\Username::plain($value);
    }

    public function profileUrl(): string
    {
        return $this->username
            ? url('/people/u/' . rawurlencode($this->username))
            : url('/people/' . $this->id);
    }

    public function chatUrl(): string
    {
        return $this->username
            ? url('/friends/chat/' . (ctype_digit($this->username) ? 'u/' : '') . rawurlencode($this->username))
            : url('/friends/chat/user/' . rawurlencode($this->uuid));
    }

    public function currentPlan(): HasOneThrough
    {
        return $this->hasOneThrough(
            Plan::class,
            Subscription::class,
            'workspace_id',       // FK on subscriptions table
            'id',                 // FK on plans table
            'active_workspace_id', // LK on users table
            'plan_id'             // LK on subscriptions table
        )->where('subscriptions.status', 'active')
         ->orderByDesc('subscriptions.id');
    }

    // Helpers

    public function getFirstNameAttribute(): string
    {
        return explode(' ', $this->name)[0];
    }

    public function getInitialsAttribute(): string
    {
        $parts = explode(' ', $this->name);
        $initials = strtoupper(substr($parts[0], 0, 1));
        if (count($parts) > 1) {
            $initials .= strtoupper(substr(end($parts), 0, 1));
        }
        return $initials;
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_admin && $this->admin_role === 'super_admin';
    }

    public function isSupport(): bool
    {
        return $this->is_admin && $this->admin_role === 'support';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasWorkspace(): bool
    {
        return $this->active_workspace_id !== null;
    }

    public function workspaceRole(?int $workspaceId = null): ?string
    {
        $workspaceId = $workspaceId ?? $this->active_workspace_id;
        if (!$workspaceId) return null;

        return $this->workspaces()
            ->where('workspaces.id', $workspaceId)
            ->first()?->pivot?->role;
    }

    public function isWorkspaceOwner(?int $workspaceId = null): bool
    {
        return $this->workspaceRole($workspaceId) === 'owner';
    }

    public function isWorkspaceAdmin(?int $workspaceId = null): bool
    {
        return in_array($this->workspaceRole($workspaceId), ['owner', 'admin']);
    }
}
