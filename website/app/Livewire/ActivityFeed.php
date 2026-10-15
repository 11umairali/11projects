<?php

namespace App\Livewire;

use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

class ActivityFeed extends Component
{
    use AuthorizesWorkspaceActions;
    #[Url]
    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    public int $perPage = 20;

    /**
     * Activity type configuration: maps log_name.event combos
     * to human labels, icon keys, and color schemes.
     */
    protected static array $activityMeta = [
        'campaign.sent' => [
            'label' => 'Campaign sent',
            'icon' => 'rocket',
            'color' => 'brand',
        ],
        'campaign.completed' => [
            'label' => 'Campaign completed',
            'icon' => 'rocket',
            'color' => 'success',
        ],
        'contact.created' => [
            'label' => 'Contact created',
            'icon' => 'user-plus',
            'color' => 'info',
        ],
        'contact.imported' => [
            'label' => 'Contacts imported',
            'icon' => 'user-plus',
            'color' => 'info',
        ],
        'conversation.created' => [
            'label' => 'New conversation',
            'icon' => 'message',
            'color' => 'info',
        ],
        'conversation.updated' => [
            'label' => 'Conversation updated',
            'icon' => 'message',
            'color' => 'muted',
        ],
        'conversation.replied' => [
            'label' => 'Conversation replied',
            'icon' => 'message',
            'color' => 'brand',
        ],
        'conversation.closed' => [
            'label' => 'Conversation closed',
            'icon' => 'message',
            'color' => 'muted',
        ],
        'message.created' => [
            'label' => 'Message received',
            'icon' => 'mail',
            'color' => 'brand',
        ],
        'message.sent' => [
            'label' => 'Message sent',
            'icon' => 'mail',
            'color' => 'success',
        ],
        'workflow.executed' => [
            'label' => 'Workflow executed',
            'icon' => 'workflow',
            'color' => 'accent',
        ],
        'email.sent' => [
            'label' => 'Email sent',
            'icon' => 'mail',
            'color' => 'brand',
        ],
        'deal.created' => [
            'label' => 'Deal created',
            'icon' => 'trophy',
            'color' => 'info',
        ],
        'deal.won' => [
            'label' => 'Deal won',
            'icon' => 'trophy',
            'color' => 'success',
        ],
        'deal.lost' => [
            'label' => 'Deal lost',
            'icon' => 'chart',
            'color' => 'danger',
        ],
        'team.joined' => [
            'label' => 'Team member joined',
            'icon' => 'users',
            'color' => 'success',
        ],
    ];

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="space-y-4 animate-pulse">
            <div class="h-6 w-32 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
            <div class="space-y-3">
                <div class="flex gap-3"><div class="h-9 w-9 bg-gray-200 dark:bg-gray-700 rounded-xl shrink-0"></div><div class="flex-1"><div class="h-4 w-3/4 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div><div class="h-3 w-1/4 bg-gray-200 dark:bg-gray-700 rounded"></div></div></div>
                <div class="flex gap-3"><div class="h-9 w-9 bg-gray-200 dark:bg-gray-700 rounded-xl shrink-0"></div><div class="flex-1"><div class="h-4 w-2/3 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div><div class="h-3 w-1/3 bg-gray-200 dark:bg-gray-700 rounded"></div></div></div>
                <div class="flex gap-3"><div class="h-9 w-9 bg-gray-200 dark:bg-gray-700 rounded-xl shrink-0"></div><div class="flex-1"><div class="h-4 w-1/2 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div><div class="h-3 w-1/4 bg-gray-200 dark:bg-gray-700 rounded"></div></div></div>
            </div>
        </div>
        HTML;
    }

    public function updatedSearch(): void
    {
        $this->perPage = 20;
    }

    public function updatedTypeFilter(): void
    {
        $this->perPage = 20;
    }

    public function loadMore(): void
    {
        $this->perPage += 20;
    }

    /**
     * Resolve icon SVG inner content for a given icon key.
     */
    public static function getIconSvg(string $iconKey): string
    {
        return match ($iconKey) {
            'rocket' => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
            'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>',
            'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
            'workflow' => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
            'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
            'trophy' => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>',
            'chart' => '<path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/>',
            'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            default => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        };
    }

    /**
     * Get meta info for an activity record.
     */
    public static function getMeta(Activity $activity): array
    {
        $key = $activity->log_name . '.' . ($activity->event ?? 'default');

        return self::$activityMeta[$key] ?? [
            'label' => ucfirst(str_replace(['.', '_', '-'], ' ', $key)),
            'icon' => 'default',
            'color' => 'muted',
        ];
    }

    /**
     * Build human-readable description from activity.
     *
     * Falls back intelligently — conversation.created uses the Conversation's
     * subject + contact name, message.created uses from_name / subject so the
     * feed reads like "K Kapil emailed you: 'dsddsd'" instead of the bare
     * generic label.
     */
    public static function humanDescription(Activity $activity): string
    {
        $meta = self::getMeta($activity);
        $causer = $activity->causer?->name ?? 'System';
        $subject = $activity->subject;
        $props = (array) ($activity->properties ?? []);

        // Pull whatever naming fields are available on the subject model.
        $subjectName = $subject?->name
            ?? $subject?->title
            ?? $subject?->full_name
            ?? $subject?->subject
            ?? $subject?->email
            ?? ($props['name'] ?? '');

        $key = $activity->log_name . '.' . ($activity->event ?? 'default');

        // Explicit, human-readable lines for the common high-volume events.
        switch ($key) {
            case 'conversation.created':
                $who = $subject?->contact?->full_name
                    ?? $subject?->contact?->email
                    ?? $subject?->from_name
                    ?? 'Someone';
                $subj = $subject?->subject ?: ($props['subject'] ?? 'New conversation');
                return "{$who} started a conversation: \"{$subj}\"";

            case 'message.created':
                $from = $subject?->from_name
                    ?: $subject?->from_email
                    ?: ($props['from_name'] ?? $props['from_email'] ?? 'Someone');
                $msgSubject = $subject?->subject ?: ($props['subject'] ?? '');
                $preview = trim((string) ($subject?->body_text ?? ''));
                if ($preview === '') {
                    $preview = trim(strip_tags((string) ($subject?->body_html ?? '')));
                }
                $preview = \Illuminate\Support\Str::limit($preview, 80);

                if ($msgSubject) {
                    return "{$from}: \"{$msgSubject}\"";
                }
                if ($preview) {
                    return "{$from}: {$preview}";
                }
                return "{$from} sent a new message";

            case 'message.sent':
                return "{$causer} sent a message" . ($subjectName ? " — {$subjectName}" : '');

            case 'conversation.updated':
                return "Conversation updated" . ($subjectName ? ": \"{$subjectName}\"" : '');

            case 'conversation.closed':
                return "{$causer} closed a conversation" . ($subjectName ? ": \"{$subjectName}\"" : '');

            case 'conversation.replied':
                return "{$causer} replied" . ($subjectName ? " to \"{$subjectName}\"" : ' to a conversation');

            case 'campaign.sent':
                return "{$causer} sent campaign \"{$subjectName}\"";
            case 'campaign.completed':
                return "Campaign \"{$subjectName}\" completed";

            case 'contact.created':
                return "{$causer} added contact {$subjectName}";
            case 'contact.imported':
                return "{$causer} imported contacts";

            case 'workflow.executed':
                return "Workflow \"{$subjectName}\" ran";

            case 'email.sent':
                return "{$causer} sent an email" . ($subjectName ? " — \"{$subjectName}\"" : '');

            case 'deal.created':
                return "{$causer} created deal \"{$subjectName}\"";
            case 'deal.won':
                return "Deal \"{$subjectName}\" was won 🎉";
            case 'deal.lost':
                return "Deal \"{$subjectName}\" was lost";

            case 'team.joined':
                return "{$subjectName} joined the team";
        }

        // Final fallback: if Spatie stored a description that's NOT just the
        // raw event name, use it; otherwise show the meta label.
        if ($activity->description && $activity->description !== $activity->event) {
            return $activity->description;
        }

        return $meta['label'];
    }

    /**
     * Get the URL link to the related resource (if any).
     */
    public static function resourceUrl(Activity $activity): ?string
    {
        if (! $activity->subject_type || ! $activity->subject_id) {
            return null;
        }

        $type = class_basename($activity->subject_type);

        return match ($type) {
            'Campaign' => "/campaigns/{$activity->subject_id}/report",
            'Contact' => "/contacts/{$activity->subject_id}",
            // Conversation / Message events deep-link into the inbox with the
            // conversation id pre-selected so clicking a row opens the thread.
            'Conversation' => "/inbox?c={$activity->subject_id}",
            'Message' => $activity->subject?->conversation_id
                ? "/inbox?c={$activity->subject->conversation_id}"
                : '/inbox',
            'Workflow' => "/workflows/{$activity->subject_id}/edit",
            'Deal' => '/deals',
            'User' => '/settings/team',
            default => null,
        };
    }

    /**
     * Group activities by date label: Today, Yesterday, This Week, Earlier.
     *
     * @return array<string, \Illuminate\Support\Collection>
     */
    public function getGroupedActivitiesProperty(): array
    {
        $workspaceId = auth()->user()?->active_workspace_id;

        $query = Activity::query()
            ->where('properties->workspace_id', $workspaceId)
            ->with(['causer', 'subject'])
            ->latest()
            ->limit($this->perPage);

        if ($this->typeFilter) {
            $parts = explode('.', $this->typeFilter, 2);
            $query->where('log_name', $parts[0]);
            if (isset($parts[1])) {
                $query->where('event', $parts[1]);
            }
        }

        if ($this->search) {
            $term = $this->search;
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', "%{$term}%")
                  ->orWhere('properties', 'like', "%{$term}%");
            });
        }

        $activities = $query->get();

        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $weekStart = now()->startOfWeek();

        $grouped = [];
        foreach ($activities as $activity) {
            $date = $activity->created_at->startOfDay();

            if ($date->eq($today)) {
                $label = 'Today';
            } elseif ($date->eq($yesterday)) {
                $label = 'Yesterday';
            } elseif ($date->gte($weekStart)) {
                $label = 'This Week';
            } else {
                $label = 'Earlier';
            }

            $grouped[$label][] = $activity;
        }

        return $grouped;
    }

    /**
     * Available filter options for the type dropdown.
     */
    public function getFilterOptionsProperty(): array
    {
        $options = ['' => 'All Activities'];
        foreach (self::$activityMeta as $key => $meta) {
            $options[$key] = $meta['label'];
        }

        return $options;
    }

    /**
     * Lightweight headline counts for the stat-card row at the top of the
     * Activity page. Scoped to the current workspace via Activity::query().
     */
    public function getStatsProperty(): array
    {
        $wsId = auth()->user()->active_workspace_id;
        $base = Activity::query()->where(function ($q) use ($wsId) {
            $q->whereJsonContains('properties->workspace_id', $wsId)
                ->orWhereHasMorph('subject', '*', function ($sq) use ($wsId) {
                    $sq->where('workspace_id', $wsId);
                });
        });

        return [
            'total' => (clone $base)->count(),
            'today' => (clone $base)->whereDate('created_at', today())->count(),
            'week'  => (clone $base)->where('created_at', '>=', now()->subDays(7))->count(),
            'actors' => (clone $base)->whereNotNull('causer_id')->distinct('causer_id')->count('causer_id'),
        ];
    }

    public function render()
    {
        return view('livewire.activity-feed');
    }
}
