<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $completedTasks = $this->assignedTasks()->where('status', 'Completed')->count();
        $totalTasks = $this->assignedTasks()->count();
        // Estimated 2.5 hours per completed task or custom calculation matching Figma card (e.g., 345 hours)
        $hoursWorked = max(0, (int) ($completedTasks * 2.5));

        return [
            'id' => $this->id,
            'full_name' => $this->full_name ?? $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->image ?? $this->avatar ?? null,
            'job_title' => $this->job_title ?? 'Team Member',
            'years_of_experience' => (int) ($this->years_of_experience ?? 0),
            'bio' => $this->bio,
            'availability_status' => $this->availability_status ?? 'Available',
            'system_role' => $this->system_role ?? 'TeamMember',
            'joined_date' => $this->created_at ? 'Joined '.$this->created_at->format('M Y') : null,
            'stats' => [
                'completed_tasks' => $completedTasks,
                'total_tasks' => $totalTasks,
                'hours_worked' => $hoursWorked,
                'active_projects_count' => $this->teams()->withCount('projects')->get()->sum('projects_count'),
            ],
            'teams' => $this->whenLoaded('teams', function () {
                return $this->teams->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'team_type' => $t->team_type,
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
