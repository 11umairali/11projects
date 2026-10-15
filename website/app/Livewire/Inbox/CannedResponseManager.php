<?php

namespace App\Livewire\Inbox;

use App\Models\CannedResponse;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CannedResponseManager extends Component
{
    use AuthorizesWorkspaceActions;
    public string $title = '';
    public string $shortcut = '';
    public string $content = '';
    public string $scope = 'personal';
    public bool $showForm = false;
    public ?int $editingId = null;

    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'shortcut' => 'nullable|string|max:50|alpha_dash',
            'content' => 'required|string|max:5000',
            'scope' => 'required|in:personal,team',
        ];
    }

    public function save(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $this->validate();
        $user = Auth::user();
        $workspaceId = $user->active_workspace_id;

        $data = [
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'title' => $this->title,
            'shortcut' => $this->shortcut ?: null,
            'content' => $this->content,
            'scope' => $this->scope,
        ];

        if ($this->editingId) {
            CannedResponse::where('id', $this->editingId)
                ->where('workspace_id', $workspaceId)
                ->update($data);
        } else {
            CannedResponse::create($data);
        }

        $this->reset(['title', 'shortcut', 'content', 'scope', 'showForm', 'editingId']);
        $this->dispatch('notify', type: 'success', message: 'Quick reply saved.');
    }

    public function edit(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        $response = CannedResponse::where('id', $id)
            ->where('workspace_id', Auth::user()->active_workspace_id)
            ->firstOrFail();

        $this->editingId = $response->id;
        $this->title = $response->title;
        $this->shortcut = $response->shortcut ?? '';
        $this->content = $response->content;
        $this->scope = $response->scope ?? 'personal';
        $this->showForm = true;
    }

    public function delete(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        CannedResponse::where('id', $id)
            ->where('workspace_id', Auth::user()->active_workspace_id)
            ->delete();

        $this->dispatch('notify', type: 'success', message: 'Quick reply deleted.');
    }

    public function insert(int $id): void
    {
        $response = CannedResponse::where('id', $id)
            ->where('workspace_id', Auth::user()->active_workspace_id)
            ->first();

        if ($response) {
            $response->increment('usage_count');
            $this->dispatch('insert-canned-response', content: $response->content);
        }
    }

    public function render()
    {
        $user = Auth::user();
        $responses = CannedResponse::forWorkspace($user->active_workspace_id, $user->id)
            ->orderBy('usage_count', 'desc')
            ->get();

        return view('livewire.inbox.canned-response-manager', [
            'responses' => $responses,
        ]);
    }
}
