<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ContactTrash extends Component
{
    use WithPagination;
    use AuthorizesWorkspaceActions;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function restore(int $id): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $contact = Contact::onlyTrashed()
            ->where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($id);

        $contact->restore();

        session()->flash('success', "Contact \"{$contact->full_name}\" restored.");
    }

    public function forceDelete(int $id): void
    {
        if (! $this->authorizeWorkspaceAction('dangerous')) {
            return;
        }

        $contact = Contact::onlyTrashed()
            ->where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($id);

        $contact->forceDelete();

        session()->flash('success', 'Contact permanently deleted.');
    }

    public function restoreAll(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        $count = Contact::onlyTrashed()
            ->where('workspace_id', $workspaceId)
            ->count();

        Contact::onlyTrashed()
            ->where('workspace_id', $workspaceId)
            ->restore();

        session()->flash('success', "{$count} contact(s) restored.");
    }

    public function emptyTrash(): void
    {
        if (! $this->authorizeWorkspaceAction('dangerous')) {
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        $count = Contact::onlyTrashed()
            ->where('workspace_id', $workspaceId)
            ->count();

        Contact::onlyTrashed()
            ->where('workspace_id', $workspaceId)
            ->forceDelete();

        session()->flash('success', "{$count} contact(s) permanently deleted.");
    }

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $contacts = Contact::onlyTrashed()
            ->where('workspace_id', $workspaceId)
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('deleted_at', 'desc')
            ->paginate(25);

        return view('livewire.contacts.contact-trash', [
            'contacts' => $contacts,
        ]);
    }
}
