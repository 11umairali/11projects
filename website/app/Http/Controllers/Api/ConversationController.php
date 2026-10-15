<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    use AuthorizesApiActions;
    /**
     * List conversations with filters and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $query = Conversation::where('workspace_id', $workspaceId)
            ->with(['contact', 'assignedTo']);

        // Filter by channel
        if ($channel = $request->input('channel')) {
            $query->where('channel', $channel);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by priority
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        // Filter by assigned user
        if ($assignedTo = $request->input('assigned_to')) {
            if ($assignedTo === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', (int) $assignedTo);
            }
        }

        // Filter by starred
        if ($request->has('is_starred')) {
            $query->where('is_starred', filter_var($request->input('is_starred'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by AI handled
        if ($request->has('is_ai_handled')) {
            $query->where('is_ai_handled', filter_var($request->input('is_ai_handled'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by read status
        if ($request->has('is_read')) {
            $query->where('is_read', filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN));
        }

        // Search by subject or preview
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('preview', 'like', "%{$search}%");
            });
        }

        // Filter by contact
        if ($contactId = $request->input('contact_id')) {
            $query->where('contact_id', (int) $contactId);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'last_message_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['last_message_at', 'created_at', 'priority', 'status'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 25), 100);

        return ConversationResource::collection($query->paginate($perPage));
    }

    /**
     * Show a single conversation with messages.
     */
    public function show(Request $request, int $id): ConversationResource|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $conversation = Conversation::where('workspace_id', $workspaceId)
            ->with(['contact', 'assignedTo', 'messages' => function ($q) {
                $q->with(['sender', 'attachments'])->orderBy('created_at', 'asc');
            }])
            ->find($id);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        // Mark as read
        if (!$conversation->is_read) {
            $conversation->update(['is_read' => true]);
        }

        $resource = new ConversationResource($conversation);
        $resource->additional([
            'messages' => MessageResource::collection($conversation->messages),
        ]);

        return $resource;
    }

    /**
     * Create a new conversation.
     */
    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'create')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $validator = Validator::make($request->all(), [
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('workspace_id', $workspaceId)],
            'channel' => 'required|string|in:email,whatsapp,sms,live_chat,telegram,slack',
            'subject' => 'nullable|string|max:500',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conversation = DB::transaction(function () use ($request, $workspaceId) {
            $conversation = Conversation::create([
                'workspace_id' => $workspaceId,
                'contact_id' => $request->input('contact_id'),
                'channel' => $request->input('channel'),
                'subject' => $request->input('subject'),
                'priority' => $request->input('priority', 'normal'),
                'status' => 'open',
                'preview' => mb_substr($request->input('message'), 0, 200),
                'messages_count' => 1,
                'last_message_at' => now(),
                'assigned_to' => $request->user()->id,
            ]);

            Message::create([
                'conversation_id' => $conversation->id,
                'workspace_id' => $workspaceId,
                'direction' => 'outbound',
                'sender_type' => 'user',
                'sender_id' => $request->user()->id,
                'type' => 'reply',
                'body_text' => $request->input('message'),
                'body_html' => nl2br(e($request->input('message'))),
                'sent_at' => now(),
            ]);

            return $conversation;
        });

        $conversation->load(['contact', 'assignedTo']);

        return (new ConversationResource($conversation))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a conversation.
     */
    public function update(Request $request, int $id): ConversationResource|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $conversation = Conversation::where('workspace_id', $workspaceId)->find($id);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|string|in:open,pending,snoozed,closed,spam',
            'priority' => 'sometimes|string|in:low,normal,high,urgent',
            'is_starred' => 'sometimes|boolean',
            'is_pinned' => 'sometimes|boolean',
            'is_read' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conversation->update($validator->validated());
        $conversation->load(['contact', 'assignedTo']);

        return new ConversationResource($conversation);
    }

    /**
     * Delete a conversation (soft delete).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $conversation = Conversation::where('workspace_id', $workspaceId)->find($id);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $conversation->delete();

        return response()->json(null, 204);
    }

    /**
     * Reply to a conversation.
     */
    public function reply(Request $request, int $conversationId): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $conversation = Conversation::where('workspace_id', $workspaceId)->find($conversationId);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'body_text' => 'required|string',
            'body_html' => 'nullable|string',
            'cc_emails' => 'nullable|array',
            'cc_emails.*' => 'email',
            'bcc_emails' => 'nullable|array',
            'bcc_emails.*' => 'email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Sanitize HTML to prevent stored XSS — whitelist-based DOM sanitizer
        // strips dangerous attributes (onerror, onclick, javascript: URIs, etc.)
        $bodyHtml = $request->input('body_html');
        if ($bodyHtml) {
            $bodyHtml = \App\Helpers\HtmlSanitizer::sanitize($bodyHtml);
        } else {
            $bodyHtml = nl2br(e($request->input('body_text')));
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspaceId,
            'direction' => 'outbound',
            'sender_type' => 'user',
            'sender_id' => $request->user()->id,
            'type' => 'reply',
            'body_text' => $request->input('body_text'),
            'body_html' => $bodyHtml,
            'cc_emails' => $request->input('cc_emails'),
            'bcc_emails' => $request->input('bcc_emails'),
            'sent_at' => now(),
        ]);

        $conversation->update([
            'preview' => mb_substr($request->input('body_text'), 0, 200),
            'last_message_at' => now(),
            'messages_count' => $conversation->messages_count + 1,
            'status' => $conversation->status === 'closed' ? 'open' : $conversation->status,
        ]);

        $message->load(['sender', 'attachments']);

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Assign a conversation to a team member.
     */
    public function assign(Request $request, int $conversationId): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $conversation = Conversation::where('workspace_id', $workspaceId)->find($conversationId);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->input('user_id');

        // FIX-031: Verify user belongs to workspace AND has an assignable role (agent+)
        $user = User::whereHas('workspaces', function ($q) use ($workspaceId) {
            $q->where('workspaces.id', $workspaceId)
              ->whereIn('workspace_members.role', ['owner', 'admin', 'agent']);
        })->where('status', 'active')->find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found or does not have permission to be assigned conversations.'], 422);
        }

        $conversation->update(['assigned_to' => $userId]);
        $conversation->load(['contact', 'assignedTo']);

        return response()->json([
            'data' => new ConversationResource($conversation),
            'message' => "Conversation assigned to {$user->name}.",
        ]);
    }

    /**
     * Close a conversation.
     */
    public function close(Request $request, int $conversationId): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $conversation = Conversation::where('workspace_id', $workspaceId)->find($conversationId);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $conversation->update([
            'status' => 'closed',
            'resolved_at' => now(),
        ]);

        $conversation->load(['contact', 'assignedTo']);

        return response()->json([
            'data' => new ConversationResource($conversation),
            'message' => 'Conversation closed.',
        ]);
    }
}
