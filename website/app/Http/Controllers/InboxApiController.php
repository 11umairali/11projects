<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\EmailAccount;
use App\Models\Message;
use App\Models\Tag;
use App\Models\User;
use App\Services\Email\EmailSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InboxApiController extends Controller
{
    /**
     * GET /inbox/api/conversations
     * Returns paginated conversations for the conversation list.
     */
    public function conversations(Request $request): JsonResponse
    {
        $workspaceId = auth()->user()->active_workspace_id;
        if (!$workspaceId) return response()->json(['data' => [], 'has_more' => false]);

        $perPage = 20;
        $page = max(1, (int) $request->get('page', 1));
        $channel = $request->get('channel', 'all');
        $folder = $request->get('folder', 'inbox');
        $search = $request->get('search', '');
        $tag = $request->get('tag', '');
        $assignee = $request->get('assignee');
        $accountId = $request->get('account_id');
        $sortBy = $request->get('sort', 'newest');

        $query = Conversation::where('workspace_id', $workspaceId)
            ->with(['contact', 'assignedTo', 'tagModels'])
            // Pull the latest inbound message's sender name as a subquery so
            // Temp Mail / catch-all conversations (which often have a contact
            // with an empty name field) can fall back to the actual From line
            // instead of rendering "Unknown" forever.
            ->addSelect('conversations.*')
            ->addSelect(['latest_inbound_from_name' => Message::select('from_name')
                ->whereColumn('conversation_id', 'conversations.id')
                ->where('direction', 'inbound')
                ->whereNotNull('from_name')
                ->where('from_name', '!=', '')
                ->orderByDesc('created_at')
                ->limit(1)])
            ->addSelect(['latest_inbound_from_email' => Message::select('from_email')
                ->whereColumn('conversation_id', 'conversations.id')
                ->where('direction', 'inbound')
                ->whereNotNull('from_email')
                ->orderByDesc('created_at')
                ->limit(1)])
            // Direction of the most recent message. Lets the row prefix
            // "To: " in Inbox too (not just Sent) whenever the last message
            // on the thread was outbound — mirrors Gmail showing "me ↔" on
            // threads we replied to last.
            ->addSelect(['latest_message_direction' => Message::select('direction')
                ->whereColumn('conversation_id', 'conversations.id')
                ->orderByDesc('created_at')
                ->limit(1)]);

        // Channel filter
        if ($channel !== 'all') {
            if ($channel === 'chat') {
                $query->whereIn('channel', ['chat', 'live_chat']);
            } else {
                $query->where('channel', $channel);
            }
        }

        // Folder filter.
        // The Bin/Trash folder needs `onlyTrashed()` because Conversation
        // uses SoftDeletes — the default Eloquent query hides any row
        // that has `deleted_at` set, so without this branch the Bin
        // ended up rendering the regular Inbox (the match-default
        // applied no extra filter and SoftDeletes filtered everything
        // back out).
        match ($folder) {
            'inbox' => $query->whereIn('status', ['open', 'pending']),
            'sent' => $query->where('channel', 'email')
                ->whereHas('messages', fn ($q) => $q->where('direction', 'outbound')),
            'starred' => $query->where('is_starred', true),
            'snoozed' => $query->where('status', 'snoozed'),
            'spam' => $query->where('status', 'spam'),
            'archive' => $query->where('status', 'closed'),
            'trash', 'bin' => $query->onlyTrashed(),
            default => null,
        };

        if ($accountId) {
            $query->where('email_account_id', $accountId);
        }

        if ($tag) {
            $query->whereHas('tagModels', fn ($q) => $q->where('name', $tag));
        }

        if ($assignee) {
            $query->where('assigned_to', $assignee);
        }

        if ($search) {
            $term = '%' . addcslashes($search, '%_') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('subject', 'like', $term)
                    ->orWhere('preview', 'like', $term)
                    ->orWhereHas('contact', fn ($cq) => $cq->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term))
                    ->orWhereHas('messages', fn ($mq) => $mq->where('direction', 'inbound')->where(fn ($sender) => $sender->where('from_name', 'like', $term)->orWhere('from_email', 'like', $term)));
            });
        }

        // Sorting
        match ($sortBy) {
            'oldest' => $query->orderBy('last_message_at')->orderBy('created_at'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")->orderByDesc('last_message_at'),
            'unread' => $query->orderByDesc('is_read')->orderByDesc('last_message_at'),
            default => $query->orderByDesc('last_message_at')->orderByDesc('created_at'),
        };

        $offset = ($page - 1) * $perPage;
        if ($request->has('through_page')) {
            $offset = 0;
            $perPage *= min(10, max(1, (int) $request->get('through_page')));
        }
        $conversations = $query->skip($offset)->take($perPage + 1)->get();
        $hasMore = $conversations->count() > $perPage;
        $conversations = $conversations->take($perPage);

        $mutedIds = \Illuminate\Support\Facades\Schema::hasTable('email_notification_mutes')
            ? \Illuminate\Support\Facades\DB::table('email_notification_mutes')->where('user_id', auth()->id())->whereIn('conversation_id', $conversations->pluck('id'))->pluck('conversation_id')->all()
            : [];
        $data = $conversations->map(function (Conversation $c) use ($folder, $mutedIds) {
            // Resolve the display name with a sensible fallback chain:
            //   1. The contact's full_name (if non-empty)
            //   2. The latest inbound message's From: name
            //   3. The latest inbound message's From: email
            //   4. "Unknown" — last resort
            // This fixes Temp Mail / catch-all rows that previously showed
            // "Unknown" because the contact was created with an email-only
            // identity and no name parts.
            $contactName = trim((string) ($c->contact?->full_name ?? ''));
            if ($contactName === '' || $contactName === 'Unknown') {
                $contactName = trim((string) ($c->latest_inbound_from_name ?? ''));
            }
            if ($contactName === '') {
                $contactName = trim((string) ($c->latest_inbound_from_email ?? ''));
            }
            if ($contactName === '') {
                $contactName = 'Unknown';
            }

            // Prefix "To: " when the row represents an outgoing thread —
            // always in the Sent folder, and in any other folder (Inbox,
            // Starred, etc.) when the most recent message on the thread
            // was outbound. Mirrors the Gmail convention.
            if ($folder === 'sent' || $c->latest_message_direction === 'outbound') {
                $contactName = 'To: ' . $contactName;
            }

            return [
            'id' => $c->id,
            'contact_name' => $contactName,
            'contact_initials' => $c->contact?->initials ?? '??',
            'subject' => $c->subject ?? '(no subject)',
            'preview' => Str::limit(preg_replace(['/\{[^}]*\}/', '/@[^{;]+[{;]/', '/\s+/'], ['', '', ' '], trim(strip_tags($c->preview ?? ''))), 120),
            'time' => $c->last_message_at?->diffForHumans(short: true) ?? '',
            'at' => $c->last_message_at?->toIso8601String(),
            'channel' => $c->channel,
            'priority' => $c->priority,
            'is_unread' => !$c->is_read,
            'is_starred' => $c->is_starred,
            'is_muted' => in_array($c->id, $mutedIds),
            'status' => $c->status,
            'messages_count' => $c->messages_count,
            'assigned_to_initials' => $c->assignedTo?->initials,
            'assigned_to_name' => $c->assignedTo?->name,
            'tags' => $c->tagModels->map(fn ($t) => ['name' => $t->name, 'color' => $t->color])->toArray(),
            ];
        });

        return response()->json(['data' => $data, 'has_more' => $hasMore, 'page' => $page]);
    }

    /**
     * GET /inbox/api/conversations/{id}/messages
     * Returns messages for a single conversation.
     */
    public function messages(int $id, Request $request): JsonResponse
    {
        $workspaceId = auth()->user()->active_workspace_id;
        // Allow up to 500 per request so the inbox can load the entire thread
        // in one round-trip for typical conversations, avoiding multiple
        // "Load more" clicks. Longer threads still paginate.
        $limit = max(1, min(500, (int) $request->get('limit', 500)));
        $offset = max(0, (int) $request->get('offset', 0));

        // Resolve the user's timezone once. An explicit *non-UTC* profile or
        // workspace choice wins; otherwise we prefer the browser cookie (set
        // by the inbox JS from Intl) because a UTC profile usually just means
        // "never configured". This matches the save-side logic so display
        // and storage round-trip cleanly.
        $cookieTz = $request->cookie('user_tz');
        $browserTz = ($cookieTz && in_array($cookieTz, timezone_identifiers_list(), true))
            ? $cookieTz
            : null;
        $profileTz = auth()->user()->timezone;
        $workspaceTz = auth()->user()->activeWorkspace?->timezone;

        if ($profileTz && $profileTz !== 'UTC') {
            $userTimezone = $profileTz;
        } elseif ($workspaceTz && $workspaceTz !== 'UTC') {
            $userTimezone = $workspaceTz;
        } elseif ($browserTz) {
            $userTimezone = $browserTz;
        } else {
            $userTimezone = $profileTz ?? $workspaceTz ?? config('app.timezone', 'UTC');
        }

        $conversation = Conversation::where('workspace_id', $workspaceId)
            ->with(['contact', 'assignedTo', 'tagModels', 'emailAccount'])
            ->find($id);

        if (!$conversation) return response()->json(['error' => 'Not found'], 404);

        // Mobile fetches may finish after the app has been minimized.
        // Its visible screen sends a separate bounded read acknowledgement.
        if (!$request->is('api/*') && $request->boolean('mark_read', true)) $conversation->update(['is_read' => true]);

        // Auto-fetch missing bodies for IMAP messages when user opens conversation
        $hasPendingBodies = Message::where('conversation_id', $conversation->id)
            ->whereNull('body_html')
            ->whereNull('body_text')
            ->whereNotNull('imap_uid')
            ->exists();

        if ($hasPendingBodies && $conversation->emailAccount && in_array($conversation->emailAccount->provider, ['imap', 'custom'])) {
            try {
                app(EmailSyncService::class)->batchFetchBodiesForConversation($conversation, $conversation->emailAccount);
            } catch (\Throwable $e) {
                Log::warning("InboxAPI: Auto body fetch failed for conv {$id}: " . Str::limit($e->getMessage(), 100));
            }
        }

        $baseQuery = $conversation->messages()
            ->with(['sender', 'attachments'])
            ->where(function ($q) {
                $q->where('type', '!=', 'ai_draft')
                    ->orWhere(fn ($sub) => $sub->where('type', 'ai_draft')->where('ai_status', 'draft'));
            });

        $totalMessages = (clone $baseQuery)->count();

        $messages = $baseQuery
            ->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                // Stamp every row with its conversation_id so the Alpine
                // inbox can discard any cross-conversation leaks when the
                // user switches threads mid-poll. Without this the 5s
                // background refresh could merge the old conversation's
                // messages into the newly-opened one's view.
                'conversation_id' => $m->conversation_id,
                'direction' => $m->direction,
                // Distinguishes internal notes from customer-facing replies —
                // the inbox renders them in a yellow "Internal Note" bubble
                // that's only visible to the team.
                'type' => $m->type,
                'sender_name' => $m->direction === 'inbound'
                    ? ($m->from_name ?: $m->from_email ?: 'Unknown sender')
                    : ($m->sender?->name ?? 'You'),
                'sender_initials' => $m->direction === 'inbound'
                    ? mb_strtoupper(mb_substr(trim($m->from_name ?: $m->from_email ?: '?'), 0, 1))
                    : ($m->sender?->initials ?? 'Me'),
                'from_email' => $m->from_email,
                'from_name' => $m->from_name,
                'to_emails' => $m->to_emails,
                // has_html gates the email-iframe renderer in the inbox.
                // Only trigger it for real email channel messages — for chat
                // channels (telegram/whatsapp/sms/slack/live_chat) we want the
                // plain dark-theme bubble regardless of any stored HTML.
                'has_html' => $conversation->channel === 'email'
                    && !empty($m->body_html)
                    && str_contains($m->body_html, '<'),
                // Decode HTML entities before stripping so legacy rows whose
                // body_html was double-escaped (e.g. "&lt;p&gt;hi&lt;/p&gt;")
                // render as plain "hi" instead of showing literal entities.
                // Use ?: (not ??) so an empty-string body_html still falls
                // through to body_text — chat replies store '' in body_html.
                'body_text' => Str::limit(
                    trim(strip_tags(html_entity_decode(
                        !empty($m->body_html) ? $m->body_html : ($m->body_text ?? ''),
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    ))),
                    500
                ),
                'subject' => $m->subject,
                'time' => $m->created_at?->diffForHumans() ?? '',
                'date' => $m->created_at?->format('M j, Y \\a\\t g:i A') ?? '',
                'date_group' => $m->created_at?->format('M j, Y') ?? '',
                'channel' => $conversation->channel ?? 'email',
                // Delivery state — drives the status badge in the UI so a
                // scheduled outbound message reads "Scheduled" until the
                // send job actually fires, not "Sent" from the moment the
                // user clicks the button.
                'schedule_status' => $m->schedule_status,
                'scheduled_at' => $m->scheduled_at?->toIso8601String(),
                // Format in the user's timezone so the badge reads the same
                // local time they picked when scheduling (stored as UTC).
                'scheduled_at_pretty' => $m->scheduled_at
                    ?->copy()->setTimezone($userTimezone)
                    ?->format('M j, g:i A'),
                'delivery_status' => $m->delivery_status,
                'delivery_error' => $m->delivery_error,
                'sent_at_pretty' => $m->sent_at
                    ?->copy()->setTimezone($userTimezone)
                    ?->format('M j, g:i A'),
                'attachments' => $m->attachments?->filter(fn ($a) => empty($a->content_id) && $a->storage_path && \Storage::disk('public')->exists($a->storage_path))->map(fn ($a) => [
                    'id' => $a->id,
                    'filename' => $a->original_filename,
                    'size' => $a->size_for_humans,
                    'url' => asset('storage/' . $a->storage_path),
                ])->values()->toArray() ?? [],
            ]);

        // Get first and last message in a single query
        $messageBounds = $conversation->messages()
            ->selectRaw('MIN(created_at) as first_at, MAX(created_at) as last_at')
            ->first();
        $lastMsg = $conversation->messages()->with('sender')->orderByDesc('created_at')->first();

        // Calculate response time without extra query
        $responseTime = null;
        if ($messageBounds->first_at && $conversation->first_response_at) {
            $responseTime = \Carbon\Carbon::parse($conversation->first_response_at)
                ->diffForHumans(\Carbon\Carbon::parse($messageBounds->first_at), ['parts' => 1, 'short' => true]);
        }

        // Third-party customer profiles (Stripe / HubSpot / Salesforce) —
        // only fetched when the workspace has the integration active AND the
        // conversation contact has an email. Each is cached per-email for
        // 5 minutes. Returns null for any integration that's inactive, has
        // no matching customer, or errors — the inbox simply omits that card.
        $stripeCustomer = null;
        $hubspotContact = null;
        $salesforceContact = null;
        $contactEmail = $conversation->contact?->email;

        if ($contactEmail) {
            $activeIntegrations = \App\Models\ChannelIntegration::where('workspace_id', $workspaceId)
                ->whereIn('channel', ['stripe', 'hubspot', 'salesforce'])
                ->where('status', 'active')
                ->pluck('channel')
                ->toArray();

            if (in_array('stripe', $activeIntegrations, true)) {
                $stripeCustomer = cache()->remember(
                    "stripe_profile:{$workspaceId}:{$contactEmail}",
                    300,
                    function () use ($workspaceId, $contactEmail) {
                        try {
                            return app(\App\Services\Integrations\StripeIntegrationService::class)
                                ->getCustomerProfile($workspaceId, $contactEmail);
                        } catch (\Throwable $e) {
                            Log::warning('InboxAPI: Stripe profile load failed', [
                                'email' => $contactEmail,
                                'error' => Str::limit($e->getMessage(), 200),
                            ]);
                            return null;
                        }
                    }
                );
            }

            if (in_array('hubspot', $activeIntegrations, true)) {
                $hubspotContact = cache()->remember(
                    "hubspot_profile:{$workspaceId}:{$contactEmail}",
                    300,
                    function () use ($workspaceId, $contactEmail) {
                        try {
                            return app(\App\Services\Integrations\HubSpotIntegrationService::class)
                                ->getContactProfile($workspaceId, $contactEmail);
                        } catch (\Throwable $e) {
                            Log::warning('InboxAPI: HubSpot profile load failed', [
                                'email' => $contactEmail,
                                'error' => Str::limit($e->getMessage(), 200),
                            ]);
                            return null;
                        }
                    }
                );
            }

            if (in_array('salesforce', $activeIntegrations, true)) {
                $salesforceContact = cache()->remember(
                    "salesforce_profile:{$workspaceId}:{$contactEmail}",
                    300,
                    function () use ($workspaceId, $contactEmail) {
                        try {
                            return app(\App\Services\Integrations\SalesforceIntegrationService::class)
                                ->getContactProfile($workspaceId, $contactEmail);
                        } catch (\Throwable $e) {
                            Log::warning('InboxAPI: Salesforce profile load failed', [
                                'email' => $contactEmail,
                                'error' => Str::limit($e->getMessage(), 200),
                            ]);
                            return null;
                        }
                    }
                );
            }
        }

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
                'priority' => $conversation->priority,
                'channel' => $conversation->channel,
                'is_starred' => $conversation->is_starred,
                'contact_name' => $conversation->contact?->full_name ?? 'Unknown',
                'contact_email' => $conversation->contact?->email ?? '',
                'contact_phone' => $conversation->contact?->phone ?? '',
                'contact_company' => $conversation->contact?->company ?? '',
                'contact_initials' => $conversation->contact?->initials ?? '??',
                'assigned_to' => $conversation->assignedTo?->name,
                'account_email' => $conversation->emailAccount?->email,
                'tags' => $conversation->tagModels->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->toArray(),
                'messages_count' => $conversation->messages_count,
                'last_message_at' => $conversation->last_message_at?->format('M j, Y g:i A'),
                'last_message_by' => $lastMsg?->from_name ?? $lastMsg?->sender?->name ?? 'Unknown',
                'last_message_direction' => $lastMsg?->direction,
                'first_message_at' => $messageBounds->first_at ? \Carbon\Carbon::parse($messageBounds->first_at)->format('M j, Y g:i A') : null,
                'created_at' => $conversation->created_at?->format('M j, Y g:i A'),
                'response_time' => $responseTime,
                'stripe_customer' => $stripeCustomer,
                'hubspot_contact' => $hubspotContact,
                'salesforce_contact' => $salesforceContact,
            ],
            'messages' => $messages,
            'total_messages' => $totalMessages,
            'has_more' => ($offset + $limit) < $totalMessages,
            'offset' => $offset,
        ]);
    }

    /**
     * GET /inbox/api/poll
     * Lightweight endpoint to check for new conversations.
     */
    public function poll(Request $request): JsonResponse
    {
        $workspaceId = auth()->user()->active_workspace_id;
        if (!$workspaceId) return response()->json(['count' => 0, 'latest_id' => 0]);

        $lastId = (int) $request->get('last_id', 0);

        $stats = Conversation::where('workspace_id', $workspaceId)
            ->selectRaw('COUNT(*) as total, MAX(id) as latest_id, MAX(updated_at) as latest_update, SUM(messages_count) as message_count, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread')
            ->first();

        $latestId = $stats->latest_id ?? 0;
        $total = $stats->total ?? 0;

        return response()->json([
            'total' => $total,
            'unread' => $stats->unread ?? 0,
            'latest_id' => $latestId,
            'revision' => ($stats->latest_update ?? '').':'.($stats->message_count ?? 0),
            'has_new' => ($lastId > 0 && $latestId > $lastId) || ($lastId === 0 && $total > 0),
        ]);
    }

    /**
     * GET /inbox/api/sidebar
     * Returns sidebar counts.
     */
    public function sidebar(): JsonResponse
    {
        $workspaceId = auth()->user()->active_workspace_id;
        if (!$workspaceId) return response()->json([]);

        // Single query instead of 10 separate COUNT queries.
        // Channel counts use the same open/pending filter as the Inbox
        // folder so the sidebar numbers add up (email+whatsapp+sms+…
        // should equal Inbox, not inflate it with snoozed/closed rows).
        $stats = Conversation::where('workspace_id', $workspaceId)
            ->selectRaw("
                SUM(CASE WHEN status IN ('open','pending') THEN 1 ELSE 0 END) as inbox,
                SUM(CASE WHEN is_starred = 1 THEN 1 ELSE 0 END) as starred,
                SUM(CASE WHEN status = 'snoozed' THEN 1 ELSE 0 END) as snoozed,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as archive,
                SUM(CASE WHEN channel = 'email'    AND status IN ('open','pending') THEN 1 ELSE 0 END) as ch_email,
                SUM(CASE WHEN channel = 'whatsapp' AND status IN ('open','pending') THEN 1 ELSE 0 END) as ch_whatsapp,
                SUM(CASE WHEN channel = 'sms'      AND status IN ('open','pending') THEN 1 ELSE 0 END) as ch_sms,
                SUM(CASE WHEN channel = 'telegram' AND status IN ('open','pending') THEN 1 ELSE 0 END) as ch_telegram,
                SUM(CASE WHEN channel = 'slack'    AND status IN ('open','pending') THEN 1 ELSE 0 END) as ch_slack,
                SUM(CASE WHEN channel IN ('chat','live_chat') AND status IN ('open','pending') THEN 1 ELSE 0 END) as ch_chat
            ")->first();

        // "Sent" folder count — conversations that have at least one
        // outbound email message. Done as a separate query because the
        // condition lives on the messages table, not the conversation
        // status field, so it can't be folded into the SUM(CASE) above.
        $sentCount = Conversation::where('workspace_id', $workspaceId)
            ->where('channel', 'email')
            ->whereHas('messages', fn ($q) => $q->where('direction', 'outbound'))
            ->count();

        return response()->json([
            'folders' => [
                'inbox' => (int) ($stats->inbox ?? 0),
                'sent' => $sentCount,
                'starred' => (int) ($stats->starred ?? 0),
                'snoozed' => (int) ($stats->snoozed ?? 0),
                'archive' => (int) ($stats->archive ?? 0),
            ],
            'channels' => [
                'email' => (int) ($stats->ch_email ?? 0),
                'whatsapp' => (int) ($stats->ch_whatsapp ?? 0),
                'sms' => (int) ($stats->ch_sms ?? 0),
                'telegram' => (int) ($stats->ch_telegram ?? 0),
                'slack' => (int) ($stats->ch_slack ?? 0),
                'chat' => (int) ($stats->ch_chat ?? 0),
            ],
        ]);
    }

    /**
     * POST /inbox/api/conversations/{id}/action
     * Perform actions on a conversation (star, close, reopen, spam, trash, assign, priority, snooze, tag).
     */
    public function action(int $id, Request $request): JsonResponse
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $action = $request->input('action');

        // For force_delete the row is already in the bin (deleted_at set),
        // so the default Eloquent query would 404 it out before we get a
        // chance to purge. Include soft-deleted rows specifically for
        // that action.
        $base = $action === 'force_delete'
            ? Conversation::withTrashed()->where('workspace_id', $workspaceId)
            : Conversation::where('workspace_id', $workspaceId);

        $conv = $base->findOrFail($id);

        if ($action === 'mark_read' && $request->has('through_message_id')) {
            $data = $request->validate(['through_message_id' => 'required|integer|min:1']);
            $through = (int) $data['through_message_id'];
            abort_unless($conv->messages()->whereKey($through)->exists(), 422, 'Message is not in this conversation.');
            // Atomic guard: a message arriving after the fetched snapshot stays unread.
            Conversation::whereKey($conv->id)->whereDoesntHave('messages', fn ($q) => $q->where('id', '>', $through))
                ->update(['is_read' => true]);
            return response()->json(['message' => 'Read receipt saved.']);
        }

        match ($action) {
            'star' => $conv->update(['is_starred' => !$conv->is_starred]),
            'close' => $conv->update(['status' => 'closed']),
            'reopen' => $conv->update(['status' => 'open']),
            'spam' => $conv->update(['status' => 'spam']),
            'trash' => $conv->delete(),
            // Permanently remove a row that's already in the Bin.
            'force_delete' => $conv->forceDelete(),
            // Pull a row back out of the Bin into its previous folder.
            'restore' => $conv->restore(),
            'mark_read' => $conv->update(['is_read' => true]),
            'mark_unread' => $conv->update(['is_read' => false]),
            'assign' => $conv->update(['assigned_to' => $request->input('user_id')]),
            'priority' => $conv->update(['priority' => $request->input('value')]),
            'snooze' => $conv->update([
                'status' => 'snoozed',
                'snoozed_until' => match ($request->input('duration')) {
                    '1h' => now()->addHour(),
                    '3h' => now()->addHours(3),
                    'tomorrow' => now()->addDay()->setTime(9, 0),
                    'next_week' => now()->next('Monday')->setTime(9, 0),
                    default => now()->addHour(),
                },
            ]),
            'add_tag' => (function () use ($conv, $request) {
                $conv->tagModels()->syncWithoutDetaching([$request->input('tag_id')]);
                if ($conv->contact) {
                    try { event(new \App\Events\TagAdded($conv->contact, \App\Models\Tag::find($request->input('tag_id')))); } catch (\Throwable $e) {}
                }
            })(),
            'remove_tag' => (function () use ($conv, $request) {
                $tagId = $request->input('tag_id');
                $conv->tagModels()->detach([$tagId]);
                if ($conv->contact) {
                    $tag = \App\Models\Tag::find($tagId);
                    if ($tag) {
                        try { event(new \App\Events\TagRemoved($conv->contact, $tag)); } catch (\Throwable $e) {}
                    }
                }
            })(),
            default => null,
        };

        // After force_delete the row is gone, so $conv->fresh() is null.
        // Return what's still safe to read off the fresh model when
        // available, otherwise echo what we know.
        $fresh = $conv->fresh();
        return response()->json([
            'ok'         => true,
            'status'     => $fresh?->status ?? $conv->status,
            'is_starred' => $fresh?->is_starred ?? $conv->is_starred,
        ]);
    }

    /**
     * POST /inbox/api/conversations/{id}/summarize
     *
     * AI Recap — generates a 2-line context summary of the entire thread
     * so an agent can pick up a long support conversation in seconds.
     *
     * Reuses the existing AIManager::generateCompose() — no new tables,
     * no new model attributes. Cached for 30 minutes keyed by conversation
     * id + message count, so re-clicking on an unchanged thread is free
     * (cache key changes the moment a new message arrives, auto-busting).
     */
    public function summarize(int $id): JsonResponse
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $conv = \App\Models\Conversation::where('workspace_id', $workspaceId)->findOrFail($id);

        // Skip AI drafts and system events — they're noise for a recap.
        $messages = $conv->messages()
            ->where('type', '!=', 'ai_draft')
            ->where('type', '!=', 'system_event')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit(50)
            ->get(['direction', 'sender_type', 'body_text', 'body_html', 'from_name', 'created_at']);

        if ($messages->isEmpty()) {
            return response()->json([
                'ok' => true,
                'summary' => __('No messages in this thread yet.'),
                'cached' => false,
            ]);
        }

        $cacheKey = "conv-summary:{$conv->id}:" . $messages->count();
        $cached = cache()->has($cacheKey);

        try {
            $summary = cache()->remember($cacheKey, now()->addMinutes(30), function () use ($conv, $messages) {
                $transcript = $messages->map(function ($m) {
                    $who = $m->direction === 'inbound'
                        ? 'CUSTOMER (' . ($m->from_name ?: 'unknown') . ')'
                        : 'AGENT';
                    $body = trim(strip_tags($m->body_text ?: $m->body_html ?: ''));
                    if (mb_strlen($body) > 800) {
                        $body = mb_substr($body, 0, 800) . '…';
                    }
                    return "{$who} [{$m->created_at->format('M j, H:i')}]: {$body}";
                })->implode("\n\n");

                $totalMsgs = $messages->count();
                $inbound = $messages->where('direction', 'inbound')->count();
                $outbound = $totalMsgs - $inbound;

                $prompt = "You are summarizing the ENTIRE multi-message support thread below "
                    . "for an agent who is picking it up cold. The thread contains "
                    . "{$totalMsgs} messages ({$inbound} from the customer, {$outbound} from agents). "
                    . "Read EVERY message — do NOT just summarize the last one. Track how the "
                    . "issue evolved across the whole thread.\n\n"
                    . "Output EXACTLY 2 short lines, plain text, no Markdown, no bullets, no quotes:\n"
                    . "Line 1 — What the customer wants / the core issue across the whole thread (1 sentence).\n"
                    . "Line 2 — Current status: what has been done so far + what the agent should do next (1 sentence).\n\n"
                    . "===== THREAD ({$totalMsgs} messages) START =====\n"
                    . "{$transcript}\n"
                    . "===== THREAD END =====\n\n"
                    . "Two-line summary:";

                $response = app(\App\Services\AI\AIManager::class)->generateCompose(
                    $conv->workspace,
                    $prompt,
                    'professional',
                    [
                        'subject' => $conv->subject,
                        'sender_name' => 'AI Recap',
                    ]
                );

                $clean = trim(strip_tags($response->content));
                $clean = trim($clean, "\"'`\u{201C}\u{201D}\u{2018}\u{2019}");
                return $clean !== '' ? $clean : __('AI could not summarize this thread.');
            });

            return response()->json([
                'ok' => true,
                'summary' => $summary,
                'cached' => $cached,
                'message_count' => $messages->count(),
            ]);
        } catch (\Throwable $e) {
            // Tell the user exactly WHICH provider/model was used so they can
            // tell whether their /settings/ai choice actually took effect or
            // whether the platform fell back to the admin default. Saves a
            // round of "why is it calling X when I picked Y" debugging.
            // Mirror the same priority chain AIManager::getDefaultConfig() uses
            // so the diagnostic reflects what the system actually called, not
            // "unknown / unknown" when neither the workspace row nor the
            // admin-saved system_setting exists.
            $aiConfig = $conv->workspace->aiConfig;
            $resolvedProvider = $aiConfig?->provider
                ?: (\App\Models\SystemSetting::get('ai_default_provider') ?:
                    (config('services.mistral.api_key') ? 'mistral'
                    : (config('services.anthropic.api_key') ? 'anthropic'
                    : (config('services.openai.api_key') ? 'openai' : 'gemini'))));
            $resolvedModel = $aiConfig?->model
                ?: (\App\Models\SystemSetting::get('ai_default_model') ?: 'platform default');

            \Log::warning('summarize endpoint failed', [
                'conversation_id' => $id,
                'provider' => $resolvedProvider,
                'model' => $resolvedModel,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'ok' => false,
                'error' => "AI Recap failed (using {$resolvedProvider} / {$resolvedModel}): " . $e->getMessage(),
                'provider' => $resolvedProvider,
                'model' => $resolvedModel,
            ], 500);
        }
    }

    /**
     * POST /inbox/api/conversations/{id}/ai-chat
     *
     * AI Assistant chat — answers an agent's free-form question about
     * the current thread. The full conversation transcript is injected
     * into the system prompt so the AI can answer "what should I reply?",
     * "is this customer angry?", "what was the order number?", etc.
     *
     * Body params:
     *   prompt   — the agent's question (string, max 1000 chars)
     *   history  — prior chat turns in this assistant session, array of
     *              {role:'user'|'assistant', content:string} (optional,
     *              max 20 turns to keep prompts cost-bounded)
     */
    public function aiChat(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'prompt'  => 'required|string|max:1000',
            'history' => 'sometimes|array|max:20',
            'history.*.role'    => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:4000',
        ]);

        $workspaceId = auth()->user()->active_workspace_id;
        $conv = \App\Models\Conversation::where('workspace_id', $workspaceId)->findOrFail($id);

        $messages = $conv->messages()
            ->where('type', '!=', 'ai_draft')
            ->where('type', '!=', 'system_event')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit(50)
            ->get(['direction', 'sender_type', 'body_text', 'body_html', 'from_name', 'created_at']);

        $transcript = $messages->map(function ($m) {
            $who = $m->direction === 'inbound'
                ? 'CUSTOMER (' . ($m->from_name ?: 'unknown') . ')'
                : 'AGENT';
            $body = trim(strip_tags($m->body_text ?: $m->body_html ?: ''));
            if (mb_strlen($body) > 800) {
                $body = mb_substr($body, 0, 800) . '…';
            }
            return "{$who} [{$m->created_at->format('M j, H:i')}]: {$body}";
        })->implode("\n\n");

        $history = collect($request->input('history', []))
            ->map(fn ($t) => strtoupper($t['role']) . ': ' . trim($t['content']))
            ->implode("\n\n");

        $userPrompt = trim($request->input('prompt'));

        $totalMsgs = $messages->count();
        $inbound = $messages->where('direction', 'inbound')->count();
        $outbound = $totalMsgs - $inbound;

        $fullPrompt = "You are an AI assistant helping a support agent triage a customer conversation.\n"
            . "The thread below has {$totalMsgs} messages ({$inbound} from the customer, {$outbound} from agents). "
            . "Read EVERY message — do NOT focus only on the latest one. Use the full history to answer accurately.\n"
            . "Answer in plain text, be concise (1-4 sentences unless explicitly asked otherwise),\n"
            . "no Markdown, no bullets unless asked, no preamble. If asked to draft a reply,\n"
            . "write it ready-to-send (no \"Here's a draft:\" wrapper).\n\n"
            . "===== THREAD ({$totalMsgs} messages) START =====\n{$transcript}\n===== THREAD END =====\n\n"
            . ($history !== '' ? "===== PRIOR Q&A =====\n{$history}\n\n" : '')
            . "AGENT QUESTION: {$userPrompt}\n\nYOUR ANSWER:";

        try {
            $response = app(\App\Services\AI\AIManager::class)->generateCompose(
                $conv->workspace,
                $fullPrompt,
                'professional',
                [
                    'subject' => $conv->subject,
                    'sender_name' => 'AI Assistant',
                ]
            );

            $clean = trim(strip_tags($response->content));
            $clean = trim($clean, "\"'`\u{201C}\u{201D}\u{2018}\u{2019}");

            return response()->json([
                'ok' => true,
                'reply' => $clean !== '' ? $clean : __('Sorry, I could not generate an answer for that.'),
            ]);
        } catch (\Throwable $e) {
            // Mirror the same priority chain AIManager::getDefaultConfig() uses
            // so the diagnostic reflects what the system actually called, not
            // "unknown / unknown" when neither the workspace row nor the
            // admin-saved system_setting exists.
            $aiConfig = $conv->workspace->aiConfig;
            $resolvedProvider = $aiConfig?->provider
                ?: (\App\Models\SystemSetting::get('ai_default_provider') ?:
                    (config('services.mistral.api_key') ? 'mistral'
                    : (config('services.anthropic.api_key') ? 'anthropic'
                    : (config('services.openai.api_key') ? 'openai' : 'gemini'))));
            $resolvedModel = $aiConfig?->model
                ?: (\App\Models\SystemSetting::get('ai_default_model') ?: 'platform default');

            \Log::warning('aiChat endpoint failed', [
                'conversation_id' => $id,
                'prompt' => $userPrompt,
                'provider' => $resolvedProvider,
                'model' => $resolvedModel,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'ok' => false,
                'error' => "AI Assistant failed (using {$resolvedProvider} / {$resolvedModel}): " . $e->getMessage(),
                'provider' => $resolvedProvider,
                'model' => $resolvedModel,
            ], 500);
        }
    }

    /**
     * POST /inbox/api/bulk
     * Bulk actions on multiple conversations.
     */
    public function bulk(Request $request): JsonResponse
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $validated = $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'required|integer|min:1', 'action' => 'required|in:mark_read,star,unstar,archive,unarchive,delete,force_delete,mute,unmute']);
        $ids = array_values(array_unique($validated['ids']));
        $action = $validated['action'];

        // For purge-from-trash (force_delete) we MUST include soft-deleted
        // rows in the base query — otherwise withTrashed-aware actions
        // would match nothing and silently do nothing. The other actions
        // operate on live (non-trashed) rows only, so they keep the
        // default behaviour.
        $query = Conversation::where('workspace_id', $workspaceId)
            ->whereIn('id', $ids);

        $forceDeleteQuery = Conversation::withTrashed()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $ids);

        if (in_array($action, ['mute', 'unmute'], true)) {
            $allowed = (clone $query)->where('channel', 'email')->pluck('id')->all();
            abort_unless(count($allowed) === count($ids), 403);
            if ($action === 'mute') {
                \Illuminate\Support\Facades\DB::table('email_notification_mutes')->insertOrIgnore(array_map(fn ($id) => [
                    'user_id' => auth()->id(), 'conversation_id' => $id,
                ], $allowed));
            } else {
                \Illuminate\Support\Facades\DB::table('email_notification_mutes')->where('user_id', auth()->id())->whereIn('conversation_id', $allowed)->delete();
            }
            return response()->json(['ok' => true, 'affected' => count($allowed)]);
        }

        match ($action) {
            'unarchive' => $query->update(['status' => 'open']),
            'mark_read' => $query->update(['is_read' => true]),
            'star' => $query->update(['is_starred' => true]),
            'unstar' => $query->update(['is_starred' => false]),
            'archive' => $query->update(['status' => 'closed']),
            // Soft-delete: live rows go to Bin (sets deleted_at).
            'delete' => $query->delete(),
            // Permanently remove rows that are already in the Bin.
            // The frontend sends this when the user clicks the trash
            // icon while viewing the Trash folder.
            'force_delete' => $forceDeleteQuery->forceDelete(),
            default => null,
        };

        return response()->json(['ok' => true, 'affected' => count($ids)]);
    }

    /**
     * GET /inbox/api/tags
     * Returns workspace tags.
     */
    public function tags(): JsonResponse
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $tags = Tag::where('workspace_id', $workspaceId)
            ->select(['id', 'name', 'color'])
            ->withCount(['conversations'])
            ->orderBy('name')
            ->get();

        return response()->json($tags);
    }

    /**
     * POST /inbox/api/sync
     * Fetches one page of emails per call, saves to DB, returns instantly.
     * JS calls this in a loop — typical cadence ~1s — until has_more=false.
     * Also dispatches a background SyncEmailAccountJob so progress continues
     * after the user leaves the inbox.
     */
    public function syncNow(Request $request): JsonResponse
    {
        $workspaceId = auth()->user()->active_workspace_id;
        if (!$workspaceId) return response()->json(['error' => 'No workspace'], 400);

        $skip = $request->validate(['skip_accounts' => 'sometimes|array|max:1000', 'skip_accounts.*' => 'integer|min:1']);
        $accounts = EmailAccount::where('workspace_id', $workspaceId)
            ->whereNotIn('id', $skip['skip_accounts'] ?? [])
            ->whereIn('status', ['connected', 'error'])
            ->get();

        if ($accounts->isEmpty()) {
            return response()->json(['synced' => 0, 'has_more' => false]);
        }

        $syncService = app(EmailSyncService::class);
        $totalSynced = 0;
        $failedAccounts = 0;
        $failures = [];
        $hasMore = false;

        // 25 per call is a middle ground: low enough to stay under PHP's typical
        // 30s max_execution_time on shared hosts (25 × ~500ms Gmail fetch = ~12s),
        // high enough that a backfill completes quickly from the browser.
        $perCall = 25;

        foreach ($accounts as $account) {
            try {
                [$count, $more] = $syncService->syncBatch($account, $perCall);
                $totalSynced += $count;
                if ($more) $hasMore = true;

                // Fire MessageReceived for inbound messages saved in this batch
                // so workflows/AI/auto-reply trigger from inbox-driven syncs too,
                // not only from the cron-driven SyncEmailAccountJob path.
                if ($count > 0) {
                    $this->fireMessageReceivedForRecent($account);
                }
            } catch (\Throwable $e) {
                $failedAccounts++;
                $failure = \App\Services\Email\MailSyncFailure::describe($e);
                $failures[] = ['account_id' => $account->id, 'email' => $account->email, ...$failure];
                Log::warning("InboxAPI: Batch sync failed: " . Str::limit($e->getMessage(), 100), [
                    'account_id' => $account->id, 'provider' => $account->provider,
                    'exception' => get_class($e), 'code' => $failure['code'],
                ]);
            }
        }

        // Dispatch background job so the backfill keeps progressing after the
        // user leaves the inbox. SyncEmailAccountJob is ShouldBeUnique so
        // duplicates are dropped automatically.
        if ($hasMore) {
            foreach ($accounts as $account) {
                \App\Jobs\SyncEmailAccountJob::dispatch($account);
            }
        }

        return response()->json(['synced' => $totalSynced, 'has_more' => $hasMore, 'failed_accounts' => $failedAccounts, 'failures' => $failures]);
    }

    /**
     * Fire MessageReceived events for inbound messages that landed in the last
     * ~2 minutes from this account. Uses a short-lived cache key per message
     * so the same message never triggers workflows twice, even when the browser
     * polls syncNow every second.
     */
    private function fireMessageReceivedForRecent(EmailAccount $account): void
    {
        $messages = Message::where('workspace_id', $account->workspace_id)
            ->whereHas('conversation', fn ($q) => $q->where('email_account_id', $account->id))
            ->where('direction', 'inbound')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->with('conversation')
            ->limit(100)
            ->get();

        foreach ($messages as $message) {
            $dedupeKey = "msg_received_fired:{$message->id}";
            if (!\Illuminate\Support\Facades\Cache::add($dedupeKey, true, 3600)) {
                continue;
            }

            if (!$message->conversation) continue;

            // Fire MessageReceived — AutoReplyListener runs both keyword
            // and AI auto-reply synchronously off this event for every
            // channel (email, telegram, whatsapp, sms, slack, live chat),
            // with the same spam/noise filter applied universally. The old
            // inline keyword + AI paths used to live here but caused email
            // to skip the sanity filter; the event listener is now the
            // single source of truth for auto-reply and de-dupes via the
            // `ai_reply_fired:{id}` cache key.
            try {
                event(new \App\Events\MessageReceived($message, $message->conversation));
            } catch (\Throwable $e) {
                Log::warning("InboxAPI: MessageReceived dispatch failed for msg={$message->id}: {$e->getMessage()}");
            }

            // 4. Slack / Zapier — fire inline so users in the inbox see the
            // notification immediately without waiting for the integrations
            // queue worker (which may be 30-60s behind on shared hosting).
            // The QUEUED listener still handles the cron-driven path when
            // the user isn't in the inbox. Belt-and-suspenders.
            $this->dispatchIntegrationsSync($message, $message->conversation);
        }
    }

    /**
     * Inline-fire Slack and Zapier notifications so AJAX-driven syncs don't
     * depend on the integrations queue being drained. Each vendor is fully
     * independent (separate try/catch) — Slack failing never blocks Zapier.
     * Dedupe: Cache::add keyed per (msg, vendor) so each vendor gets one
     * shot even if syncNow polls the same row across calls.
     */
    private function dispatchIntegrationsSync(Message $message, Conversation $conversation): void
    {
        $workspaceId = $conversation->workspace_id;

        // Slack — post rich conversation update to the configured channel
        try {
            $slackKey = "int_slack_fired:{$message->id}";
            $claimed = \Illuminate\Support\Facades\Cache::add($slackKey, true, 3600);
            $slack = app(\App\Services\Integrations\SlackIntegrationService::class);
            $active = $slack->isActive($workspaceId);

            Log::info("InboxAPI: Slack inline check", [
                'msg' => $message->id,
                'workspace' => $workspaceId,
                'cache_claimed' => $claimed,
                'slack_active' => $active,
            ]);

            if ($claimed && $active) {
                $posted = $slack->postConversationUpdate($conversation, 'new_message');
                Log::info("InboxAPI: Slack inline post result msg={$message->id} posted=" . ($posted ? 'true' : 'false'));
            }
        } catch (\Throwable $e) {
            Log::warning("InboxAPI: Slack inline notify failed for msg={$message->id}: {$e->getMessage()}");
        }

        // Zapier — fire conversation.new webhook
        try {
            $zapKey = "int_zapier_fired:{$message->id}";
            if (\Illuminate\Support\Facades\Cache::add($zapKey, true, 3600)) {
                $zapier = app(\App\Services\Integrations\ZapierIntegrationService::class);
                if ($zapier->isActive($workspaceId)) {
                    $zapier->triggerWebhook($workspaceId, 'conversation.new', [
                        'conversation_id' => $conversation->id,
                        'message_id' => $message->id,
                        'subject' => $message->subject,
                        'from_email' => $message->from_email,
                        'from_name' => $message->from_name,
                        'channel' => $conversation->channel,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("InboxAPI: Zapier inline webhook failed for msg={$message->id}: {$e->getMessage()}");
        }
    }

    /**
     * POST /inbox/api/conversations/{id}/fetch-bodies
     * On-demand body fetch — when user opens a conversation with missing bodies,
     * fetch them inline so content appears immediately.
     */
    public function fetchBodies(int $id): JsonResponse
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $conversation = Conversation::where('workspace_id', $workspaceId)
            ->with('emailAccount')
            ->find($id);

        if (!$conversation) return response()->json(['error' => 'Not found'], 404);

        // Only for email channel with IMAP accounts
        $emailAccount = $conversation->emailAccount;
        if (!$emailAccount || !in_array($emailAccount->provider, ['imap', 'custom'])) {
            return response()->json(['fetched' => 0, 'message' => 'Not an IMAP account']);
        }

        // Check if there are any messages without bodies
        $pending = Message::where('conversation_id', $conversation->id)
            ->whereNull('body_html')
            ->whereNull('body_text')
            ->whereNotNull('imap_uid')
            ->count();

        if ($pending === 0) {
            return response()->json(['fetched' => 0, 'message' => 'All bodies loaded']);
        }

        try {
            $syncService = app(EmailSyncService::class);
            $syncService->batchFetchBodiesForConversation($conversation, $emailAccount);

            return response()->json([
                'fetched' => $pending,
                'message' => "Fetched {$pending} message bodies",
            ]);
        } catch (\Throwable $e) {
            Log::error("InboxAPI: Body fetch failed for conversation {$id}", [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['fetched' => 0, 'error' => Str::limit($e->getMessage(), 200)]);
        }
    }

    /**
     * POST /inbox/api/set-timezone
     * JS on the inbox page calls this once on load with the browser's
     * IANA timezone (e.g. "Asia/Kolkata"). We store it on the user row so
     * every subsequent server-side operation (scheduled emails, display
     * formatting, campaigns) has the right tz without any per-request
     * cookie/property gymnastics.
     */
    public function setTimezone(Request $request): JsonResponse
    {
        $tz = trim((string) $request->input('timezone', ''));
        $user = auth()->user();

        if (!$user) {
            Log::warning('InboxAPI: set-timezone called without auth');
            return response()->json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }

        if (!$tz) {
            Log::warning('InboxAPI: set-timezone got empty value', ['user_id' => $user->id]);
            return response()->json(['ok' => false, 'error' => 'missing timezone'], 422);
        }

        // Validate by trying to construct a DateTimeZone — this accepts both
        // canonical IANA names AND deprecated aliases like "Asia/Calcutta"
        // (which some browsers still return). timezone_identifiers_list()
        // alone rejects those aliases and was causing 422s.
        try {
            new \DateTimeZone($tz);
        } catch (\Throwable $e) {
            Log::warning('InboxAPI: set-timezone invalid tz string', [
                'user_id' => $user->id,
                'input' => $tz,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => 'invalid timezone: ' . $tz], 422);
        }

        $previous = $user->timezone;
        if ($previous === $tz) {
            Log::debug('InboxAPI: set-timezone — already set', [
                'user_id' => $user->id,
                'tz' => $tz,
            ]);
            return response()->json(['ok' => true, 'timezone' => $tz, 'changed' => false]);
        }

        $user->timezone = $tz;
        $user->save();

        Log::info('InboxAPI: set-timezone — updated user profile', [
            'user_id' => $user->id,
            'from' => $previous,
            'to' => $tz,
        ]);

        return response()->json(['ok' => true, 'timezone' => $tz, 'changed' => true, 'previous' => $previous]);
    }

    /**
     * GET /inbox/api/team
     * Returns workspace team members.
     */
    public function team(): JsonResponse
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $members = User::whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $workspaceId))
            ->select(['id', 'name', 'email'])
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'initials' => $u->initials,
            ]);

        return response()->json($members);
    }

    /**
     * GET /inbox/api/accounts
     *
     * Returns the workspace's connected email accounts so the inbox
     * sidebar can render an "All Accounts ▾" picker. Tapping an account
     * sets the account filter, which the conversations endpoint already
     * honours via ?account_id=N (existing wiring at line ~95).
     */
    public function accounts(): JsonResponse
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $accounts = \App\Models\EmailAccount::where('workspace_id', $workspaceId)
            ->orderBy('is_default', 'desc')
            ->orderBy('email')
            ->get(['id', 'email', 'display_name', 'provider', 'status', 'is_default'])
            ->map(fn ($a) => [
                'id'           => $a->id,
                'email'        => $a->email,
                'display_name' => $a->display_name ?: $a->email,
                'provider'     => $a->provider,
                'status'       => $a->status,
                'is_default'   => (bool) $a->is_default,
            ]);

        return response()->json($accounts);
    }
}
