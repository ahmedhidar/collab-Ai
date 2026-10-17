<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingTranscriptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'speaker_id' => $this->speaker_id,
            'speaker_name' => $this->speaker?->full_name ?? $this->speaker?->name ?? $this->speaker_name ?? 'Speaker',
            'speaker_avatar' => $this->speaker?->image ?? $this->speaker?->avatar ?? null,
            'time_offset' => $this->time_offset ?? '00:00',
            'content' => $this->content,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
