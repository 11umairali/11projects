<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmailAccount extends Model
{
    use BelongsToWorkspace, HasFactory, SoftDeletes, \Spatie\Activitylog\Traits\LogsActivity;

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly(['email', 'status', 'provider', 'display_name'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Email account {$eventName}")
            ->useLogName('email_accounts')
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity): void
    {
        $activity->properties = $activity->properties->put('workspace_id', $this->workspace_id);
    }

    protected $fillable = [
        'workspace_id',
        'user_id',
        'email',
        'display_name',
        'provider',
        'imap_host',
        'imap_port',
        'imap_username',
        'imap_password',
        'imap_encryption',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'oauth_token',
        'oauth_refresh_token',
        'oauth_token_expires_at',
        'status',
        'error_message',
        'is_default',
        'ai_auto_reply',
        'sync_folders',
        'last_synced_at',
        'last_synced_uid',
    ];

    protected $hidden = [
        'imap_username',
        'imap_password',
        'smtp_username',
        'smtp_password',
        'oauth_token',
        'oauth_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'imap_username' => 'encrypted',
            'imap_password' => 'encrypted',
            'smtp_username' => 'encrypted',
            'smtp_password' => 'encrypted',
            'oauth_token' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_token_expires_at' => 'datetime',
            'imap_port' => 'integer',
            'smtp_port' => 'integer',
            'is_default' => 'boolean',
            'ai_auto_reply' => 'boolean',
            'sync_folders' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailAccount $account) {
            $account->uuid = $account->uuid ?? Str::uuid();
        });
    }

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailSignatures(): HasMany
    {
        return $this->hasMany(EmailSignature::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    // Helpers

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function isOAuth(): bool
    {
        return in_array($this->provider, ['gmail', 'outlook']);
    }
}
