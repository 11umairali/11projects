<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactList extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'contacts_count',
    ];

    protected function casts(): array
    {
        return [
            'contacts_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_list_members')
            ->withPivot('added_at');
    }

    /**
     * Recalculate and update the cached contacts_count.
     */
    public function refreshContactsCount(): void
    {
        $this->update(['contacts_count' => $this->contacts()->count()]);
    }
}
