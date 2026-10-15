<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Models\ContactList;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ContactListManager extends Component
{
    use AuthorizesWorkspaceActions;

    // Create/Edit form
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $description = '';

    // Add contacts modal
    public bool $showAddContacts = false;
    public ?int $addingToListId = null;
    public string $contactSearch = '';
    public array $selectedContactIds = [];

    // Delete confirmation
    public ?int $confirmDeleteId = null;

    public function createList(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) return;

        $this->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $workspaceId = auth()->user()->active_workspace_id;

        if ($this->editingId) {
            $list = ContactList::where('workspace_id', $workspaceId)->findOrFail($this->editingId);
            $list->update([
                'name' => $this->name,
                'description' => $this->description,
            ]);
            session()->flash('success', "Group \"{$this->name}\" updated.");
        } else {
            ContactList::create([
                'workspace_id' => $workspaceId,
                'name' => $this->name,
                'description' => $this->description,
                'contacts_count' => 0,
            ]);
            session()->flash('success', "Group \"{$this->name}\" created.");
        }

        $this->resetForm();
    }

    public function editList(int $id): void
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $list = ContactList::where('workspace_id', $workspaceId)->findOrFail($id);

        $this->editingId = $id;
        $this->name = $list->name;
        $this->description = $list->description ?? '';
        $this->showForm = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function deleteList(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) return;

        if ($this->confirmDeleteId) {
            $workspaceId = auth()->user()->active_workspace_id;
            $list = ContactList::where('workspace_id', $workspaceId)->findOrFail($this->confirmDeleteId);
            $list->contacts()->detach();
            $list->delete();
            session()->flash('success', "Group \"{$list->name}\" deleted.");
        }

        $this->confirmDeleteId = null;
    }

    public function openAddContacts(int $listId): void
    {
        $this->addingToListId = $listId;
        $this->contactSearch = '';
        $this->selectedContactIds = [];
        $this->showAddContacts = true;
    }

    public function toggleContact(int $contactId): void
    {
        if (in_array($contactId, $this->selectedContactIds)) {
            $this->selectedContactIds = array_values(array_diff($this->selectedContactIds, [$contactId]));
        } else {
            $this->selectedContactIds[] = $contactId;
        }
    }

    public function addSelectedContacts(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) return;
        if (! $this->addingToListId || empty($this->selectedContactIds)) return;

        $workspaceId = auth()->user()->active_workspace_id;
        $list = ContactList::where('workspace_id', $workspaceId)->findOrFail($this->addingToListId);

        $existingIds = $list->contacts()->pluck('contacts.id')->toArray();
        $newIds = array_diff($this->selectedContactIds, $existingIds);

        if (! empty($newIds)) {
            $pivotData = [];
            foreach ($newIds as $contactId) {
                $pivotData[$contactId] = ['added_at' => now()];
            }
            $list->contacts()->attach($pivotData);
            $list->refreshContactsCount();
        }

        $added = count($newIds);
        session()->flash('success', "{$added} contact(s) added to \"{$list->name}\".");
        $this->showAddContacts = false;
        $this->selectedContactIds = [];
    }

    public function removeContact(int $listId, int $contactId): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) return;

        $workspaceId = auth()->user()->active_workspace_id;
        $list = ContactList::where('workspace_id', $workspaceId)->findOrFail($listId);
        $list->contacts()->detach($contactId);
        $list->refreshContactsCount();
    }

    public function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
    }

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $lists = ContactList::where('workspace_id', $workspaceId)
            ->withCount('contacts')
            ->orderBy('name')
            ->get();

        // Contacts for the "add" modal — only search when user types 2+ chars
        $availableContacts = collect();
        if ($this->showAddContacts && strlen($this->contactSearch) >= 2) {
            $term = '%' . $this->contactSearch . '%';
            $availableContacts = Contact::where('workspace_id', $workspaceId)
                ->where('status', 'active')
                ->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('company', 'like', $term);
                })
                ->orderBy('first_name')
                ->limit(20)
                ->get();
        }

        // Contacts in the currently viewed list
        $listContacts = collect();
        if ($this->addingToListId && ! $this->showAddContacts) {
            $list = $lists->firstWhere('id', $this->addingToListId);
            if ($list) {
                $listContacts = $list->contacts()->orderBy('first_name')->limit(100)->get();
            }
        }

        return view('livewire.contacts.contact-list-manager', [
            'lists' => $lists,
            'availableContacts' => $availableContacts,
            'listContacts' => $listContacts,
        ]);
    }
}
