<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'meeting_date' => $this->meeting_date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'meeting_link' => $this->meeting_link,
            'status' => $this->status ?? ($this->is_cancelled ? 'cancelled' : 'scheduled'),
            'is_cancelled' => (bool) $this->is_cancelled,
            'project' => $this->whenLoaded('project', function () {
                return $this->project ? [
                    'id' => $this->project->id,
                    'name' => $this->project->name ?? $this->project->title,
                    'title' => $this->project->name ?? $this->project->title,
                ] : null;
            }),
            'team' => $this->whenLoaded('team', function () {
                return $this->team ? [
                    'id' => $this->team->id,
                    'name' => $this->team->name,
                ] : null;
            }),
            'organizer' => $this->whenLoaded('organizer', function () {
                return $this->organizer ? [
                    'id' => $this->organizer->id,
                    'name' => $this->organizer->full_name ?? $this->organizer->name,
                    'avatar' => $this->organizer->image ?? $this->organizer->avatar ?? null,
                ] : null;
            }),
            'participants' => MeetingParticipantResource::collection($this->whenLoaded('participants')),
            'participants_count' => $this->participants_count ?? $this->participants?->count() ?? 0,
            'has_summary' => (bool) ($this->ai_summary_exists ?? ($this->relationLoaded('aiSummary') && $this->aiSummary !== null)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
