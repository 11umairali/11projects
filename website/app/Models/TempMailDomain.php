<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TempMailDomain extends Model
{
    protected $fillable = [
        'domain', 'display_name', 'imap_host', 'imap_port', 'imap_username',
        'imap_password', 'imap_encryption', 'status', 'error_message',
        'max_addresses', 'default_lifetime_hours', 'blocked_patterns',
        'allowed_patterns', 'last_synced_at', 'last_synced_uid',
    ];

    protected $hidden = ['imap_username', 'imap_password'];

    protected function casts(): array
    {
        return [
            'imap_host' => 'encrypted',
            'imap_username' => 'encrypted',
            'imap_password' => 'encrypted',
            'blocked_patterns' => 'array',
            'allowed_patterns' => 'array',
            'last_synced_at' => 'datetime',
            'max_addresses' => 'integer',
            'default_lifetime_hours' => 'integer',
            'imap_port' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->uuid = $model->uuid ?? Str::uuid();
        });
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(TempMailAddress::class);
    }

    public function activeAddresses(): HasMany
    {
        return $this->hasMany(TempMailAddress::class)
            ->where('is_active', true)
            ->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getFullAddress(string $localPart): string
    {
        return $localPart . '@' . $this->domain;
    }

    public function isLocalPartBlocked(string $localPart): bool
    {
        $blocked = $this->blocked_patterns ?? [];
        $lower = strtolower($localPart);
        foreach ($blocked as $pattern) {
            if (strtolower($pattern) === $lower) return true;
            if (str_contains($lower, strtolower($pattern))) return true;
        }
        return false;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
