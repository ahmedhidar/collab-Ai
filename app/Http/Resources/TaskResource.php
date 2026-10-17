<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'progress' => $this->progress ?? 0,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'due_date_human' => $this->due_date?->format('M d'),
            'comments_count' => (int) ($this->comments_count ?? $this->comments()->count()),
            'files_count' => (int) ($this->files_count ?? $this->files()->count()),
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                ];
            }),
            'assigned_team' => $this->whenLoaded('assignedTeam', function () {
                return [
                    'id' => $this->assignedTeam->id,
                    'name' => $this->assignedTeam->name,
                    'team_type' => $this->assignedTeam->team_type,
                ];
            }),
            'assigned_user' => $this->whenLoaded('assignedUser', function () {
                return [
                    'id' => $this->assignedUser->id,
                    'name' => $this->assignedUser->full_name ?? $this->assignedUser->name,
                    'avatar' => $this->assignedUser->image ?? null,
                    'job_title' => $this->assignedUser->job_title,
                ];
            }),
            'reviewer' => $this->whenLoaded('reviewer', function () {
                return [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->full_name ?? $this->reviewer->name,
                    'avatar' => $this->reviewer->image ?? null,
                    'job_title' => $this->reviewer->job_title,
                ];
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->full_name ?? $this->creator->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
