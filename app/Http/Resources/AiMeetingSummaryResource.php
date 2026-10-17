<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiMeetingSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'summary' => $this->summary_text,
            'key_points' => $this->key_points ?? [],
            'decisions' => $this->decisions_made ?? [],
            'action_items' => $this->action_items ?? [],
            'risks' => $this->risks_identified ?? [],
            'next_steps' => $this->next_steps,
            'generated_at' => $this->generated_at?->toIso8601String(),
        ];
    }
}
