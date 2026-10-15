<?php

namespace App\Livewire\Settings;

use App\Models\AutoReplyRule;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Component;

class AutoReplyRules extends Component
{
    use AuthorizesWorkspaceActions;

    public bool $showForm = false;
    public ?int $editingId = null;

    // Form fields
    public string $name = '';
    public string $keywordsInput = '';
    public string $matchType = 'any';
    public string $replyBody = '';
    public string $replySubject = '';
    public bool $isActive = true;
    public string $channel = 'all';
    public bool $firstMessageOnly = true;
    public int $priority = 0;

    public function openForm(?int $id = null): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        if ($id) {
            $rule = AutoReplyRule::where('workspace_id', auth()->user()->active_workspace_id)
                ->findOrFail($id);

            $this->editingId = $rule->id;
            $this->name = $rule->name;
            $this->keywordsInput = implode(', ', $rule->keywords ?? []);
            $this->matchType = $rule->match_type;
            $this->replyBody = $rule->reply_body;
            $this->replySubject = $rule->reply_subject ?? '';
            $this->isActive = $rule->is_active;
            $this->channel = $rule->channel;
            $this->firstMessageOnly = $rule->first_message_only;
            $this->priority = $rule->priority;
        } else {
            $this->resetFormFields();
        }

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function save(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->validate([
            'name'          => 'required|string|max:255',
            'keywordsInput' => 'required|string',
            'matchType'     => 'required|in:any,all,exact',
            'replyBody'     => 'required|string',
            'replySubject'  => 'nullable|string|max:255',
            'channel'       => 'required|in:all,email,whatsapp,sms,chat,telegram',
            'priority'      => 'required|integer|min:0|max:999',
        ]);

        $keywords = array_values(array_filter(array_map('trim', explode(',', $this->keywordsInput))));

        if (empty($keywords)) {
            $this->addError('keywordsInput', 'At least one keyword is required.');
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        $data = [
            'workspace_id'     => $workspaceId,
            'name'             => $this->name,
            'keywords'         => $keywords,
            'match_type'       => $this->matchType,
            'reply_body'       => $this->replyBody,
            'reply_subject'    => $this->replySubject ?: null,
            'is_active'        => $this->isActive,
            'channel'          => $this->channel,
            'first_message_only' => $this->firstMessageOnly,
            'priority'         => $this->priority,
        ];

        if ($this->editingId) {
            AutoReplyRule::where('workspace_id', $workspaceId)
                ->where('id', $this->editingId)
                ->update($data);
            session()->flash('success', 'Auto-reply rule updated.');
        } else {
            AutoReplyRule::create($data);
            session()->flash('success', 'Auto-reply rule created.');
        }

        $this->closeForm();
    }

    public function toggleActive(int $id): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $rule = AutoReplyRule::where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($id);
        $rule->update(['is_active' => ! $rule->is_active]);
    }

    public function delete(int $id): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        AutoReplyRule::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $id)
            ->delete();

        session()->flash('success', 'Auto-reply rule deleted.');
    }

    public string $testInput = '';
    public string $testResult = '';

    /**
     * Test which rule would match a given input message.
     */
    public function testRules(): void
    {
        if (empty(trim($this->testInput))) {
            $this->testResult = 'Please enter a test message.';
            return;
        }

        $rules = AutoReplyRule::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        $input = mb_strtolower(trim($this->testInput));

        foreach ($rules as $rule) {
            $keywords = $rule->keywords ?? [];
            $matched = match ($rule->match_type) {
                'any' => collect($keywords)->contains(fn ($kw) => str_contains($input, mb_strtolower($kw))),
                'all' => collect($keywords)->every(fn ($kw) => str_contains($input, mb_strtolower($kw))),
                'exact' => collect($keywords)->contains(fn ($kw) => $input === mb_strtolower($kw)),
                default => false,
            };

            if ($matched) {
                $this->testResult = "Matched rule: \"{$rule->name}\" (priority {$rule->priority}). Reply: " . \Illuminate\Support\Str::limit($rule->reply_body, 100);
                return;
            }
        }

        $this->testResult = 'No rules matched this message. The AI auto-reply would handle it instead.';
    }

    public function render()
    {
        $rules = AutoReplyRule::where('workspace_id', auth()->user()->active_workspace_id)
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        return view('livewire.settings.auto-reply-rules', ['rules' => $rules]);
    }

    protected function resetFormFields(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->keywordsInput = '';
        $this->matchType = 'any';
        $this->replyBody = '';
        $this->replySubject = '';
        $this->isActive = true;
        $this->channel = 'all';
        $this->firstMessageOnly = true;
        $this->priority = 0;
    }
}
