<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ContactTimeline extends Component
{
    public int $contactId;
    public int $limit = 20;
    public string $channelFilter = 'all';

    public function loadMore(): void
    {
        $this->limit += 20;
    }

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $contact = Contact::where('workspace_id', $workspaceId)->findOrFail($this->contactId);

        $timeline = collect();

        // Conversations and their first few messages (workspace-scoped for defense-in-depth)
        $conversations = Conversation::where('workspace_id', $workspaceId)
            ->where('contact_id', $contact->id)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at', 'asc')->limit(3)])
            ->get();

        foreach ($conversations as $conv) {
            $timeline->push([
                'type' => 'conversation_started',
                'title' => 'Conversation started',
                'description' => $conv->subject ?? 'No subject',
                'channel' => $conv->channel ?? 'email',
                'date' => $conv->created_at,
                'icon' => 'message-square',
                'color' => 'brand',
            ]);

            foreach ($conv->messages as $msg) {
                $isOutbound = ($msg->direction ?? '') === 'outbound';
                $bodyPreview = mb_substr(strip_tags($msg->body_text ?? $msg->body_html ?? ''), 0, 100);

                $timeline->push([
                    'type' => $isOutbound ? 'email_sent' : 'email_received',
                    'title' => $isOutbound ? 'Email sent' : 'Email received',
                    'description' => $bodyPreview ? $bodyPreview . '...' : 'No content',
                    'date' => $msg->created_at,
                    'icon' => $isOutbound ? 'send' : 'mail',
                    'color' => $isOutbound ? 'info' : 'success',
                ]);
            }
        }

        // Deals (workspace-scoped)
        $deals = Deal::where('workspace_id', $workspaceId)
            ->where('contact_id', $contact->id)->get();
        foreach ($deals as $deal) {
            $timeline->push([
                'type' => 'deal_created',
                'title' => 'Deal created',
                'description' => "{$deal->title} -- \${$deal->value}",
                'date' => $deal->created_at,
                'icon' => 'briefcase',
                'color' => 'warning',
            ]);
        }

        // Contact creation event
        $timeline->push([
            'type' => 'contact_created',
            'title' => 'Contact added',
            'description' => 'Contact was created in the workspace',
            'date' => $contact->created_at,
            'icon' => 'user-plus',
            'color' => 'success',
        ]);

        // Audit log entries for this contact (workspace-scoped to prevent cross-tenant leakage)
        $auditLogs = DB::table('audit_logs')
            ->where('auditable_type', 'App\\Models\\Contact')
            ->where('auditable_id', $contact->id)
            ->where('workspace_id', $workspaceId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        foreach ($auditLogs as $log) {
            $timeline->push([
                'type' => 'activity',
                'title' => ucfirst(str_replace('_', ' ', $log->event ?? 'updated')),
                'description' => $log->actor_name ?? 'System',
                'date' => $log->created_at,
                'icon' => 'activity',
                'color' => 'muted',
            ]);
        }

        // All events (unfiltered) for channel summary counts
        $allEvents = $timeline->sortByDesc('date')->values();

        // Apply channel filter
        if ($this->channelFilter !== 'all') {
            $timeline = $timeline->filter(function ($event) {
                return ($event['channel'] ?? null) === $this->channelFilter;
            });
        }

        // Sort descending by date, limit results
        $timeline = $timeline->sortByDesc('date')->take($this->limit)->values();

        return view('livewire.contacts.contact-timeline', [
            'timeline' => $timeline,
            'events' => $allEvents,
            'hasMore' => $timeline->count() >= $this->limit,
        ]);
    }
}
