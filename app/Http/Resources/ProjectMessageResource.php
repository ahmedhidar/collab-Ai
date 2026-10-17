<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'channel_name' => $this->channel_name ?? 'general',
            'team_id' => $this->team_id,
            'content' => $this->content,
            'is_edited' => (bool) $this->is_edited,
            'parent_message_id' => $this->parent_message_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->format('H:i'),
            'sender' => [
                'id' => $this->sender?->id,
                'name' => $this->sender?->full_name ?? $this->sender?->name,
                'avatar' => $this->sender?->image,
                'job_title' => $this->sender?->job_title,
                'role' => $this->sender?->system_role,
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
            'mentions' => $this->whenLoaded('mentions', function () {
                return $this->mentions->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->full_name ?? $user->name,
                ]);
            }),
            'replies_count' => (int) ($this->replies_count ?? $this->replies()->count()),
            'read_receipts_count' => (int) ($this->read_receipts_count ?? $this->readReceipts()->count()),
            'read_by_me' => $currentUserId ? $this->readReceipts()->where('user_id', $currentUserId)->exists() : false,
            'replies' => ProjectMessageResource::collection($this->whenLoaded('replies')),
        ];
    }
}
