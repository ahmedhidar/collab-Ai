<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InboxMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'thread_id' => $this->thread_id,
            'sender_id' => $this->sender_id,
            'content' => $this->content,
            'is_read' => (bool) $this->is_read,
            'parent_message_id' => $this->parent_message_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->format('H:i'),
            'sender' => [
                'id' => $this->sender?->id,
                'name' => $this->sender?->full_name ?? $this->sender?->name,
                'avatar' => $this->sender?->image,
                'job_title' => $this->sender?->job_title,
            ],
            'attachment' => $this->whenLoaded('attachment', function () {
                if (! $this->attachment) {
                    return null;
                }

                return [
                    'id' => $this->attachment->id,
                    'file_name' => $this->attachment->file_name,
                    'file_size_bytes' => $this->attachment->file_size_bytes,
                    'file_path' => $this->attachment->file_path,
                    'mime_type' => $this->attachment->mime_type,
                ];
            }),
            'replies' => InboxMessageResource::collection($this->whenLoaded('replies')),
        ];
    }
}
