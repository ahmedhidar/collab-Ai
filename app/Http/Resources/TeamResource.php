<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalTasks = (int) ($this->tasks_count ?? $this->tasks()->count());
        $completedTasks = (int) ($this->completed_tasks_count ?? $this->tasks()->where('status', 'Completed')->count());
        $completionRate = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'team_type' => $this->team_type,
            'members_count' => (int) ($this->members_count ?? $this->members()->count()),
            'active_projects_count' => (int) ($this->active_projects_count ?? $this->projects()->where('status', 'Active')->count()),
            'total_tasks_count' => $totalTasks,
            'completed_tasks_count' => $completedTasks,
            'completion_rate' => $completionRate,
            'members' => TeamMemberResource::collection($this->whenLoaded('members')),
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
