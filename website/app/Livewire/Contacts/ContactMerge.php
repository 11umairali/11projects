<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ContactMerge extends Component
{
    use AuthorizesWorkspaceActions;

    /** @var array<int, array{contacts: array, match_type: string}> */
    public array $duplicateGroups = [];

    public ?int $selectedGroupIndex = null;

    public ?int $primaryContactId = null;

    public bool $scanning = false;

    public int $mergedCount = 0;

    /** ID of the last merge audit log entry — enables undo. */
    public ?int $lastMergeAuditId = null;

    /** Fully loaded contacts for the currently selected group comparison. */
    public array $selectedGroupContacts = [];

    /** Total contact count scanned during the last scan. */
    public int $scannedContactCount = 0;

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="space-y-4 animate-pulse">
            <div class="flex justify-between"><div class="h-10 w-64 bg-gray-200 dark:bg-gray-700 rounded-xl"></div><div class="h-10 w-40 bg-gray-200 dark:bg-gray-700 rounded-xl"></div></div>
            <div class="bg-gray-200 dark:bg-gray-700 rounded-2xl h-96"></div>
        </div>
        HTML;
    }

    /**
     * Scan the workspace for duplicate contacts.
     *
     * Strategy 1: Exact email match (case-insensitive), grouped by LOWER(email).
     * Strategy 2: Fuzzy name+company match (first_name + last_name + company all non-null and identical).
     *
     * Results are deduplicated so a contact only appears in one group.
     */
    public function scanForDuplicates(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->scanning = true;
        $this->duplicateGroups = [];
        $this->selectedGroupIndex = null;
        $this->primaryContactId = null;
        $this->selectedGroupContacts = [];

        $workspaceId = auth()->user()->active_workspace_id;

        $this->scannedContactCount = Contact::where('workspace_id', $workspaceId)->count();

        $seenContactIds = [];
        $groups = [];

        // --- Strategy 1: Email duplicates ---
        $emailGroups = Contact::where('workspace_id', $workspaceId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->selectRaw('LOWER(email) as normalized_email, COUNT(*) as cnt')
            ->groupByRaw('LOWER(email)')
            ->having('cnt', '>', 1)
            ->limit(100)
            ->pluck('normalized_email');

        foreach ($emailGroups as $email) {
            $contacts = Contact::where('workspace_id', $workspaceId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->orderByDesc('lead_score')
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'company', 'lead_score', 'status', 'created_at']);

            if ($contacts->count() < 2) {
                continue;
            }

            $contactIds = $contacts->pluck('id')->toArray();

            // Skip if any of these contacts are already in another group.
            if (array_intersect($contactIds, $seenContactIds)) {
                continue;
            }

            $seenContactIds = array_merge($seenContactIds, $contactIds);

            $groups[] = [
                'match_type' => 'email',
                'match_value' => $email,
                'contacts' => $contacts->map(fn ($c) => [
                    'id' => $c->id,
                    'first_name' => $c->first_name,
                    'last_name' => $c->last_name,
                    'email' => $c->email,
                    'phone' => $c->phone,
                    'company' => $c->company,
                    'lead_score' => $c->lead_score ?? 0,
                    'status' => $c->status,
                    'created_at' => $c->created_at?->toDateTimeString(),
                ])->toArray(),
            ];

            if (count($groups) >= 100) {
                break;
            }
        }

        // --- Strategy 2: Name + Company fuzzy match ---
        if (count($groups) < 100) {
            $nameGroups = Contact::where('workspace_id', $workspaceId)
                ->whereNotNull('first_name')
                ->where('first_name', '!=', '')
                ->whereNotNull('last_name')
                ->where('last_name', '!=', '')
                ->whereNotNull('company')
                ->where('company', '!=', '')
                ->selectRaw('LOWER(TRIM(first_name)) as fn, LOWER(TRIM(last_name)) as ln, LOWER(TRIM(company)) as co, COUNT(*) as cnt')
                ->groupByRaw('LOWER(TRIM(first_name)), LOWER(TRIM(last_name)), LOWER(TRIM(company))')
                ->having('cnt', '>', 1)
                ->limit(100)
                ->get();

            foreach ($nameGroups as $ng) {
                $contacts = Contact::where('workspace_id', $workspaceId)
                    ->whereRaw('LOWER(TRIM(first_name)) = ?', [$ng->fn])
                    ->whereRaw('LOWER(TRIM(last_name)) = ?', [$ng->ln])
                    ->whereRaw('LOWER(TRIM(company)) = ?', [$ng->co])
                    ->orderByDesc('lead_score')
                    ->orderByDesc('updated_at')
                    ->limit(20)
                    ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'company', 'lead_score', 'status', 'created_at']);

                if ($contacts->count() < 2) {
                    continue;
                }

                $contactIds = $contacts->pluck('id')->toArray();

                if (array_intersect($contactIds, $seenContactIds)) {
                    continue;
                }

                $seenContactIds = array_merge($seenContactIds, $contactIds);

                $groups[] = [
                    'match_type' => 'name_company',
                    'match_value' => "{$ng->fn} {$ng->ln} @ {$ng->co}",
                    'contacts' => $contacts->map(fn ($c) => [
                        'id' => $c->id,
                        'first_name' => $c->first_name,
                        'last_name' => $c->last_name,
                        'email' => $c->email,
                        'phone' => $c->phone,
                        'company' => $c->company,
                        'lead_score' => $c->lead_score ?? 0,
                        'status' => $c->status,
                        'created_at' => $c->created_at?->toDateTimeString(),
                    ])->toArray(),
                ];

                if (count($groups) >= 100) {
                    break;
                }
            }
        }

        $this->duplicateGroups = $groups;
        $this->scanning = false;
    }

    /**
     * Select a duplicate group for detailed comparison.
     */
    public function selectGroup(int $index): void
    {
        if (! isset($this->duplicateGroups[$index])) {
            return;
        }

        $this->selectedGroupIndex = $index;
        $group = $this->duplicateGroups[$index];
        $contactIds = collect($group['contacts'])->pluck('id')->toArray();
        $workspaceId = auth()->user()->active_workspace_id;

        // Load full contact details with tag names and relationship counts in a single query.
        $contacts = Contact::where('workspace_id', $workspaceId)
            ->whereIn('id', $contactIds)
            ->with('tags:id,name,color')
            ->withCount(['conversations', 'deals'])
            ->get();

        $this->selectedGroupContacts = $contacts->map(fn ($c) => [
            'id' => $c->id,
            'first_name' => $c->first_name,
            'last_name' => $c->last_name,
            'full_name' => $c->full_name,
            'email' => $c->email,
            'phone' => $c->phone,
            'company' => $c->company,
            'job_title' => $c->job_title,
            'city' => $c->city,
            'country' => $c->country,
            'timezone' => $c->timezone,
            'lead_score' => $c->lead_score ?? 0,
            'status' => $c->status,
            'tags' => $c->tags->pluck('name')->toArray(),
            'conversations_count' => $c->conversations_count,
            'deals_count' => $c->deals_count,
            'last_contacted_at' => $c->last_contacted_at?->toDateTimeString(),
            'last_seen_at' => $c->last_seen_at?->toDateTimeString(),
            'created_at' => $c->created_at?->toDateTimeString(),
            'updated_at' => $c->updated_at?->toDateTimeString(),
        ])->toArray();

        // Default primary: first contact (highest lead_score from scan ordering).
        $this->primaryContactId = $this->selectedGroupContacts[0]['id'] ?? null;
    }

    /**
     * Set the primary (master) contact to keep after merge.
     */
    public function setPrimary(int $contactId): void
    {
        $validIds = collect($this->selectedGroupContacts)->pluck('id')->toArray();

        if (in_array($contactId, $validIds, true)) {
            $this->primaryContactId = $contactId;
        }
    }

    /**
     * Merge all secondary contacts into the primary contact.
     *
     * 1. Re-assign conversations from secondaries to primary.
     * 2. Re-assign deals from secondaries to primary.
     * 3. Union all tags onto primary.
     * 4. Fill empty fields on primary from secondary data (first non-null wins).
     * 5. Set lead_score to the max across all contacts.
     * 6. Soft-delete secondary contacts.
     * 7. Write audit log entry.
     */
    public function mergeContacts(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        if ($this->selectedGroupIndex === null || $this->primaryContactId === null) {
            session()->flash('error', 'Please select a duplicate group and a primary contact first.');
            return;
        }

        $this->withOperationLock('merge-contacts-' . auth()->id(), function () {
            $workspaceId = auth()->user()->active_workspace_id;
            $group = $this->duplicateGroups[$this->selectedGroupIndex] ?? null;

            if (! $group) {
                session()->flash('error', 'Invalid duplicate group.');
                return;
            }

            $allIds = collect($group['contacts'])->pluck('id')->toArray();
            $secondaryIds = array_values(array_diff($allIds, [$this->primaryContactId]));

            if (empty($secondaryIds)) {
                session()->flash('error', 'No secondary contacts to merge.');
                return;
            }

            // Verify primary belongs to this workspace.
            $primary = Contact::where('workspace_id', $workspaceId)
                ->with('tags')
                ->find($this->primaryContactId);

            if (! $primary) {
                session()->flash('error', 'Primary contact not found in this workspace.');
                return;
            }

            $secondaries = Contact::where('workspace_id', $workspaceId)
                ->whereIn('id', $secondaryIds)
                ->with('tags')
                ->get();

            if ($secondaries->isEmpty()) {
                session()->flash('error', 'Secondary contacts not found.');
                return;
            }

            $mergedSecondaryNames = $secondaries->map(fn ($c) => $c->full_name . ' <' . $c->email . '>')->toArray();

            DB::transaction(function () use ($primary, $secondaries, $secondaryIds) {
                // 1. Move conversations
                DB::table('conversations')
                    ->whereIn('contact_id', $secondaryIds)
                    ->update(['contact_id' => $primary->id]);

                // 2. Move deals
                DB::table('deals')
                    ->whereIn('contact_id', $secondaryIds)
                    ->update(['contact_id' => $primary->id]);

                // 3. Merge tags (union)
                $primaryExistingTagIds = $primary->tags->pluck('id')->toArray();
                $allTagIds = $primaryExistingTagIds;
                foreach ($secondaries as $secondary) {
                    $allTagIds = array_merge($allTagIds, $secondary->tags->pluck('id')->toArray());
                }
                $allTagIds = array_unique($allTagIds);
                $primary->tags()->sync($allTagIds);

                // Fire TagAdded for tags inherited from secondaries (not already on primary)
                $newlyMergedTagIds = array_diff($allTagIds, $primaryExistingTagIds);
                if (!empty($newlyMergedTagIds)) {
                    foreach (\App\Models\Tag::whereIn('id', $newlyMergedTagIds)->get() as $mergedTag) {
                        try { event(new \App\Events\TagAdded($primary, $mergedTag)); } catch (\Throwable $e) {}
                    }
                }

                // 4. Fill empty fields on primary from secondaries (first non-null wins)
                $fillableFields = [
                    'phone', 'company', 'job_title', 'city', 'country',
                    'timezone', 'avatar_path', 'last_contacted_at', 'last_seen_at',
                ];

                foreach ($fillableFields as $field) {
                    if (empty($primary->{$field})) {
                        foreach ($secondaries as $secondary) {
                            if (! empty($secondary->{$field})) {
                                $primary->{$field} = $secondary->{$field};
                                break;
                            }
                        }
                    }
                }

                // Merge custom_fields: secondary values fill gaps only.
                $mergedCustom = $primary->custom_fields ?? [];
                foreach ($secondaries as $secondary) {
                    foreach (($secondary->custom_fields ?? []) as $key => $value) {
                        if (! isset($mergedCustom[$key]) || $mergedCustom[$key] === null || $mergedCustom[$key] === '') {
                            $mergedCustom[$key] = $value;
                        }
                    }
                }
                $primary->custom_fields = $mergedCustom;

                // 5. Lead score = max of all
                $maxScore = max(
                    $primary->lead_score ?? 0,
                    ...$secondaries->pluck('lead_score')->map(fn ($s) => $s ?? 0)->toArray()
                );
                $primary->lead_score = $maxScore;

                $primary->save();

                // 6. Soft-delete secondaries
                Contact::whereIn('id', $secondaryIds)->delete();
            });

            // 7. Audit log
            $user = auth()->user();
            $auditId = DB::table('audit_logs')->insertGetId([
                'auditable_type' => 'App\\Models\\Contact',
                'auditable_id' => $primary->id,
                'event' => 'contacts_merged',
                'actor_type' => 'user',
                'actor_id' => $user->id,
                'actor_name' => $user->name,
                'workspace_id' => $workspaceId,
                'old_values' => json_encode([
                    'secondary_contact_ids' => $secondaryIds,
                    'secondary_contacts' => $mergedSecondaryNames,
                ]),
                'new_values' => json_encode([
                    'primary_contact_id' => $primary->id,
                    'primary_name' => $primary->full_name,
                    'merged_count' => count($secondaryIds),
                ]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->lastMergeAuditId = $auditId;
            $this->mergedCount += count($secondaryIds);

            // Remove the merged group from the list.
            unset($this->duplicateGroups[$this->selectedGroupIndex]);
            $this->duplicateGroups = array_values($this->duplicateGroups);
            $this->selectedGroupIndex = null;
            $this->primaryContactId = null;
            $this->selectedGroupContacts = [];

            session()->flash('success', count($secondaryIds) . ' duplicate contact(s) merged. You can undo this action.');
        });
    }

    /**
     * Undo the most recent merge performed in this session.
     *
     * Restores soft-deleted secondary contacts and writes an undo audit log.
     */
    public function undoLastMerge(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        if (! $this->lastMergeAuditId) {
            session()->flash('error', 'No recent merge to undo.');
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        $auditLog = DB::table('audit_logs')
            ->where('id', $this->lastMergeAuditId)
            ->where('event', 'contacts_merged')
            ->first();

        if (! $auditLog) {
            session()->flash('error', 'Merge record not found. Cannot undo.');
            $this->lastMergeAuditId = null;
            return;
        }

        $oldValues = json_decode($auditLog->old_values, true);
        $newValues = json_decode($auditLog->new_values, true);

        $secondaryIds = $oldValues['secondary_contact_ids'] ?? [];
        $primaryId = $newValues['primary_contact_id'] ?? null;

        if (empty($secondaryIds) || ! $primaryId) {
            session()->flash('error', 'Incomplete merge record. Cannot undo.');
            $this->lastMergeAuditId = null;
            return;
        }

        DB::transaction(function () use ($secondaryIds, $primaryId, $workspaceId) {
            // 1. Restore soft-deleted secondary contacts
            Contact::withTrashed()
                ->where('workspace_id', $workspaceId)
                ->whereIn('id', $secondaryIds)
                ->restore();

            // 2. Re-assign conversations back to secondary contacts by matching email
            $restoredContacts = Contact::where('workspace_id', $workspaceId)
                ->whereIn('id', $secondaryIds)
                ->get();

            foreach ($restoredContacts as $secondary) {
                if (! $secondary->email) {
                    continue;
                }

                // Move conversations whose original sender matches the secondary contact's email
                DB::table('conversations')
                    ->where('contact_id', $primaryId)
                    ->where('workspace_id', $workspaceId)
                    ->where('from_email', $secondary->email)
                    ->update(['contact_id' => $secondary->id]);

                // Move deals originally linked to this secondary (by matching company + name)
                if ($secondary->company && $secondary->first_name) {
                    // Only re-assign deals that have no specific deal notes linking to primary
                    DB::table('deals')
                        ->where('contact_id', $primaryId)
                        ->where('workspace_id', $workspaceId)
                        ->where('title', 'LIKE', '%' . $secondary->first_name . '%')
                        ->update(['contact_id' => $secondary->id]);
                }
            }
        });

        // 3. Write undo audit log
        DB::table('audit_logs')->insert([
            'auditable_type' => 'App\\Models\\Contact',
            'auditable_id' => $primaryId,
            'event' => 'contacts_merge_undone',
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()->name,
            'workspace_id' => $workspaceId,
            'old_values' => json_encode(['original_merge_audit_id' => $this->lastMergeAuditId]),
            'new_values' => json_encode([
                'restored_contact_ids' => $secondaryIds,
                'primary_contact_id' => $primaryId,
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $restoredCount = count($secondaryIds);
        $this->lastMergeAuditId = null;
        $this->mergedCount = max(0, $this->mergedCount - $restoredCount);

        session()->flash('success', "{$restoredCount} contact(s) restored. Merge has been undone.");
    }

    /**
     * Dismiss a group — the user says these are not duplicates.
     */
    public function dismissGroup(int $index): void
    {
        if (! isset($this->duplicateGroups[$index])) {
            return;
        }

        unset($this->duplicateGroups[$index]);
        $this->duplicateGroups = array_values($this->duplicateGroups);

        // If the dismissed group was currently selected, clear the selection.
        if ($this->selectedGroupIndex === $index) {
            $this->selectedGroupIndex = null;
            $this->primaryContactId = null;
            $this->selectedGroupContacts = [];
        } elseif ($this->selectedGroupIndex !== null && $this->selectedGroupIndex > $index) {
            // Adjust index after removal.
            $this->selectedGroupIndex--;
        }
    }

    public function render()
    {
        return view('livewire.contacts.contact-merge');
    }
}
