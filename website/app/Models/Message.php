<?php

namespace App\Models;

use App\Helpers\HtmlSanitizer;
use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Message extends Model
{
    use BelongsToWorkspace, HasFactory, SoftDeletes, \Spatie\Activitylog\Traits\LogsActivity;

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly(['direction', 'subject', 'from_email', 'delivery_status', 'type'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Message {$eventName}")
            ->useLogName('messages')
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity): void
    {
        $activity->properties = $activity->properties->put('workspace_id', $this->workspace_id);
    }

    protected $fillable = [
        'conversation_id',
        'workspace_id',
        'direction',
        'sender_type',
        'sender_id',
        'type',
        'body_html',
        'body_text',
        'subject',
        'from_email',
        'from_name',
        'to_emails',
        'cc_emails',
        'bcc_emails',
        'message_id_header',
        'in_reply_to',
        'references_header',
        'ai_confidence',
        'ai_model',
        'ai_provider',
        'ai_tokens_in',
        'ai_tokens_out',
        'ai_cost',
        'ai_response_time_ms',
        'ai_sources_used',
        'ai_status',
        'sentiment',
        'detected_language',
        'delivery_status',
        'delivery_error',
        'opens_count',
        'clicks_count',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'bounced_at',
        'bounce_type',
        'scheduled_at',
        'schedule_status',
        'channel_message_id',
        'imap_uid',
        'imap_folder',
    ];

    protected function casts(): array
    {
        return [
            'to_emails' => 'array',
            'cc_emails' => 'array',
            'bcc_emails' => 'array',
            'references_header' => 'array',
            'ai_confidence' => 'integer',
            'ai_tokens_in' => 'integer',
            'ai_tokens_out' => 'integer',
            'ai_cost' => 'decimal:5',
            'ai_response_time_ms' => 'integer',
            'ai_sources_used' => 'array',
            'opens_count' => 'integer',
            'clicks_count' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'bounced_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Message $message) {
            $message->uuid = $message->uuid ?? Str::uuid();
        });
    }

    // Relationships

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    // Scopes

    public function scopeAiDrafts(Builder $query): Builder
    {
        return $query->where('type', 'ai_draft');
    }

    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('schedule_status', 'pending')
            ->whereNotNull('scheduled_at');
    }

    public function scopeReadyToSend(Builder $query): Builder
    {
        return $query->where('schedule_status', 'pending')
            ->where('scheduled_at', '<=', now());
    }

    // Helpers

    public function isAiGenerated(): bool
    {
        return $this->sender_type === 'ai';
    }

    public function hasAttachments(): bool
    {
        return $this->attachments()->exists();
    }

    /**
     * Sanitized HTML body safe for rendering in Blade views.
     *
     * Delegates to HtmlSanitizer which uses DOMDocument with a strict
     * whitelist of both tags AND attributes.  This is the only correct
     * approach — regex/strip_tags cannot reliably prevent XSS because
     * strip_tags preserves all attributes on allowed tags (e.g.
     * <a onclick="alert(1)"> passes straight through).
     *
     * The MessageObserver already sanitizes body_html on write, so this
     * accessor is a defence-in-depth measure for any data that predates
     * the observer or was inserted via raw DB queries.
     *
     * Use {!! $message->safe_body_html !!} instead of raw {!! $message->body_html !!}.
     */
    public function getSafeBodyHtmlAttribute(): string
    {
        $html = $this->body_html ?? nl2br(e($this->body_text ?? ''));

        return HtmlSanitizer::sanitize($html);
    }
}
