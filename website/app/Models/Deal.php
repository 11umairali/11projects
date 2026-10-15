<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Deal extends Model
{
    use BelongsToWorkspace, HasFactory, SoftDeletes, \Spatie\Activitylog\Traits\LogsActivity;

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logOnly(['title', 'value', 'status', 'deal_stage_id', 'assigned_to'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Deal {$eventName}")
            ->useLogName('deals')
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity): void
    {
        $activity->properties = $activity->properties->put('workspace_id', $this->workspace_id);
    }

    protected $fillable = [
        'workspace_id',
        'contact_id',
        'pipeline_id',
        'deal_stage_id',
        'assigned_to',
        'title',
        'value',
        'currency',
        'expected_close_date',
        'status',
        'won_at',
        'lost_at',
        'lost_reason',
        'custom_fields',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'expected_close_date' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Deal $deal) {
            $deal->uuid = $deal->uuid ?? Str::uuid();
        });

        // Fire workflow trigger when deal stage changes
        static::updating(function (Deal $deal) {
            if ($deal->isDirty('deal_stage_id')) {
                $oldStageId = $deal->getOriginal('deal_stage_id');
                $newStageId = $deal->deal_stage_id;
                try {
                    $oldStage = \App\Models\DealStage::find($oldStageId);
                    $newStage = \App\Models\DealStage::find($newStageId);
                    if ($oldStage && $newStage) {
                        event(new \App\Events\DealStageChanged($deal, $oldStage, $newStage));
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Deal: workflow trigger failed for deal {$deal->id}: {$e->getMessage()}");
                }
            }
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

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function dealStage(): BelongsTo
    {
        return $this->belongsTo(DealStage::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Scopes

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeWon(Builder $query): Builder
    {
        return $query->where('status', 'won');
    }

    public function scopeLost(Builder $query): Builder
    {
        return $query->where('status', 'lost');
    }

    // Helpers

    public function markAsWon(): void
    {
        $this->update([
            'status' => 'won',
            'won_at' => now(),
        ]);
    }

    public function markAsLost(?string $reason = null): void
    {
        $this->update([
            'status' => 'lost',
            'lost_at' => now(),
            'lost_reason' => $reason,
        ]);
    }
}
