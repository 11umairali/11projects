<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'created_by',
        'email_account_id',
        // SMS support: which channel (email|sms), the Twilio "from" number,
        // and the plain-text body. For email campaigns these are NULL.
        'channel',
        'from_number',
        'body_text',
        'name',
        'type',
        'status',
        'subject',
        'body_html',
        'body_json',
        'preview_text',
        'audience_type',
        'audience_id',
        'audience_meta',
        'emails_per_minute',
        'batch_size',
        'batch_delay_seconds',
        'recipients_count',
        'sent_count',
        'delivered_count',
        'opened_count',
        'clicked_count',
        'bounced_count',
        'unsubscribed_count',
        'scheduled_at',
        'sent_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'body_json' => 'array',
            'audience_meta' => 'array',
            'emails_per_minute' => 'integer',
            'batch_size' => 'integer',
            'batch_delay_seconds' => 'integer',
            'recipients_count' => 'integer',
            'sent_count' => 'integer',
            'delivered_count' => 'integer',
            'opened_count' => 'integer',
            'clicked_count' => 'integer',
            'bounced_count' => 'integer',
            'unsubscribed_count' => 'integer',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign) {
            $campaign->uuid = $campaign->uuid ?? Str::uuid();
        });
    }

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function campaignLinks(): HasMany
    {
        return $this->hasMany(CampaignLink::class);
    }

    public function abTestVariants(): HasMany
    {
        return $this->hasMany(AbTestVariant::class);
    }

    public function dripSequences(): HasMany
    {
        return $this->hasMany(DripSequence::class);
    }

    // Scopes

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    // Helpers

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function getOpenRateAttribute(): float
    {
        return $this->delivered_count > 0
            ? round(($this->opened_count / $this->delivered_count) * 100, 1)
            : 0;
    }

    public function getClickRateAttribute(): float
    {
        return $this->delivered_count > 0
            ? round(($this->clicked_count / $this->delivered_count) * 100, 1)
            : 0;
    }
}
