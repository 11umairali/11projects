<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'channel' => $this->channel,
            'status' => $this->status,
            'priority' => $this->priority,
            'subject' => $this->subject,
            'preview' => $this->preview,
            'sentiment' => $this->sentiment,
            'is_starred' => $this->is_starred,
            'is_pinned' => $this->is_pinned,
            'is_read' => $this->is_read,
            'is_ai_handled' => $this->is_ai_handled,
            'messages_count' => $this->messages_count,
            'ai_replies_count' => $this->ai_replies_count,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'assigned_to' => $this->whenLoaded('assignedTo', function () {
                return [
                    'id' => $this->assignedTo->id,
                    'name' => $this->assignedTo->name,
                    'email' => $this->assignedTo->email,
                ];
            }),
            'tags' => $this->tags ?? [],
            'snoozed_until' => $this->snoozed_until?->toIso8601String(),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'first_response_at' => $this->first_response_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
