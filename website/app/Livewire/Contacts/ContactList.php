<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Models\ContactList as ContactListModel;
use App\Models\Tag;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactList extends Component
{
    use WithPagination;
    use AuthorizesWorkspaceActions;

    #[Url]
    public string $search = '';

    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    #[Url]
    public string $selectedTag = '';

    #[Url]
    public string $selectedGroup = '';

    public array $selectedIds = [];
    public bool $selectAll = false;
    public string $bulkAction = '';
    public ?int $bulkTagId = null;
    public ?int $bulkGroupId = null;
    public bool $showForm = false;
    public bool $showImport = false;
    public ?int $editingContactId = null;

    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-4 animate-pulse">
            <div class="flex justify-between"><div class="h-10 w-48 bg-gray-200 rounded-xl"></div><div class="h-10 w-32 bg-gray-200 rounded-xl"></div></div>
            <div class="bg-gray-200 rounded-2xl h-96"></div>
        </div>
        HTML;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedTag(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedGroup(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowed = ['first_name', 'email', 'company', 'lead_score', 'last_contacted_at', 'created_at'];
        if (!in_array($field, $allowed)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * FIX-099: Renamed logic — selectAll now correctly reflects "current page selected"
     * When toggling ON: selects all contacts on current page
     * When toggling OFF: deselects all
     */
    public function toggleSelectAll(): void
    {
        if (!$this->selectAll) {
            $this->selectedIds = [];
        } else {
            // Select current page IDs only (OOM protection)
            $this->selectedIds = $this->getContactsQuery()
                ->paginate(50)
                ->pluck('id')
                ->toArray();
        }
    }

    public function toggleSelect(int $id): void
    {
        if (in_array($id, $this->selectedIds)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function deleteContact(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        Contact::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $id)
            ->delete();

        $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));
        session()->flash('success', 'Contact deleted.');
    }

    public function deleteSelected(): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;
        if (empty($this->selectedIds) && !$this->selectAll) return;

        $workspaceId = auth()->user()->active_workspace_id;

        if ($this->selectAll) {
            // When selectAll is true, only delete the currently selected IDs (current page),
            // not the entire query result set, to prevent accidental mass deletion.
            if (empty($this->selectedIds)) {
                session()->flash('error', 'No contacts are currently selected. Please select contacts before deleting.');
                return;
            }
        }

        $count = Contact::where('workspace_id', $workspaceId)
            ->whereIn('id', $this->selectedIds)
            ->count();

        Contact::where('workspace_id', $workspaceId)
            ->whereIn('id', $this->selectedIds)
            ->delete();

        $this->selectedIds = [];
        $this->selectAll = false;
        session()->flash('success', "{$count} contact(s) deleted.");
    }

    public function addTagToSelected(int $tagId): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (empty($this->selectedIds) && !$this->selectAll) return;

        $workspaceId = auth()->user()->active_workspace_id;

        // Verify the tag belongs to this workspace
        $tag = Tag::where('workspace_id', $workspaceId)->find($tagId);
        if (!$tag) {
            session()->flash('error', 'Tag not found in this workspace.');
            return;
        }

        // Batch insert pivot rows instead of N+1 syncWithoutDetaching per contact
        if ($this->selectAll) {
            $contactIds = $this->getContactsQuery()->pluck('id');
        } else {
            $contactIds = Contact::where('workspace_id', $workspaceId)
                ->whereIn('id', $this->selectedIds)
                ->pluck('id');
        }

        $pivotData = $contactIds->map(fn ($id) => [
            'contact_id' => $id,
            'tag_id' => $tagId,
        ])->toArray();

        if (!empty($pivotData)) {
            // Capture which contacts already had this tag so we only fire
            // TagAdded for the ones that are newly tagged.
            $alreadyTagged = \Illuminate\Support\Facades\DB::table('contact_tag')
                ->where('tag_id', $tagId)
                ->whereIn('contact_id', $contactIds)
                ->pluck('contact_id')
                ->all();

            \Illuminate\Support\Facades\DB::table('contact_tag')
                ->insertOrIgnore($pivotData);

            $newlyTaggedIds = array_diff($contactIds->all(), $alreadyTagged);
            if (!empty($newlyTaggedIds)) {
                foreach (Contact::whereIn('id', $newlyTaggedIds)->get() as $contact) {
                    try { event(new \App\Events\TagAdded($contact, $tag)); } catch (\Throwable $e) {}
                }
            }
        }

        $count = $contactIds->count();
        $this->selectedIds = [];
        $this->selectAll = false;
        session()->flash('success', "Tag \"{$tag->name}\" added to {$count} contact(s).");
    }

    public function exportCsv(): StreamedResponse
    {
        if (!$this->authorizeWorkspaceAction('manage')) {
            abort(403);
        }

        $workspaceId = auth()->user()->active_workspace_id;
        $selectedIds = $this->selectedIds;
        $sortField = $this->sortField;
        $sortDirection = $this->sortDirection;

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="contacts-' . now()->format('Y-m-d') . '.csv"',
        ];

        return Response::streamDownload(function () use ($workspaceId, $selectedIds, $sortField, $sortDirection) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['First Name', 'Last Name', 'Email', 'Phone', 'Company', 'Job Title', 'City', 'Country', 'Lead Score', 'Tags', 'Status', 'Last Contacted']);

            $query = Contact::where('workspace_id', $workspaceId)->with('tags');

            if (!empty($selectedIds)) {
                $query->whereIn('id', $selectedIds);
            }

            // FIX-102: Limit export to 50,000 contacts to prevent OOM
            $exported = 0;
            $maxExport = 50000;

            $query->orderBy($sortField, $sortDirection)
                ->chunk(500, function ($contacts) use ($handle, &$exported, $maxExport) {
                    if ($exported >= $maxExport) return false;
                    foreach ($contacts as $contact) {
                        fputcsv($handle, [
                            $this->sanitizeCsvValue($contact->first_name),
                            $this->sanitizeCsvValue($contact->last_name),
                            $this->sanitizeCsvValue($contact->email),
                            $this->sanitizeCsvValue($contact->phone),
                            $this->sanitizeCsvValue($contact->company),
                            $this->sanitizeCsvValue($contact->job_title),
                            $this->sanitizeCsvValue($contact->city),
                            $this->sanitizeCsvValue($contact->country),
                            $this->sanitizeCsvValue((string) ($contact->lead_score ?? '')),
                            $this->sanitizeCsvValue($contact->tags->pluck('name')->implode(', ')),
                            $this->sanitizeCsvValue($contact->status ?? ''),
                            $this->sanitizeCsvValue($contact->last_contacted_at?->format('Y-m-d H:i') ?? ''),
                        ]);
                        $exported++;
                        if ($exported >= $maxExport) return;
                    }
                });

            fclose($handle);
        }, 'contacts-' . now()->format('Y-m-d') . '.csv', $headers);
    }

    /**
     * Sanitize a value for CSV export to prevent formula injection.
     * Spreadsheet applications execute formulas starting with =, +, -, @, tab, or CR.
     */
    private function sanitizeCsvValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Execute the selected bulk action against all currently selected contacts.
     * Centralizes delete, tag, export, status changes into a single dispatch point.
     */
    public function executeBulkAction(): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        $workspaceId = auth()->user()->active_workspace_id;
        $ids = array_map('intval', $this->selectedIds);

        if (empty($ids)) {
            session()->flash('error', 'No contacts selected.');
            return;
        }

        $query = Contact::where('workspace_id', $workspaceId)->whereIn('id', $ids);

        match ($this->bulkAction) {
            'delete' => $this->bulkDelete($query),
            'tag' => $this->bulkTag($query, $ids),
            'add_to_group' => $this->bulkAddToGroup($query),
            'remove_from_group' => $this->bulkRemoveFromGroup($query),
            'export' => $this->bulkExport($ids),
            'unsubscribe' => $this->bulkUpdateStatus($query, 'unsubscribed'),
            'activate' => $this->bulkUpdateStatus($query, 'active'),
            default => null,
        };

        $this->selectedIds = [];
        $this->selectAll = false;
        $this->bulkAction = '';
    }

    protected function bulkDelete($query): void
    {
        $count = $query->count();
        $query->delete();
        session()->flash('success', "{$count} contact(s) moved to trash.");
    }

    protected function bulkTag($query, array $ids): void
    {
        if (!$this->bulkTagId) {
            session()->flash('error', 'Please select a tag.');
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;
        $tag = Tag::where('workspace_id', $workspaceId)->find($this->bulkTagId);
        if (!$tag) {
            session()->flash('error', 'Tag not found in this workspace.');
            return;
        }

        $contactIds = $query->pluck('id');
        $pivotData = $contactIds->map(fn ($id) => [
            'contact_id' => $id,
            'tag_id' => $this->bulkTagId,
        ])->toArray();

        if (!empty($pivotData)) {
            $alreadyTagged = \Illuminate\Support\Facades\DB::table('contact_tag')
                ->where('tag_id', $this->bulkTagId)
                ->whereIn('contact_id', $contactIds)
                ->pluck('contact_id')
                ->all();

            \Illuminate\Support\Facades\DB::table('contact_tag')
                ->insertOrIgnore($pivotData);

            $newlyTaggedIds = array_diff($contactIds->all(), $alreadyTagged);
            if (!empty($newlyTaggedIds)) {
                foreach (Contact::whereIn('id', $newlyTaggedIds)->get() as $contact) {
                    try { event(new \App\Events\TagAdded($contact, $tag)); } catch (\Throwable $e) {}
                }
            }
        }

        $count = $contactIds->count();
        session()->flash('success', "Tag \"{$tag->name}\" added to {$count} contact(s).");
        $this->bulkTagId = null;
    }

    protected function bulkAddToGroup($query): void
    {
        if (!$this->bulkGroupId) {
            session()->flash('error', 'Please select a group.');
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;
        $group = ContactListModel::where('workspace_id', $workspaceId)->find($this->bulkGroupId);
        if (!$group) {
            session()->flash('error', 'Group not found in this workspace.');
            return;
        }

        $contactIds = $query->pluck('id');
        $pivotData = $contactIds->map(fn ($id) => [
            'contact_id' => $id,
            'contact_list_id' => $this->bulkGroupId,
            'added_at' => now(),
        ])->toArray();

        if (!empty($pivotData)) {
            \Illuminate\Support\Facades\DB::table('contact_list_members')
                ->insertOrIgnore($pivotData);
        }

        $count = $contactIds->count();
        session()->flash('success', "Added {$count} contact(s) to group \"{$group->name}\".");
        $this->bulkGroupId = null;
    }

    protected function bulkRemoveFromGroup($query): void
    {
        if (!$this->bulkGroupId) {
            session()->flash('error', 'Please select a group.');
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;
        $group = ContactListModel::where('workspace_id', $workspaceId)->find($this->bulkGroupId);
        if (!$group) {
            session()->flash('error', 'Group not found in this workspace.');
            return;
        }

        $contactIds = $query->pluck('id');

        \Illuminate\Support\Facades\DB::table('contact_list_members')
            ->where('contact_list_id', $this->bulkGroupId)
            ->whereIn('contact_id', $contactIds)
            ->delete();

        $count = $contactIds->count();
        session()->flash('success', "Removed {$count} contact(s) from group \"{$group->name}\".");
        $this->bulkGroupId = null;
    }

    protected function bulkExport(array $ids): void
    {
        session()->put('bulk_export_contact_ids', $ids);
        session()->flash('info', 'Export started for ' . count($ids) . ' contacts.');
    }

    protected function bulkUpdateStatus($query, string $status): void
    {
        $count = $query->count();
        $query->update(['status' => $status]);
        $label = $status === 'unsubscribed' ? 'unsubscribed' : 'activated';
        session()->flash('success', "{$count} contact(s) {$label}.");
    }

    public function openForm(?int $contactId = null): void
    {
        $this->editingContactId = $contactId;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingContactId = null;
    }

    public function openImport(): void
    {
        $this->showImport = true;
    }

    public function closeImport(): void
    {
        $this->showImport = false;
    }

    #[On('contact-saved')]
    public function onContactSaved(): void
    {
        $this->closeForm();
        session()->flash('success', 'Contact saved successfully.');
    }

    #[On('import-completed')]
    public function onImportCompleted(): void
    {
        $this->closeImport();
        session()->flash('success', 'Contacts imported successfully.');
    }

    protected function getContactsQuery()
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $query = Contact::where('workspace_id', $workspaceId)
            ->with('tags');

        if ($this->search) {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('company', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        if ($this->selectedTag) {
            $query->whereHas('tags', function ($q) {
                $q->where('tags.id', $this->selectedTag);
            });
        }

        if ($this->selectedGroup) {
            $query->whereHas('lists', fn ($q) => $q->where('contact_lists.id', $this->selectedGroup));
        }

        return $query->orderBy($this->sortField, $this->sortDirection);
    }

    #[Computed]
    public function tags()
    {
        return Tag::where('workspace_id', auth()->user()->active_workspace_id)
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $contacts = $this->getContactsQuery()->paginate(50);
        // Use the paginator's total instead of a separate count query
        $totalContacts = $contacts->total();
        $contactGroups = ContactListModel::where('workspace_id', $workspaceId)->orderBy('name')->get();

        return view('livewire.contacts.contact-list', [
            'contacts' => $contacts,
            'totalContacts' => $totalContacts,
            'contactGroups' => $contactGroups,
        ]);
    }
}
