<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invite extends Model
{
    use HasFactory;

    /**
     * Eloquent would infer the table as `invites` from the class name,
     * but the schema (see 2026_04_03_000001_create_workspace_invites_table)
     * actually lives in `workspace_invites`. TeamManager writes there via
     * raw DB::table, so the model has to read the same table — otherwise
     * Invite::where('token', ...) silently returns nothing and the
     * accept-flow looks broken.
     */
    protected $table = 'workspace_invites';

    protected $fillable = [
        'workspace_id',
        'invited_by',
        'email',
        'role',
        'status',
        'token',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invite $invite) {
            $invite->token = $invite->token ?? Str::random(64);
            $invite->expires_at = $invite->expires_at ?? now()->addDays(7);
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return is_null($this->accepted_at) && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return is_null($this->accepted_at) && $this->expires_at->isPast();
    }
}
