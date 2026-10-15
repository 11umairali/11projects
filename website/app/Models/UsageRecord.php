<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    protected $fillable = [
        'workspace_id',
        'feature_key',
        'quantity',
        'period',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    // ───────────────────────────────────────────────────
    //  Usage tracking
    // ───────────────────────────────────────────────────

    /**
     * Increment usage for a metered feature within the current billing period.
     *
     * Uses updateOrCreate to ensure a record exists for the workspace + feature +
     * period combination, then atomically increments the quantity.
     */
    public static function incrementUsage(int $workspaceId, string $featureKey, int $amount = 1): void
    {
        $period = now()->format('Y-m');

        self::updateOrCreate(
            ['workspace_id' => $workspaceId, 'feature_key' => $featureKey, 'period' => $period],
            []
        )->increment('quantity', $amount);
    }

    /**
     * Decrement usage for a metered feature within the current billing period.
     *
     * Only meaningful for metered features tracked via usage_records (not
     * resource-based counts like contacts/workflows, which use live DB counts).
     */
    public static function decrementUsage(int $workspaceId, string $featureKey, int $amount = 1): void
    {
        $period = now()->format('Y-m');

        $record = self::where('workspace_id', $workspaceId)
            ->where('feature_key', $featureKey)
            ->where('period', $period)
            ->first();

        if ($record && $record->quantity > 0) {
            $record->decrement('quantity', min($amount, $record->quantity));
        }
    }

    // ───────────────────────────────────────────────────
    //  Relationships
    // ───────────────────────────────────────────────────

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
