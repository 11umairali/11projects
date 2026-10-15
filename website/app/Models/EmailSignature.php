<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSignature extends Model
{
    protected $fillable = [
        'email_account_id',
        'name',
        'content_html',
        'is_default',
        'append_to_new',
        'append_to_replies',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'append_to_new' => 'boolean',
            'append_to_replies' => 'boolean',
        ];
    }

    // Relationships

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }
}
