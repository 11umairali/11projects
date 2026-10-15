<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use BelongsToWorkspace, HasFactory, SoftDeletes, \Spatie\Activitylog\Traits\LogsActivity;

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly(['status', 'priority', 'assigned_to', 'is_starred', 'subject'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Conversation {$eventName}")
            ->useLogName('conversations')
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity): void
    {
        $activity->properties = $activity->properties->put('workspace_id', $this->workspace_id);
    }

    protected $fillable = [
        'workspace_id',
        'contact_id',
        'email_account_id',
        'assigned_to',
        'channel',
        'status',
        'priority',
        'subject',
        'preview',
        'sentiment',
        'is_starred',
        'is_pinned',
        'is_read',
        'is_ai_handled',
        'messages_count',
        'ai_replies_count',
        'tags',
        'snoozed_until',
        'last_message_at',
        'first_response_at',
        'resolved_at',
        'channel_conversation_id',
    ];

    protected function casts(): array
    {
        return [
            'is_starred' => 'boolean',
            'is_pinned' => 'boolean',
            'is_read' => 'boolean',
            'is_ai_handled' => 'boolean',
            'messages_count' => 'integer',
            'ai_replies_count' => 'integer',
            'tags' => 'array',
            'snoozed_until' => 'datetime',
            'last_message_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            $conversation->uuid = $conversation->uuid ?? Str::uuid();
        });
    }

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function tagModels(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'conversation_tag');
    }

    // Scopes

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }
}
