<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
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
        $progress = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'deadline' => $this->deadline?->format('Y-m-d'),
            'priority' => $this->priority,
            'status' => $this->status,
            'progress_percentage' => $progress,
            'total_tasks_count' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'files_count' => (int) ($this->files_count ?? $this->files()->count()),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'teams' => $this->whenLoaded('teams', function () {
                return $this->teams->map(fn ($team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'team_type' => $team->team_type,
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
