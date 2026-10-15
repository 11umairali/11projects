<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'subject' => $this->subject,
            'preview_text' => $this->preview_text,
            'audience_type' => $this->audience_type,
            'recipients_count' => $this->recipients_count,
            'stats' => [
                'sent' => $this->sent_count,
                'delivered' => $this->delivered_count,
                'opened' => $this->opened_count,
                'clicked' => $this->clicked_count,
                'bounced' => $this->bounced_count,
                'unsubscribed' => $this->unsubscribed_count,
                'open_rate' => $this->open_rate,
                'click_rate' => $this->click_rate,
            ],
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),
            'email_account_id' => $this->email_account_id,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
