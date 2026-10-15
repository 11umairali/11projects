<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoReplyRule extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'name',
        'keywords',
        'match_type',
        'reply_body',
        'reply_subject',
        'is_active',
        'channel',
        'first_message_only',
        'priority',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'is_active' => 'boolean',
            'first_message_only' => 'boolean',
            'priority' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Check if the given text matches this rule's keywords.
     */
    public function matches(string $text): bool
    {
        $text = mb_strtolower(trim($text));
        $keywords = array_map('mb_strtolower', $this->keywords ?? []);

        if (empty($keywords) || $text === '') {
            return false;
        }

        return match ($this->match_type) {
            'exact' => collect($keywords)->contains(fn (string $kw) => $text === $kw),
            'all'   => collect($keywords)->every(fn (string $kw) => str_contains($text, $kw)),
            default => collect($keywords)->contains(fn (string $kw) => str_contains($text, $kw)),
        };
    }
}
