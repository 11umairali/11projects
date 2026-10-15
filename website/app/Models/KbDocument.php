<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KbDocument extends Model
{
    use BelongsToWorkspace, SoftDeletes;

    protected $table = 'kb_documents';

    protected $fillable = [
        'workspace_id',
        'type',
        'title',
        'content',
        'content_hash',
        'question',
        'answer',
        'category',
        'is_priority',
        'file_path',
        'file_type',
        'file_size',
        'source_url',
        'status',
        'error_message',
        'chunks_count',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'is_priority' => 'boolean',
            'file_size' => 'integer',
            'chunks_count' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (KbDocument $document) {
            $document->uuid = $document->uuid ?? Str::uuid();
        });
    }

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function kbChunks(): HasMany
    {
        return $this->hasMany(KbChunk::class, 'document_id');
    }

    // Scopes

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', 'ready');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // Helpers

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isQa(): bool
    {
        return $this->type === 'qa';
    }
}
