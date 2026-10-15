<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $sessionId  The chat session or workspace identifier for the channel.
     * @param  string  $message  The message text.
     * @param  string  $senderName  Display name of the sender.
     * @param  string  $senderType  One of: agent, contact, ai, system.
     * @param  string  $timestamp  ISO 8601 timestamp.
     * @param  array  $metadata  Optional extra data (e.g., attachments, type hints).
     */
    public function __construct(
        public string $sessionId,
        public string $message,
        public string $senderName,
        public string $senderType,
        public string $timestamp,
        public array $metadata = [],
    ) {}

    /**
     * Get the channels the event should broadcast on.
     * Uses a private channel keyed by session ID.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.{$this->sessionId}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    /**
     * Data to broadcast with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'sender_name' => $this->senderName,
            'sender_type' => $this->senderType,
            'timestamp' => $this->timestamp,
            'metadata' => $this->metadata,
        ];
    }
}
