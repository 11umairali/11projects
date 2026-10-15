<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\Channels\LiveChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Public-facing API for the embeddable live-chat widget.
 *
 * Authentication is via the workspace's chat widget public_id token,
 * passed as a Bearer token or query parameter -- NOT via session auth.
 *
 * All endpoints are rate-limited by the global 'api' throttle.
 */
class ChatWidgetController extends Controller
{
    public function __construct(
        private readonly LiveChatService $liveChatService,
    ) {}

    // ------------------------------------------------------------------
    //  Widget authentication helper
    // ------------------------------------------------------------------

    /**
     * Resolve the workspace from the widget public_id token.
     *
     * SECURITY: The token MUST be provided via the Authorization Bearer header.
     * Query params and request body are NOT accepted — tokens in URLs are logged
     * by proxies, CDNs, and browser history, creating a credential leak vector.
     */
    private function resolveWorkspace(Request $request): ?Workspace
    {
        $token = null;

        // Only accept Bearer header — never query params or body
        $authHeader = $request->header('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = trim(substr($authHeader, 7));
        }

        if (!$token || !is_string($token)) {
            return null;
        }

        // Look up the chat_widgets table by public_id to find the workspace
        $widget = DB::table('chat_widgets')
            ->where('public_id', $token)
            ->first();

        if (!$widget) {
            return null;
        }

        return Workspace::find($widget->workspace_id);
    }

    /**
     * Resolve workspace or abort with 403.
     */
    private function resolveWorkspaceOrFail(Request $request): Workspace
    {
        $workspace = $this->resolveWorkspace($request);

        if (!$workspace) {
            abort(response()->json([
                'success' => false,
                'message' => 'Invalid or missing widget token.',
            ], 403));
        }

        return $workspace;
    }

    /**
     * Get the widget config row for a workspace.
     */
    private function getWidgetConfig(int $workspaceId): ?object
    {
        return DB::table('chat_widgets')
            ->where('workspace_id', $workspaceId)
            ->first();
    }

    // ------------------------------------------------------------------
    //  Endpoints
    // ------------------------------------------------------------------

    /**
     * POST /api/widget/start-chat
     *
     * Create a new chat conversation and optionally a contact record.
     *
     * Request body:
     *   - name:     string (optional)
     *   - email:    string (optional)
     *   - phone:    string (optional)
     *   - page_url: string (optional) -- the page the visitor is on
     */
    public function startChat(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspaceOrFail($request);

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'page_url' => 'nullable|url|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $visitorName = $data['name'] ?? 'Visitor';
        $visitorEmail = $data['email'] ?? null;

        try {
            DB::beginTransaction();

            // Find or create contact
            $contact = null;
            if ($visitorEmail) {
                $contact = Contact::firstOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'email' => strtolower($visitorEmail),
                    ],
                    [
                        'first_name' => $this->extractFirstName($visitorName),
                        'last_name' => $this->extractLastName($visitorName),
                        'phone' => $data['phone'] ?? null,
                        'status' => 'active',
                        'last_seen_at' => now(),
                    ]
                );

                // Update last_seen_at on existing contacts
                $contact->update(['last_seen_at' => now()]);
            }

            // Generate a unique session ID for this chat
            $sessionId = Str::uuid()->toString();

            // Create conversation via LiveChatService
            $conversation = $this->liveChatService->createSession($workspace->id, [
                'session_id' => $sessionId,
                'name' => $visitorName,
                'email' => $visitorEmail,
            ]);

            // Link contact to conversation
            if ($contact) {
                $conversation->update(['contact_id' => $contact->id]);
            }

            // Notify agents about the new chat
            $this->liveChatService->notifyAgents($workspace->id, [
                'session_id' => $sessionId,
                'visitor_name' => $visitorName,
                'visitor_email' => $visitorEmail ?? '',
                'page_url' => $data['page_url'] ?? '',
                'message' => "{$visitorName} started a new chat",
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Chat started.',
                'data' => [
                    'conversation_id' => $conversation->uuid,
                    'session_id' => $sessionId,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('ChatWidget: Failed to start chat', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start chat. Please try again.',
            ], 500);
        }
    }

