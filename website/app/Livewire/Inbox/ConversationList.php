<?php

namespace App\Livewire\Inbox;

use App\Models\Conversation;
use App\Models\Tag;
use App\Models\User;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ConversationList extends Component
{
    use AuthorizesWorkspaceActions;

    /** Maximum conversations to keep in memory during infinite scroll. */
    private const MAX_LOADED_CONVERSATIONS = 200;

    public string $channel = 'all';
    public string $folder = 'inbox';
    public string $search = '';
    public string $sortBy = 'newest';
    public ?int $selectedConversationId = null;
    public ?string $filterTag = null;
    public ?int $filterAssignee = null;
    public ?int $filterAccountId = null;

    /** @var array<int> */
    public array $selectedIds = [];
    public bool $selectAll = false;

    public int $perPage = 25;
    public int $page = 1;
    public bool $hasMorePages = false;

    /** @var array<array> Accumulated conversation data for infinite scroll */
    public array $loadedConversations = [];

    public function placeholder()
    {
        $item = <<<'ITEM'
                <div class="px-4 py-3 border-b border-border/50 border-l-[3px] border-l-transparent">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full bg-border shrink-0"></div>
                        <div class="flex-1 min-w-0 space-y-1.5">
                            <div class="flex items-center justify-between">
                                <div class="h-3.5 bg-border rounded w-28"></div>
                                <div class="h-3 bg-border/50 rounded w-10"></div>
                            </div>
                            <div class="h-3.5 bg-border rounded w-3/4"></div>
                            <div class="h-3 bg-border/50 rounded w-full"></div>
                            <div class="flex items-center gap-1.5">
                                <div class="h-2.5 w-2.5 bg-border rounded-full"></div>
                                <div class="h-3 bg-border/50 rounded-full w-12"></div>
                            </div>
                        </div>
                    </div>
                </div>
ITEM;

        $items = str_repeat($item, 8);

        return <<<HTML
        <div class="w-full md:w-[320px] xl:w-[340px] 2xl:w-[360px] shrink-0 border-r border-border flex flex-col bg-surface-2 min-w-0 animate-pulse">
            <!-- Top bar skeleton -->
            <div class="px-3 py-2.5 border-b border-border space-y-2 shrink-0">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="h-4 w-12 bg-border rounded"></div>
                        <div class="h-4 w-6 bg-brand/10 rounded-full"></div>
                    </div>
                    <div class="h-7 w-14 bg-border rounded-md"></div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex-1 h-8 bg-border/50 rounded-md"></div>
                    <div class="h-8 w-16 bg-border/50 rounded-md"></div>
                    <div class="h-8 w-8 bg-border/50 rounded-md"></div>
                </div>
            </div>

            <!-- Conversation item skeletons -->
            <div class="flex-1 overflow-hidden">
                {$items}
            </div>
        </div>
        HTML;
    }

    public function mount(): void
    {
        $this->loadConversations();

        // Auto-select conversation from URL query parameter (e.g. /inbox?cid=123)
        $conversationId = request()->query('cid');
        if ($conversationId) {
            $this->selectConversation((int) $conversationId);
        }
    }

    #[Computed]
    public function hasEmailAccounts(): bool
    {
        return \App\Models\EmailAccount::where('workspace_id', auth()->user()->active_workspace_id)->exists();
    }

    #[Computed]
    public function isSyncing(): bool
    {
        // Show "syncing" only if there's an account that has NEVER synced, was created
        // recently, and has no error. Accounts with errors should not show as syncing.
        return \App\Models\EmailAccount::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('status', 'connected')
            ->whereNull('last_synced_at')
            ->whereNull('error_message')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();
    }

    #[On('trigger-sync')]
    public function triggerSync(): void
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $accounts = \App\Models\EmailAccount::where('workspace_id', $workspaceId)
            ->where('status', 'connected')
            ->get();

        foreach ($accounts as $account) {
            \App\Jobs\SyncEmailAccountJob::dispatch($account);
        }

        // Reload conversations
        $this->page = 1;
        $this->loadedConversations = [];
        $this->loadConversations();

        session()->flash('success', 'Sync started for ' . $accounts->count() . ' account(s).');
    }

    #[On('sidebar-filter-changed')]
    public function onSidebarFilterChanged(string $channel, string $folder, ?string $tag, ?int $assignee, ?int $accountId = null): void
    {
        $this->channel = $channel;
        $this->folder = $folder;
        $this->filterTag = $tag;
        $this->filterAssignee = $assignee;
        $this->filterAccountId = $accountId;
        $this->page = 1;
        $this->loadedConversations = [];
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->loadConversations();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->loadedConversations = [];
        $this->loadConversations();
    }

    public function updatedSortBy(): void
    {
        $this->page = 1;
        $this->loadedConversations = [];
        $this->loadConversations();
    }

    public function loadMore(): void
    {
        if (!$this->hasMorePages) {
            return;
        }

        $this->page++;
        $this->loadConversations(append: true);
    }

    /**
     * Build the base query for conversations (used for selectAll bulk operations).
     */
    protected function getBaseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $query = Conversation::where('workspace_id', $workspaceId);

        if ($this->channel !== 'all') {
            if ($this->channel === 'chat') {
                $query->whereIn('channel', ['chat', 'live_chat']);
            } else {
                $query->where('channel', $this->channel);
            }
        }

        match ($this->folder) {
            'inbox' => $query->where('status', 'open'),
            'sent' => $query->whereHas('messages', fn ($q) => $q->where('direction', 'outbound'))->where('status', 'closed'),
            'starred' => $query->where('is_starred', true),
            'snoozed' => $query->where('status', 'snoozed'),
            'spam' => $query->where('status', 'spam'),
            'trash' => $query->onlyTrashed(),
            'archive' => $query->where('status', 'closed'),
            default => null,
        };

        if ($this->filterAccountId) {
            $query->where('email_account_id', $this->filterAccountId);
        }

        if ($this->filterTag) {
            $query->whereHas('tagModels', fn ($q) => $q->where('name', $this->filterTag));
        }

        if ($this->filterAssignee) {
            $query->where('assigned_to', $this->filterAssignee);
        }

        if ($this->search) {
            $escapedSearch = addcslashes($this->search, '%_');
            $term = '%' . $escapedSearch . '%';
            $searchLongEnough = mb_strlen($this->search) >= 3;
            $query->where(function ($q) use ($term, $searchLongEnough) {
                $q->where('subject', 'like', $term)
                    ->orWhere('preview', 'like', $term)
                    ->orWhereHas('contact', function ($cq) use ($term) {
                        $cq->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
                // Also search message body for longer queries (expensive but scoped within the WHERE group)
                if ($searchLongEnough) {
                    $q->orWhereExists(function ($sub) use ($term) {
                        $sub->selectRaw('1')
                            ->from('messages')
                            ->whereColumn('messages.conversation_id', 'conversations.id')
                            ->where('messages.body_text', 'like', $term)
                            ->limit(1);
                    });
                }
            });
        }

        return $query;
    }

    protected function loadConversations(bool $append = false, bool $refreshWindow = false): void
    {
        $workspaceId = auth()->user()->active_workspace_id;
        if (!$workspaceId) {
            $this->loadedConversations = [];
            return;
        }

        $query = $this->getBaseQuery()
            ->with(['contact', 'assignedTo', 'tagModels', 'emailAccount']);

        // Sorting
        match ($this->sortBy) {
            'newest' => $query->orderByDesc('last_message_at')->orderByDesc('created_at'),
            'oldest' => $query->orderBy('last_message_at')->orderBy('created_at'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")->orderByDesc('last_message_at'),
            'unread' => $query->orderByDesc('is_read')->orderByDesc('last_message_at'),
            default => $query->orderByDesc('last_message_at'),
        };

        $offset = $refreshWindow ? 0 : ($this->page - 1) * $this->perPage;
        $limit = $refreshWindow ? min(self::MAX_LOADED_CONVERSATIONS, $this->page * $this->perPage) : $this->perPage;
        $conversations = $query->skip($offset)->take($limit + 1)->get();
        $this->hasMorePages = $conversations->count() > $limit;
        $conversations = $conversations->take($limit);

        $mapped = $conversations->map(function (Conversation $conv) {
            return [
                'id' => $conv->id,
                'contact_name' => $conv->contact?->full_name ?? 'Unknown',
                'contact_email' => $conv->contact?->email ?? '',
                'contact_initials' => $conv->contact?->initials ?? '??',
                'contact_avatar' => $conv->contact?->avatar_path,
                'subject' => $conv->subject ?? '(no subject)',
                'preview' => \Illuminate\Support\Str::limit($conv->preview ?? '', 120),
                'time' => $conv->last_message_at?->diffForHumans(short: true) ?? $conv->created_at?->diffForHumans(short: true) ?? '',
                'channel' => $conv->channel,
                'priority' => $conv->priority,
                'is_unread' => !$conv->is_read,
                'is_starred' => $conv->is_starred,
                'is_ai_handled' => $conv->is_ai_handled,
                'status' => $conv->status,
                'messages_count' => $conv->messages_count,
                'assigned_to_name' => $conv->assignedTo?->name,
                'assigned_to_initials' => $conv->assignedTo?->initials,
                'tags' => $conv->tagModels->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'color' => $t->color,
                ])->toArray(),
                'account_email' => $conv->emailAccount?->email,
                'account_provider' => $conv->emailAccount?->provider,
            ];
        })->toArray();

        if ($append) {
            $this->loadedConversations = array_merge($this->loadedConversations, $mapped);
        } else {
            $this->loadedConversations = $mapped;
        }

        // Cap at MAX_LOADED_CONVERSATIONS to prevent unbounded memory growth during infinite scroll
        if (count($this->loadedConversations) > self::MAX_LOADED_CONVERSATIONS) {
            $this->loadedConversations = array_slice($this->loadedConversations, -self::MAX_LOADED_CONVERSATIONS);
        }
    }

    public function selectConversation(int $id): void
    {
        $this->selectedConversationId = $id;

        // Mark as read
        $workspaceId = auth()->user()->active_workspace_id;
        Conversation::where('workspace_id', $workspaceId)
            ->where('id', $id)
            ->update(['is_read' => true]);

        // Update loaded list
        foreach ($this->loadedConversations as &$conv) {
            if ($conv['id'] === $id) {
                $conv['is_unread'] = false;
                break;
            }
        }
        unset($conv);

        $this->dispatch('conversation-selected', conversationId: $id);
    }

    public function toggleSelect(int $id): void
    {
        if (in_array($id, $this->selectedIds)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedIds = [];
            $this->selectAll = false;
        } else {
            // Only select visible page IDs, not ALL records
            $this->selectedIds = array_column($this->loadedConversations, 'id');
            $this->selectAll = true;
        }
    }

    // Bulk actions

    public function bulkMarkAsRead(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (empty($this->selectedIds) && !$this->selectAll) return;

        $workspaceId = auth()->user()->active_workspace_id;

        if ($this->selectAll) {
            $this->getBaseQuery()->update(['is_read' => true]);
        } else {
            Conversation::where('workspace_id', $workspaceId)
                ->whereIn('id', $this->selectedIds)
                ->update(['is_read' => true]);
        }

        foreach ($this->loadedConversations as &$conv) {
            if ($this->selectAll || in_array($conv['id'], $this->selectedIds)) {
                $conv['is_unread'] = false;
            }
        }
        unset($conv);

        $this->selectedIds = [];
        $this->selectAll = false;
        $this->dispatch('conversations-updated');
        session()->flash('success', 'Conversations marked as read.');
    }

    public function bulkMarkAsUnread(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (empty($this->selectedIds) && !$this->selectAll) return;

        $workspaceId = auth()->user()->active_workspace_id;

        if ($this->selectAll) {
            $this->getBaseQuery()->update(['is_read' => false]);
        } else {
            Conversation::where('workspace_id', $workspaceId)
                ->whereIn('id', $this->selectedIds)
                ->update(['is_read' => false]);
        }

        foreach ($this->loadedConversations as &$conv) {
            if ($this->selectAll || in_array($conv['id'], $this->selectedIds)) {
                $conv['is_unread'] = true;
            }
        }
        unset($conv);

        $this->selectedIds = [];
        $this->selectAll = false;
        $this->dispatch('conversations-updated');
        session()->flash('success', 'Conversations marked as unread.');
    }

    public function bulkStar(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (empty($this->selectedIds) && !$this->selectAll) return;

        $workspaceId = auth()->user()->active_workspace_id;

        if ($this->selectAll) {
            $this->getBaseQuery()->update(['is_starred' => true]);
        } else {
            Conversation::where('workspace_id', $workspaceId)
                ->whereIn('id', $this->selectedIds)
                ->update(['is_starred' => true]);
        }

        $this->selectedIds = [];
        $this->selectAll = false;
        $this->page = 1;
        $this->loadedConversations = [];
        $this->loadConversations();
        $this->dispatch('conversations-updated');
        session()->flash('success', 'Conversations starred.');
    }

    public function bulkArchive(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (empty($this->selectedIds) && !$this->selectAll) return;

        $workspaceId = auth()->user()->active_workspace_id;

        if ($this->selectAll) {
            $this->getBaseQuery()->update(['status' => 'closed']);
        } else {
            Conversation::where('workspace_id', $workspaceId)
                ->whereIn('id', $this->selectedIds)
                ->update(['status' => 'closed']);
        }

        $this->selectedIds = [];
        $this->selectAll = false;
        $this->page = 1;
        $this->loadedConversations = [];
        $this->loadConversations();
        $this->dispatch('conversations-updated');
        session()->flash('success', 'Conversations archived.');
    }

    public function bulkDelete(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (empty($this->selectedIds) && !$this->selectAll) return;

        $this->withOperationLock("bulk-delete-inbox-" . auth()->id(), function () {
            $workspaceId = auth()->user()->active_workspace_id;

            if ($this->selectAll) {
                $this->getBaseQuery()->delete();
            } else {
                Conversation::where('workspace_id', $workspaceId)
                    ->whereIn('id', $this->selectedIds)
                    ->delete(); // soft delete
            }

            $this->selectedIds = [];
            $this->selectAll = false;
            $this->page = 1;
            $this->loadedConversations = [];
            $this->loadConversations();
            $this->dispatch('conversations-updated');
            session()->flash('success', 'Conversations deleted.');
        });
    }

    public function bulkSpam(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (empty($this->selectedIds) && !$this->selectAll) return;

        $workspaceId = auth()->user()->active_workspace_id;

        if ($this->selectAll) {
            $this->getBaseQuery()->update(['status' => 'spam']);
        } else {
            Conversation::where('workspace_id', $workspaceId)
                ->whereIn('id', $this->selectedIds)
                ->update(['status' => 'spam']);
        }

        $this->selectedIds = [];
        $this->selectAll = false;
        $this->page = 1;
        $this->loadedConversations = [];
        $this->loadConversations();
        $this->dispatch('conversations-updated');
        session()->flash('success', 'Conversations moved to spam.');
    }

    public function bulkAssignTo(int $userId): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (empty($this->selectedIds) && !$this->selectAll) return;

        $workspaceId = auth()->user()->active_workspace_id;

        // Verify target user belongs to this workspace
        $isMember = \DB::table('workspace_members')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (!$isMember) {
            session()->flash('error', 'Selected user is not a member of this workspace.');
            return;
        }

        if ($this->selectAll) {
            $this->getBaseQuery()->update(['assigned_to' => $userId]);
        } else {
            Conversation::where('workspace_id', $workspaceId)
                ->whereIn('id', $this->selectedIds)
                ->update(['assigned_to' => $userId]);
        }

        $this->selectedIds = [];
        $this->selectAll = false;
        $this->page = 1;
        $this->loadedConversations = [];
        $this->loadConversations();
        session()->flash('success', 'Conversations assigned.');
    }

    public function bulkAddTag(int $tagId): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if (empty($this->selectedIds) && !$this->selectAll) return;

        $workspaceId = auth()->user()->active_workspace_id;

        // Verify tag belongs to workspace
        $tagExists = Tag::where('workspace_id', $workspaceId)->where('id', $tagId)->exists();
        if (!$tagExists) {
            session()->flash('error', 'Tag not found.');
            return;
        }

        // FIX-101: Use batch insert instead of N+1 syncWithoutDetaching per conversation
        if ($this->selectAll) {
            $conversationIds = $this->getBaseQuery()->limit(1000)->pluck('id');
        } else {
            $conversationIds = Conversation::where('workspace_id', $workspaceId)
                ->whereIn('id', $this->selectedIds)
                ->pluck('id');
        }

        // Batch insert pivot rows, ignoring duplicates
        $pivotData = $conversationIds->map(fn ($id) => [
            'conversation_id' => $id,
            'tag_id' => $tagId,
        ])->toArray();

        if (!empty($pivotData)) {
            \Illuminate\Support\Facades\DB::table('conversation_tag')
                ->insertOrIgnore($pivotData);
        }

        $this->selectedIds = [];
        $this->selectAll = false;
        $this->page = 1;
        $this->loadedConversations = [];
        $this->loadConversations();
        session()->flash('success', 'Tag added to conversations.');
    }

    #[On('realtime-refresh')]
    public function pollRefresh(): void
    {
        $this->loadConversations(refreshWindow: true);
    }
    #[Computed]
    public function getTeamForFilter(): \Illuminate\Support\Collection
    {
        $workspaceId = auth()->user()->active_workspace_id;
        if (!$workspaceId) return collect();

        return \App\Models\User::whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspaceId))
            ->select(['id', 'name', 'email'])
            ->get();
    }

    #[Computed]
    public function getTagsForFilter(): \Illuminate\Support\Collection
    {
        $workspaceId = auth()->user()->active_workspace_id;
        if (!$workspaceId) return collect();

        return \App\Models\Tag::where('workspace_id', $workspaceId)
            ->withCount('conversations')
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.inbox.conversation-list');
    }
}
