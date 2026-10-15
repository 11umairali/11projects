<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KbDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'title' => $this->title,
            'content' => $this->content,
            'question' => $this->question,
            'answer' => $this->answer,
            'category' => $this->category,
            'is_priority' => $this->is_priority,
            'file_path' => $this->file_path,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'source_url' => $this->source_url,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'chunks_count' => $this->chunks_count,
            'usage_count' => $this->usage_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