    /**
     * POST /api/widget/send-message
     *
     * Send a message in an existing chat conversation.
     *
     * Request body:
     *   - session_id: string (required)
     *   - message:    string (required)
     *   - name:       string (optional) -- visitor display name
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspaceOrFail($request);

        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|max:100',
            'message' => 'required|string|max:5000',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Find conversation by session_id scoped to this workspace
        $conversation = Conversation::where('workspace_id', $workspace->id)
            ->where('channel_conversation_id', $data['session_id'])
            ->whereIn('channel', ['chat', 'live_chat'])
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Chat session not found.',
            ], 404);
        }

        if ($conversation->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'This chat session has been closed.',
            ], 410);
        }

        try {
            $message = $this->liveChatService->storeMessage(
                conversation: $conversation,
                body: $data['message'],
                senderType: 'contact',
                senderName: $data['name'] ?? 'Visitor',
            );

            return response()->json([
                'success' => true,
                'message' => 'Message sent.',
                'data' => [
                    'id' => $message->uuid,
                    'body' => $message->body_text,
                    'sender_type' => $message->sender_type,
                    'sender_name' => $message->from_name,
                    'created_at' => $message->created_at->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('ChatWidget: Failed to send message', [
                'workspace_id' => $workspace->id,
                'session_id' => $data['session_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message.',
            ], 500);
        }
    }

    /**
     * GET /api/widget/history?session_id=xxx
     *
     * Return message history for a chat session.
     */
    public function history(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspaceOrFail($request);

        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'session_id is required.',
            ], 422);
        }

        $conversation = Conversation::where('workspace_id', $workspace->id)
            ->where('channel_conversation_id', $sessionId)
            ->whereIn('channel', ['chat', 'live_chat'])
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => true,
                'data' => ['messages' => []],
            ]);
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->limit(200)
            ->get()
            ->map(fn (Message $msg) => [
                'id' => $msg->uuid,
                'body' => $msg->body_text ?? strip_tags($msg->body_html ?? ''),
                'sender_type' => $msg->sender_type,
                'sender_name' => $msg->from_name,
                'direction' => $msg->direction,
                'created_at' => $msg->created_at->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $messages,
                'status' => $conversation->status,
            ],
        ]);
    }

    /**
     * POST /api/widget/rate
     *
     * Store a visitor's chat rating.
     *
     * Request body:
     *   - session_id: string (required)
     *   - rating:     int    (required, 1-5)
     *   - feedback:   string (optional)
     */
    public function rate(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspaceOrFail($request);

        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|max:100',
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $conversation = Conversation::where('workspace_id', $workspace->id)
            ->where('channel_conversation_id', $data['session_id'])
            ->whereIn('channel', ['chat', 'live_chat'])
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Chat session not found.',
            ], 404);
        }

        // Store rating in the conversation's tags JSON field as metadata.
        // This avoids requiring a schema migration. A dedicated column would
        // be better long-term but this keeps the fix non-destructive.
        $tags = $conversation->tags ?? [];
        $tags['_chat_rating'] = (int) $data['rating'];
        if (!empty($data['feedback'])) {
            $tags['_chat_feedback'] = $data['feedback'];
        }

        $conversation->update(['tags' => $tags]);

        // Also store as a system message so agents can see it in the thread
        $this->liveChatService->storeMessage(
            conversation: $conversation,
            body: "Visitor rated this chat {$data['rating']}/5" . (!empty($data['feedback']) ? ": {$data['feedback']}" : ''),
            senderType: 'system',
            senderName: 'System',
        );

        return response()->json([
            'success' => true,
            'message' => 'Rating submitted. Thank you!',
        ]);
    }

    /**
     * POST /api/widget/close
     *
     * Close a chat session from the visitor's side.
     *
     * Request body:
     *   - session_id: string (required)
     */
    public function close(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspaceOrFail($request);

        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $conversation = Conversation::where('workspace_id', $workspace->id)
            ->where('channel_conversation_id', $request->input('session_id'))
            ->whereIn('channel', ['chat', 'live_chat'])
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Chat session not found.',
            ], 404);
        }

        if ($conversation->status !== 'closed') {
            $conversation->update([
                'status' => 'closed',
                'resolved_at' => now(),
            ]);

            // Add a system message
            $this->liveChatService->storeMessage(
                conversation: $conversation,
                body: 'Visitor closed the chat.',
                senderType: 'system',
                senderName: 'System',
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Chat closed.',
        ]);
    }

    /**
     * GET /api/widget/status
     *
     * Return the widget configuration: online/offline state, welcome
     * message, branding, pre-chat form requirements.
     */
    public function status(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        if (!$workspace) {
            return response()->json([
                'success' => false,
                'online' => false,
                'message' => 'Widget not found.',
            ], 404);
        }

        $config = $this->getWidgetConfig($workspace->id);

        if (!$config) {
            return response()->json([
                'success' => true,
                'data' => [
                    'online' => false,
                    'welcome_message' => 'We are currently offline.',
                ],
            ]);
        }

        // Determine online status based on operating hours
        $isOnline = $this->isWithinOperatingHours($workspace, $config);

        // Check if any agents are actually available
        $hasAvailableAgents = $workspace->members()
            ->wherePivot('status', 'online')
            ->exists();

        // White-label resolution for the chat widget's "Powered by" line:
        //   1. If the workspace's plan includes the `white_label` feature,
        //      the credit is hidden regardless of widget config.
        //   2. Otherwise honor the per-widget show_branding toggle (default
        //      true so freshly installed widgets credit the platform).
        $showBranding = (bool) ($config->show_branding ?? true);
        if ($showBranding && \App\Helpers\WhiteLabel::isWhiteLabeled($workspace)) {
            $showBranding = false;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'online' => $isOnline && $hasAvailableAgents,
                'welcome_message' => $config->welcome_message ?? 'Hi there! How can we help you today?',
                'offline_message' => $config->offline_message ?? 'We are currently offline. Leave a message and we will get back to you.',
                'offline_mode' => $config->offline_mode ?? 'leave_message',
                'pre_chat_form' => $config->pre_chat_form ?? 'name_email',
                'pre_chat_required' => (bool) ($config->pre_chat_required ?? true),
                'primary_color' => $config->primary_color ?? '#4F46E5',
                'position' => $config->position ?? 'bottom_right',
                'company_name' => $config->company_name ?? $workspace->name,
                'show_branding' => $showBranding,
                'brand_name' => \App\Helpers\WhiteLabel::brandName(),
                'file_sharing' => (bool) ($config->file_sharing ?? true),
                'chat_rating' => (bool) ($config->chat_rating ?? true),
                'sound_notification' => (bool) ($config->sound_notification ?? true),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    //  Private helpers
    // ------------------------------------------------------------------

    /**
     * Determine if we're within the widget's operating hours.
     */
    private function isWithinOperatingHours(Workspace $workspace, object $config): bool
    {
        $mode = $config->operating_hours ?? 'workspace';

        if ($mode === '24_7') {
            return true;
        }

        $hours = match ($mode) {
            'custom' => json_decode($config->custom_hours ?? '{}', true),
            default => $workspace->business_hours ?? [],
        };

        if (empty($hours)) {
            return true; // No hours configured means always open
        }

        $tz = $workspace->timezone ?? config('app.timezone', 'UTC');
        $now = now($tz);
        $dayKey = strtolower($now->format('l')); // monday, tuesday, etc.

        $dayConfig = $hours[$dayKey] ?? null;

        if (!$dayConfig || !($dayConfig['enabled'] ?? false)) {
            return false;
        }

        $start = $dayConfig['start'] ?? '09:00';
        $end = $dayConfig['end'] ?? '17:00';
        $currentTime = $now->format('H:i');

        return $currentTime >= $start && $currentTime <= $end;
    }

    private function extractFirstName(string $name): string
    {
        $parts = explode(' ', trim($name), 2);
        return $parts[0] ?? 'Visitor';
    }

    private function extractLastName(string $name): string
    {
        $parts = explode(' ', trim($name), 2);
        return $parts[1] ?? '';
    }
}
