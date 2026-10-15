<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contact extends Model
{
    use BelongsToWorkspace, HasFactory, SoftDeletes, \Spatie\Activitylog\Traits\LogsActivity;

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'email', 'phone', 'company', 'status'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Contact {$eventName}")
            ->useLogName('contacts')
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity): void
    {
        $activity->properties = $activity->properties->put('workspace_id', $this->workspace_id);
    }

    protected $fillable = [
        'workspace_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'job_title',
        'avatar_path',
        'city',
        'country',
        'timezone',
        'lead_score',
        'custom_fields',
        'status',
        'unsubscribed_at',
        'unsubscribe_reason',
        'last_contacted_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'lead_score' => 'integer',
            'unsubscribed_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Contact $contact) {
            $contact->uuid = $contact->uuid ?? Str::uuid();
        });

        // Fire workflow trigger when contact is created
        static::created(function (Contact $contact) {
            try {
                event(new \App\Events\ContactCreated($contact));
            } catch (\Throwable $e) {
                \Log::warning("Contact: workflow trigger failed for contact {$contact->id}: {$e->getMessage()}");
            }
        });

        // Fire workflow trigger when fields on an existing contact change.
        // `updated` (not `updating`) so we have the persisted state AND an
        // accurate list of changed columns via getChanges().
        static::updated(function (Contact $contact) {
            $changed = array_keys($contact->getChanges());
            // Skip trivial housekeeping-only updates so every observer's
            // touch() doesn't retrigger workflows.
            $changed = array_values(array_filter($changed, fn ($c) => $c !== 'updated_at'));
            if (empty($changed)) return;

            try {
                event(new \App\Events\ContactUpdated($contact, $changed));
            } catch (\Throwable $e) {
                \Log::warning("Contact: update trigger failed for contact {$contact->id}: {$e->getMessage()}");
            }
        });

        // MN-009: Set exit_reason on active drip enrollments before cascade delete
        static::deleting(function (Contact $contact) {
            $contact->dripEnrollments()
                ->where('status', 'active')
                ->update(['status' => 'exited', 'exit_reason' => 'contact_deleted', 'exited_at' => now()]);
        });
    }

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messages(): HasManyThrough
    {
        return $this->hasManyThrough(
            Message::class,
            Conversation::class,
            'contact_id',      // FK on conversations table
            'conversation_id', // FK on messages table
            'id',              // LK on contacts table
            'id'               // LK on conversations table
        );
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'contact_tag');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function dripEnrollments(): HasMany
    {
        return $this->hasMany(DripEnrollment::class);
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_members')
            ->withPivot('added_at');
    }

    // Scopes

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->whereFullText(
            ['first_name', 'last_name', 'email', 'company'],
            $term
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // Accessors

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getInitialsAttribute(): string
    {
        $initials = strtoupper(substr($this->first_name ?? '', 0, 1));
        if ($this->last_name) {
            $initials .= strtoupper(substr($this->last_name, 0, 1));
        }
        return $initials ?: '??';
    }
}
