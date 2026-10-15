<?php

namespace App\Livewire\Search;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Campaign;
use App\Models\Deal;
use App\Models\KbDocument;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Global Search — full-page search results with tabbed navigation.
 *
 * Searches across contacts, conversations, messages, campaigns,
 * deals, and knowledge-base documents scoped to the active workspace.
 */
class GlobalSearch extends Component
{
    use AuthorizesWorkspaceActions;

    /** The raw search query string. */
    public string $query = '';

    /** Active filter tab: all | contacts | conversations | messages | campaigns | deals | documents */
    public string $activeTab = 'all';

    /** Grouped results keyed by type. */
    public array $results = [];

    /** Whether a search has been executed at least once. */
    public bool $searched = false;

    /** Per-type result counts for tab badges. */
    public array $counts = [];

    /** Query-string binding so the URL stays in sync. */
    protected $queryString = [
        'query' => ['as' => 'q', 'except' => ''],
        'activeTab' => ['as' => 'type', 'except' => 'all'],
    ];

    /**
     * Mount — pre-fill query from the URL if present.
     */
    public function mount(): void
    {
        if ($this->query) {
            $this->performSearch();
        }
    }

    /**
     * React to live query changes (debounced in the view).
     */
    public function updatedQuery(): void
    {
        if (strlen($this->query) < 2) {
            $this->results = [];
            $this->counts = [];
            $this->searched = false;
            return;
        }

        $this->performSearch();
    }

    /**
     * React to tab changes.
     */
    public function updatedActiveTab(): void
    {
        // No re-fetch needed — results are already grouped.
        // Tab filtering happens in the view.
    }

    /**
     * Explicit search action (e.g. form submit / Enter key).
     */
    public function search(): void
    {
        $this->performSearch();
    }

    /**
     * Switch to a specific tab.
     */
    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * Core search logic — queries every entity type scoped to the workspace.
     */
    protected function performSearch(): void
    {
        if (!$this->authorizeWorkspaceAction('view')) return;

        $user = Auth::user();
        $wsId = $user->active_workspace_id;

        if (!$wsId) return;

        $isMember = $user->workspaces()->where('workspaces.id', $wsId)->exists();
        if (!$isMember) return;

        $q = trim($this->query);
        if (strlen($q) < 2) return;

        $this->searched = true;

        // ── Contacts ──
        $contacts = Contact::where('workspace_id', $wsId)
            ->where(function ($query) use ($q) {
                $query->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%");
            })
            ->limit(15)
            ->get(['id', 'first_name', 'last_name', 'email', 'company', 'updated_at'])
            ->map(fn ($c) => [
                'id'        => $c->id,
                'title'     => trim("{$c->first_name} {$c->last_name}"),
                'subtitle'  => $c->email,
                'meta'      => $c->company,
                'type'      => 'contact',
                'url'       => route('contacts.detail', $c->id),
                'timestamp' => $c->updated_at?->diffForHumans(),
            ])->toArray();

        // ── Conversations ──
        $conversations = Conversation::where('workspace_id', $wsId)
            ->where('subject', 'like', "%{$q}%")
            ->with('contact:id,first_name,last_name')
            ->limit(15)
            ->get(['id', 'subject', 'channel', 'status', 'contact_id', 'last_message_at'])
            ->map(fn ($c) => [
                'id'        => $c->id,
                'title'     => $c->subject ?: 'No subject',
                'subtitle'  => $c->contact ? trim("{$c->contact->first_name} {$c->contact->last_name}") : 'Unknown',
                'meta'      => ucfirst($c->channel) . ' · ' . ucfirst($c->status),
                'type'      => 'conversation',
                'url'       => route('inbox') . "?conversation={$c->id}",
                'timestamp' => $c->last_message_at?->diffForHumans(),
            ])->toArray();

        // ── Messages ──
        $messages = Message::where('workspace_id', $wsId)
            ->where('body_text', 'like', "%{$q}%")
            ->with('conversation:id,subject')
            ->limit(10)
            ->get(['id', 'conversation_id', 'body_text', 'sender_type', 'created_at'])
            ->map(fn ($m) => [
                'id'        => $m->id,
                'title'     => $m->conversation?->subject ?: 'Message',
                'subtitle'  => Str::limit(strip_tags($m->body_text), 120),
                'meta'      => ucfirst($m->sender_type ?? 'unknown'),
                'type'      => 'message',
                'url'       => route('inbox') . "?conversation={$m->conversation_id}",
                'timestamp' => $m->created_at?->diffForHumans(),
            ])->toArray();

        // ── Campaigns ──
        $campaigns = Campaign::where('workspace_id', $wsId)
            ->where('name', 'like', "%{$q}%")
            ->limit(10)
            ->get(['id', 'name', 'status', 'type', 'updated_at'])
            ->map(fn ($c) => [
                'id'        => $c->id,
                'title'     => $c->name,
                'subtitle'  => ucfirst($c->status),
                'meta'      => ucfirst($c->type),
                'type'      => 'campaign',
                'url'       => route('campaigns.edit', $c->id),
                'timestamp' => $c->updated_at?->diffForHumans(),
            ])->toArray();

        // ── Deals ──
        $deals = Deal::where('workspace_id', $wsId)
            ->where('title', 'like', "%{$q}%")
            ->limit(10)
            ->get(['id', 'title', 'value', 'status', 'updated_at'])
            ->map(fn ($d) => [
                'id'        => $d->id,
                'title'     => $d->title,
                'subtitle'  => '$' . number_format($d->value ?? 0, 2),
                'meta'      => ucfirst($d->status),
                'type'      => 'deal',
                'url'       => route('deals'),
                'timestamp' => $d->updated_at?->diffForHumans(),
            ])->toArray();

        // ── Knowledge Base ──
        $docs = KbDocument::where('workspace_id', $wsId)
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'title', 'type', 'status', 'updated_at'])
            ->map(fn ($d) => [
                'id'        => $d->id,
                'title'     => $d->title,
                'subtitle'  => ucfirst($d->type),
                'meta'      => ucfirst($d->status),
                'type'      => 'document',
                'url'       => route('knowledge-base'),
                'timestamp' => $d->updated_at?->diffForHumans(),
            ])->toArray();

        $this->results = [
            'contacts'      => $contacts,
            'conversations' => $conversations,
            'messages'      => $messages,
            'campaigns'     => $campaigns,
            'deals'         => $deals,
            'documents'     => $docs,
        ];

        $this->counts = array_map('count', $this->results);
    }

    public function render()
    {
        $totalResults = collect($this->results)->flatten(1)->count();

        return view('livewire.search.global-search', [
            'totalResults' => $totalResults,
        ]);
    }
}
