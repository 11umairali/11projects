<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowNode extends Model
{
    use HasFactory;
    protected $fillable = [
        'workflow_id',
        'type',
        'subtype',
        'config',
        'position_x',
        'position_y',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'position_x' => 'float',
            'position_y' => 'float',
        ];
    }

    // Relationships

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
