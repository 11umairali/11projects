<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'conversation_id' => $this->conversation_id,
            'direction' => $this->direction,
            'sender_type' => $this->sender_type,
            'type' => $this->type,
            'subject' => $this->subject,
            'body_text' => $this->body_text,
            'body_html' => $this->body_html,
            'from_email' => $this->from_email,
            'from_name' => $this->from_name,
            'to_emails' => $this->to_emails ?? [],
            'cc_emails' => $this->cc_emails ?? [],
            'ai_confidence' => $this->ai_confidence,
            'ai_model' => $this->ai_model,
            'ai_provider' => $this->ai_provider,
            'ai_status' => $this->ai_status,
            'sentiment' => $this->sentiment,
            'detected_language' => $this->detected_language,
            'delivery_status' => $this->delivery_status,
            'opens_count' => $this->opens_count,
            'clicks_count' => $this->clicks_count,
            'sender' => $this->whenLoaded('sender', function () {
                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name,
                    'email' => $this->sender->email,
                ];
            }),
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'file_name' => $a->file_name,
                    'file_type' => $a->file_type,
                    'file_size' => $a->file_size,
                ]);
            }),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
