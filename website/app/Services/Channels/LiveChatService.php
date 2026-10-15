<?php

namespace App\Services\Channels;

use App\Events\ChatMessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LiveChatService
{
    /**
     * Send a message to a chat visitor via Pusher broadcast.
     */
    public function sendToVisitor(string $sessionId, string $message, string $senderName, string $senderType = 'agent'): void
    {
        try {
            event(new ChatMessageSent(
                sessionId: $sessionId,
                message: $message,
                senderName: $senderName,
                senderType: $senderType,
                timestamp: now()->toIso8601String(),
            ));

            Log::info('LiveChat: Message sent to visitor', [
                'session_id' => $sessionId,
                'sender' => $senderName,
                'sender_type' => $senderType,
            ]);
        } catch (\Throwable $e) {
            Log::error('LiveChat: Failed to send message to visitor', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Notify agents in a workspace about a new chat.
     */
    public function notifyAgents(int $workspaceId, array $chatData): void
    {
        try {
            event(new ChatMessageSent(
                sessionId: "workspace.{$workspaceId}",
                message: $chatData['message'] ?? 'New chat started',
                senderName: $chatData['visitor_name'] ?? 'Visitor',
                senderType: 'system',
                timestamp: now()->toIso8601String(),
                metadata: [
                    'type' => 'new_chat',
                    'session_id' => $chatData['session_id'] ?? '',
                    'visitor_name' => $chatData['visitor_name'] ?? 'Visitor',
                    'visitor_email' => $chatData['visitor_email'] ?? '',
                    'page_url' => $chatData['page_url'] ?? '',
                    'workspace_id' => $workspaceId,
                ],
            ));

            Log::info('LiveChat: Agents notified of new chat', [
                'workspace_id' => $workspaceId,
                'session_id' => $chatData['session_id'] ?? '',
            ]);
        } catch (\Throwable $e) {
            Log::error('LiveChat: Failed to notify agents', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a conversation record for a new live chat session.
     */
    public function createSession(int $workspaceId, array $visitorInfo): Conversation
    {
        $conversation = Conversation::create([
            'workspace_id' => $workspaceId,
            'channel' => 'live_chat',
            'channel_conversation_id' => $visitorInfo['session_id'] ?? Str::uuid()->toString(),
            'status' => 'open',
            'priority' => 'normal',
            'subject' => 'Live Chat: ' . ($visitorInfo['name'] ?? 'Visitor'),
            'is_read' => false,
            'messages_count' => 0,
            'last_message_at' => now(),
        ]);

        Log::info('LiveChat: Session created', [
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspaceId,
        ]);

        return $conversation;
    }

    /**
     * Store a chat message in the database.
     */
    public function storeMessage(Conversation $conversation, string $body, string $senderType, ?string $senderName = null, ?int $senderId = null): Message
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'workspace_id' => $conversation->workspace_id,
            'uuid' => Str::uuid(),
            'direction' => $senderType === 'contact' ? 'inbound' : 'outbound',
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'type' => 'text',
            'body_text' => $body,
            'body_html' => nl2br(e($body)),
            'from_name' => $senderName ?? ($senderType === 'contact' ? 'Visitor' : 'Agent'),
            'delivery_status' => 'delivered',
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'preview' => Str::limit($body, 200),
            'is_read' => $senderType !== 'contact', // mark unread for inbound
        ]);
        $conversation->increment('messages_count');

        // Fire MessageReceived for inbound messages (visitor-sent)
        if ($senderType === 'contact') {
            event(new \App\Events\MessageReceived($message, $conversation));
        }

        return $message;
    }
}
