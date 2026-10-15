<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    protected $fillable = [
        'workspace_id',
        'direction',
        'url',
        'method',
        'headers',
        'payload',
        'response_status',
        'response_body',
        'duration_ms',
        'status',
        'attempts',
        'max_attempts',
        'last_attempted_at',
        'next_retry_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'last_attempted_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->whereIn('status', ['failed', 'retrying'])
            ->whereColumn('attempts', '<', 'max_attempts')
            ->where(function (Builder $q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    // ── Status Helpers ─────────────────────────────────────────────

    public function markAsRetrying(): void
    {
        $this->update([
            'status' => 'retrying',
            'last_attempted_at' => now(),
        ]);
    }

    public function markAsSuccess(int $responseStatus, ?string $responseBody, int $durationMs): void
    {
        $this->update([
            'status' => 'success',
            'response_status' => $responseStatus,
            'response_body' => $responseBody,
            'duration_ms' => $durationMs,
            'attempts' => $this->attempts + 1,
            'last_attempted_at' => now(),
            'next_retry_at' => null,
            'error_message' => null,
        ]);
    }

    public function markAsFailed(string $error, ?int $responseStatus = null, ?string $responseBody = null, ?int $durationMs = null): void
    {
        $nextAttempt = $this->attempts + 1;
        $exhausted = $nextAttempt >= $this->max_attempts;

        $this->update([
            'status' => $exhausted ? 'failed' : 'retrying',
            'response_status' => $responseStatus,
            'response_body' => $responseBody,
            'duration_ms' => $durationMs,
            'attempts' => $nextAttempt,
            'last_attempted_at' => now(),
            'next_retry_at' => $exhausted ? null : $this->calculateNextRetry($nextAttempt),
            'error_message' => $error,
        ]);
    }

    /**
     * Exponential backoff: 1 min, 5 min, 15 min.
     */
    private function calculateNextRetry(int $attempt): \Carbon\Carbon
    {
        $delays = [1, 5, 15]; // minutes
        $index = min($attempt - 1, count($delays) - 1);

        return now()->addMinutes($delays[$index]);
    }
}
