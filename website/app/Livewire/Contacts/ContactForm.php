<?php

namespace App\Livewire\Contacts;

use App\Exceptions\PlanLimitReachedException;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Tag;
use App\Models\Workspace;
use App\Services\PlanLimitService;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ContactForm extends Component
{
    use AuthorizesWorkspaceActions;

    public ?int $contactId = null;

    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $phone = '';
    public string $company = '';
    public string $job_title = '';
    public string $city = '';
    public string $country = '';
    public string $timezone = '';
    public array $selectedTags = [];
    public array $customFieldValues = [];

    public function mount(?int $contactId = null): void
    {
        // Authorization: create for new, interact for edit
        if ($contactId) {
            if (!$this->authorizeWorkspaceAction('interact')) return;
        } else {
            if (!$this->authorizeWorkspaceAction('create')) return;
        }

        $this->contactId = $contactId;

        // Initialize custom field values
        $customFields = CustomField::where('workspace_id', auth()->user()->active_workspace_id)
            ->orderBy('sort_order')
            ->get();

        foreach ($customFields as $field) {
            $this->customFieldValues[$field->key] = '';
        }

        if ($contactId) {
            $contact = Contact::where('workspace_id', auth()->user()->active_workspace_id)
                ->with('tags')
                ->findOrFail($contactId);

            $this->first_name = $contact->first_name ?? '';
            $this->last_name = $contact->last_name ?? '';
            $this->email = $contact->email ?? '';
            $this->phone = $contact->phone ?? '';
            $this->company = $contact->company ?? '';
            $this->job_title = $contact->job_title ?? '';
            $this->city = $contact->city ?? '';
            $this->country = $contact->country ?? '';
            $this->timezone = $contact->timezone ?? '';
            $this->selectedTags = $contact->tags->pluck('id')->toArray();

            // Load custom field values
            $savedFields = $contact->custom_fields ?? [];
            foreach ($customFields as $field) {
                $this->customFieldValues[$field->key] = $savedFields[$field->key] ?? '';
            }
        }
    }

    public function rules(): array
    {
        $wsId = auth()->user()->active_workspace_id;

        $rules = [
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'company' => 'nullable|string|max:150',
            'job_title' => 'nullable|string|max:150',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:50',
            'selectedTags' => 'array',
            // Scope tag validation by workspace
            'selectedTags.*' => ['integer', Rule::exists('tags', 'id')->where('workspace_id', $wsId)],
        ];

        // Add custom field validation
        $customFields = CustomField::where('workspace_id', $wsId)->get();
        foreach ($customFields as $field) {
            $fieldRules = [];
            if ($field->required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            $fieldRules[] = match ($field->type) {
                'number' => 'numeric',
                'date' => 'date',
                'email' => 'email',
                'url' => 'url',
                'checkbox' => 'boolean',
                default => 'string|max:500',
            };

            $rules["customFieldValues.{$field->key}"] = implode('|', $fieldRules);
        }

        return $rules;
    }

    public function save(): void
    {
        // Authorization: create for new, interact for edit
        if ($this->contactId) {
            if (!$this->authorizeWorkspaceAction('interact')) return;
        } else {
            if (!$this->authorizeWorkspaceAction('create')) return;
        }

        $validated = $this->validate();
        $workspaceId = auth()->user()->active_workspace_id;

        // Enforce plan limit when creating a new contact
        if (! $this->contactId) {
            try {
                $workspace = Workspace::findOrFail($workspaceId);
                app(PlanLimitService::class)->assertCanCreate($workspace, 'contacts');
            } catch (PlanLimitReachedException $e) {
                session()->flash('error', $e->getMessage());
                return;
            }
        }

        // Duplicate email check (workspace-scoped)
        $emailExists = Contact::where('workspace_id', $workspaceId)
            ->where('email', $this->email)
            ->when($this->contactId, fn ($q) => $q->where('id', '!=', $this->contactId))
            ->exists();

        if ($emailExists) {
            $this->addError('email', 'A contact with this email already exists in this workspace.');
            return;
        }

        $data = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'city' => $this->city,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'custom_fields' => array_filter($this->customFieldValues, fn($v) => $v !== '' && $v !== null),
        ];

        if ($this->contactId) {
            $contact = Contact::where('workspace_id', $workspaceId)
                ->findOrFail($this->contactId);
            $contact->update($data);
            session()->flash('success', 'Contact updated.');
        } else {
            $contact = Contact::create(array_merge($data, [
                'workspace_id' => $workspaceId,
                'status' => 'active',
            ]));
            session()->flash('success', 'Contact created.');
        }

        // Sync tags (already validated as workspace-scoped via rules)
        $validTags = Tag::where('workspace_id', $workspaceId)
            ->whereIn('id', $this->selectedTags)
            ->pluck('id')
            ->toArray();
        $existingTagIds = $contact->tags()->pluck('tags.id')->all();
        $contact->tags()->sync($validTags);

        // Fire TagAdded only for newly-attached tags
        $newlyAddedTagIds = array_diff($validTags, $existingTagIds);
        if (!empty($newlyAddedTagIds)) {
            foreach (Tag::whereIn('id', $newlyAddedTagIds)->get() as $addedTag) {
                try { event(new \App\Events\TagAdded($contact, $addedTag)); } catch (\Throwable $e) {}
            }
        }

        $this->dispatch('contact-saved');
    }

    public function close(): void
    {
        $this->dispatch('contact-saved');
    }

    #[Computed]
    public function tags()
    {
        return Tag::where('workspace_id', auth()->user()->active_workspace_id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function customFields()
    {
        return CustomField::where('workspace_id', auth()->user()->active_workspace_id)
            ->orderBy('sort_order')
            ->get();
    }

    public function render()
    {
        return view('livewire.contacts.contact-form');
    }
}
