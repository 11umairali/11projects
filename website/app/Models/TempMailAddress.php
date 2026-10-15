<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TempMailAddress extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'workspace_id', 'user_id', 'temp_mail_domain_id', 'local_part',
        'full_address', 'label', 'is_active', 'messages_count',
        'conversation_id', 'expires_at', 'last_received_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_received_at' => 'datetime',
            'messages_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->uuid = $model->uuid ?? Str::uuid();
        });
    }

    // Relationships
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function domain(): BelongsTo { return $this->belongsTo(TempMailDomain::class, 'temp_mail_domain_id'); }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }

    public function messages()
    {
        return $this->conversation ? $this->conversation->messages()->latest() : collect();
    }

    // Scopes
    public function scopeActive($query) { return $query->where('is_active', true)->where('expires_at', '>', now()); }
    public function scopeExpired($query) { return $query->where('expires_at', '<=', now()); }
    public function scopeForWorkspace($query, int $wsId) { return $query->where('workspace_id', $wsId); }

    // Helpers
    public function isExpired(): bool { return $this->expires_at->isPast(); }
    public function isActiveAndValid(): bool { return $this->is_active && !$this->isExpired(); }

    public function remainingTime(): string
    {
        if ($this->isExpired()) return 'Expired';
        return $this->expires_at->diffForHumans(['parts' => 2, 'short' => true]);
    }

    public function remainingMinutes(): int
    {
        if ($this->isExpired()) return 0;
        return max(0, (int) now()->diffInMinutes($this->expires_at));
    }
}
