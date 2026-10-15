<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'avatar_path' => $this->avatar_path,
            'city' => $this->city,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'lead_score' => $this->lead_score,
            'status' => $this->status,
            'custom_fields' => $this->custom_fields ?? [],
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'conversations_count' => $this->whenCounted('conversations'),
            'deals_count' => $this->whenCounted('deals'),
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'unsubscribed_at' => $this->unsubscribed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
