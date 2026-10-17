<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingDetailResource extends JsonResource
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
                    'email' => $this->organizer->email,
                ] : null;
            }),
            'participants' => MeetingParticipantResource::collection($this->whenLoaded('participants')),
            'participants_count' => $this->participants_count ?? $this->participants?->count() ?? 0,
            'transcripts' => MeetingTranscriptResource::collection($this->whenLoaded('transcripts')),
            'ai_summary' => new AiMeetingSummaryResource($this->whenLoaded('aiSummary')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
