<?php

namespace App\Livewire\Inbox;

use App\Models\Conversation;
use App\Models\EmailAccount;
use App\Models\Tag;
use App\Models\User;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class InboxSidebar extends Component
{
    use AuthorizesWorkspaceActions;

    public function placeholder()
    {
        $folderItem = <<<'ITEM'
            <div class="flex items-center gap-3 px-3 py-1.5">
                <div class="w-4 h-4 bg-border rounded"></div>
                <div class="h-3 bg-border rounded w-20"></div>
                <div class="ml-auto h-4 w-6 bg-border/50 rounded-full"></div>
            </div>
ITEM;
        $folders = str_repeat($folderItem, 6);

        $channelItem = <<<'ITEM'
            <div class="flex items-center gap-2 px-3 py-1">
                <div class="w-2 h-2 bg-border rounded-full"></div>
                <div class="h-3 bg-border rounded w-16"></div>
                <div class="ml-auto h-3 w-5 bg-border/50 rounded-full"></div>
            </div>
ITEM;
        $channels = str_repeat($channelItem, 5);

        return <<<HTML
        <div class="w-full md:w-[220px] xl:w-[240px] flex-shrink-0 border-r border-border flex flex-col bg-surface-2 min-w-0 animate-pulse">
            <div class="px-3 py-3 border-b border-border flex-shrink-0">
                <div class="h-4 bg-border rounded w-16 mb-3"></div>
                {$folders}
            </div>
            <div class="px-3 py-3 flex-shrink-0">
                <div class="h-3 bg-border rounded w-14 mb-2"></div>
                {$channels}
            </div>
        </div>
        HTML;
    }

    public string $activeChannel = 'all';
    public string $activeFolder = 'inbox';
    public ?string $activeTag = null;
    public ?int $activeAssignee = null;
    public ?int $activeAccountId = null;

    // Memoized counts to avoid re-querying within the same render cycle
    protected ?array $cachedChannelCounts = null;
    protected ?array $cachedFolderCounts = null;

    public function setChannel(string $channel): void
    {
        $this->activeChannel = $channel;
        $this->invalidateCountCaches();
        $this->dispatchFilter();
    }

    public function setFolder(string $folder): void
    {
        $this->activeFolder = $folder;
        $this->invalidateCountCaches();
        $this->dispatchFilter();
    }

    public function setAccount(?int $accountId): void
    {
        $this->activeAccountId = $this->activeAccountId === $accountId ? null : $accountId;
        $this->invalidateCountCaches();
        $this->dispatchFilter();
    }

    /** Bust sidebar count caches so next render picks up fresh data */
    #[\Livewire\Attributes\On('realtime-refresh')]
    #[\Livewire\Attributes\On('conversations-updated')]
    public function invalidateCountCaches(): void
    {
        $workspaceId = $this->getWorkspaceId();
        if ($workspaceId) {
            cache()->forget("sidebar-channels:{$workspaceId}");
            cache()->forget("sidebar-folders:{$workspaceId}");
        }
    }

    public function setTag(?string $tag): void
    {
        $this->activeTag = $this->activeTag === $tag ? null : $tag;
        $this->dispatchFilter();
    }

    public function setAssignee(?int $assigneeId): void
    {
        $this->activeAssignee = $this->activeAssignee === $assigneeId ? null : $assigneeId;
        $this->dispatchFilter();
    }

    protected function dispatchFilter(): void
    {
        $this->dispatch('sidebar-filter-changed',
            channel: $this->activeChannel,
            folder: $this->activeFolder,
            tag: $this->activeTag,
            assignee: $this->activeAssignee,
            accountId: $this->activeAccountId,
        );
    }

    protected function getWorkspaceId(): ?int
    {
        return auth()->user()->active_workspace_id;
    }

    protected function getChannelCounts(): array
    {
        if ($this->cachedChannelCounts !== null) {
            return $this->cachedChannelCounts;
        }

        $workspaceId = $this->getWorkspaceId();
        if (!$workspaceId) {
            $this->cachedChannelCounts = array_fill_keys(['all', 'email', 'whatsapp', 'sms', 'telegram', 'slack', 'chat'], 0);
            return $this->cachedChannelCounts;
        }

        // Cache channel counts for 30s to avoid re-querying on every poll
        $this->cachedChannelCounts = cache()->remember(
            "sidebar-channels:{$workspaceId}",
            30,
            function () use ($workspaceId) {
                $counts = Conversation::where('workspace_id', $workspaceId)
                    ->where('is_read', false)
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['closed', 'spam'])
                    ->select('channel', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('channel')
                    ->pluck('cnt', 'channel')
                    ->toArray();

                return [
                    'all' => array_sum($counts),
                    'email' => $counts['email'] ?? 0,
                    'whatsapp' => $counts['whatsapp'] ?? 0,
                    'sms' => $counts['sms'] ?? 0,
                    'telegram' => $counts['telegram'] ?? 0,
                    'slack' => $counts['slack'] ?? 0,
                    'chat' => ($counts['chat'] ?? 0) + ($counts['live_chat'] ?? 0),
                ];
            }
        );

        return $this->cachedChannelCounts;
    }

    protected function getFolderCounts(): array
    {
        if ($this->cachedFolderCounts !== null) {
            return $this->cachedFolderCounts;
        }

        $workspaceId = $this->getWorkspaceId();
        if (!$workspaceId) {
            $this->cachedFolderCounts = array_fill_keys(['inbox', 'sent', 'starred', 'snoozed', 'spam', 'trash', 'archive'], 0);
            return $this->cachedFolderCounts;
        }

        // Cache folder counts for 30s to avoid re-querying on every poll
        $this->cachedFolderCounts = cache()->remember(
            "sidebar-folders:{$workspaceId}",
            30,
            function () use ($workspaceId) {
                $statusCounts = Conversation::where('workspace_id', $workspaceId)
                    ->whereNull('deleted_at')
                    ->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as inbox")
                    ->selectRaw("SUM(CASE WHEN is_starred = 1 THEN 1 ELSE 0 END) as starred")
                    ->selectRaw("SUM(CASE WHEN status = 'snoozed' THEN 1 ELSE 0 END) as snoozed")
                    ->selectRaw("SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END) as spam")
                    ->selectRaw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as archive")
                    ->first();

                $trashCount = Conversation::onlyTrashed()->where('workspace_id', $workspaceId)->count();

                $sentCount = Conversation::where('workspace_id', $workspaceId)
                    ->whereNull('deleted_at')
                    ->where('status', 'closed')
                    ->whereHas('messages', fn ($q) => $q->where('direction', 'outbound'))
                    ->count();

                return [
                    'inbox' => (int) ($statusCounts->inbox ?? 0),
                    'sent' => $sentCount,
                    'starred' => (int) ($statusCounts->starred ?? 0),
                    'snoozed' => (int) ($statusCounts->snoozed ?? 0),
                    'spam' => (int) ($statusCounts->spam ?? 0),
                    'trash' => $trashCount,
                    'archive' => (int) ($statusCounts->archive ?? 0),
                ];
            }
        );

        return $this->cachedFolderCounts;
    }

    protected function getTagsWithCounts(): \Illuminate\Support\Collection
    {
        $workspaceId = $this->getWorkspaceId();
        if (!$workspaceId) {
            return collect();
        }

        return Tag::where('workspace_id', $workspaceId)
            ->withCount('conversations')
            ->orderBy('name')
            ->get();
    }

    protected function getTeamMembers(): \Illuminate\Support\Collection
    {
        $workspaceId = $this->getWorkspaceId();
        if (!$workspaceId) {
            return collect();
        }

        // Single query with subquery count to avoid N+1
        $conversationCounts = Conversation::where('workspace_id', $workspaceId)
            ->where('status', 'open')
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as cnt')
            ->groupBy('assigned_to')
            ->pluck('cnt', 'assigned_to');

        return User::whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspaceId))
            ->get()
            ->map(function ($user) use ($conversationCounts) {
                $user->assigned_count = $conversationCounts[$user->id] ?? 0;
                return $user;
            });
    }

    protected function getEmailAccounts(): \Illuminate\Support\Collection
    {
        $workspaceId = $this->getWorkspaceId();
        if (!$workspaceId) {
            return collect();
        }

        return EmailAccount::where('workspace_id', $workspaceId)
            ->where('status', 'connected')
            ->withCount(['conversations' => function ($q) {
                $q->where('status', 'open')->where('is_read', false);
            }])
            ->orderByDesc('is_default')
            ->orderBy('email')
            ->get();
    }

    public function render()
    {
        // Reset memoized caches for each render cycle
        $this->cachedChannelCounts = null;
        $this->cachedFolderCounts = null;

        return view('livewire.inbox.inbox-sidebar', [
            'channelCounts' => $this->getChannelCounts(),
            'folderCounts' => $this->getFolderCounts(),
            'tags' => $this->getTagsWithCounts(),
            'teamMembers' => $this->getTeamMembers(),
            'emailAccounts' => $this->getEmailAccounts(),
        ]);
    }
}
