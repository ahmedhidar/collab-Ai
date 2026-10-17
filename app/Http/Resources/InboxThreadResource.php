<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InboxThreadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUserId = (int) $request->user()?->id;
        $otherUser = $this->getOtherUser($currentUserId);
        $latest = $this->latestMessage;
        $unreadCount = $this->messages()
            ->where('sender_id', '!=', $currentUserId)
            ->where('is_read', false)
            ->count();

        return [
            'id' => $this->id,
            'other_user' => [
                'id' => $otherUser?->id,
                'name' => $otherUser?->full_name ?? $otherUser?->name,
                'avatar' => $otherUser?->image,
                'job_title' => $otherUser?->job_title,
                'availability_status' => $otherUser?->availability_status ?? 'Available',
            ],
            'latest_message' => $latest ? [
                'id' => $latest->id,
                'content' => $latest->content,
                'sender_id' => $latest->sender_id,
                'is_read' => (bool) $latest->is_read,
                'created_at' => $latest->created_at?->toIso8601String(),
                'created_at_human' => $latest->created_at?->diffForHumans(),
            ] : null,
            'unread_count' => $unreadCount,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
        ];
    }
}
