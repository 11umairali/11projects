<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbChunk extends Model
{
    use BelongsToWorkspace;

    protected $table = 'kb_chunks';

    protected $fillable = [
        'document_id',
        'workspace_id',
        'content',
        'chunk_index',
        'vector_id',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    // Relationships

    public function document(): BelongsTo
    {
        return $this->belongsTo(KbDocument::class, 'document_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
