<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Segment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'name',
        'rules',
        'is_dynamic',
        'contacts_count',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_dynamic' => 'boolean',
            'contacts_count' => 'integer',
        ];
    }

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
