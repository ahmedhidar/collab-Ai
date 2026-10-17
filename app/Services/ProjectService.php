<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    /**
     * Retrieve paginated projects filtered by search, status, and priority.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getProjects(array $filters): LengthAwarePaginator
    {
        $query = Project::query()
            ->with(['creator', 'teams'])
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'Completed'),
                'files',
            ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        $sort = $filters['sort'] ?? 'newest';
        match ($sort) {
            'oldest' => $query->oldest(),
            'name' => $query->orderBy('name', 'asc'),
            'deadline' => $query->orderBy('deadline', 'asc'),
            default => $query->latest(),
        };

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * Find project by ID with full detailed overview relations.
     */
    public function getProjectDetail(int $id): Project
    {
        return Project::query()
            ->with([
                'creator',
                'teams.members',
                'files.uploader',
            ])
            ->withCount([
                'tasks',
                'tasks as pending_tasks_count' => fn ($q) => $q->where('status', 'Pending'),
                'tasks as in_progress_tasks_count' => fn ($q) => $q->where('status', 'In Progress'),
                'tasks as in_review_tasks_count' => fn ($q) => $q->where('status', 'In Review'),
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'Completed'),
                'files',
            ])
            ->findOrFail($id);
    }

    /**
     * Create a new project.
     *
     * @param  array<string, mixed>  $data
     */
    public function createProject(array $data, int $userId): Project
    {
        return DB::transaction(function () use ($data, $userId) {
            $project = Project::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'priority' => $data['priority'] ?? 'Medium',
                'status' => $data['status'] ?? 'Active',
                'created_by' => $userId,
            ]);

            if (! empty($data['team_ids'])) {
                $project->teams()->sync($data['team_ids']);
            }

            return $project->load(['creator', 'teams']);
        });
    }

    /**
     * Update an existing project.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProject(Project $project, array $data): Project
    {
        $project->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => array_key_exists('description', $data) ? $data['description'] : null,
            'start_date' => array_key_exists('start_date', $data) ? $data['start_date'] : null,
            'deadline' => array_key_exists('deadline', $data) ? $data['deadline'] : null,
            'priority' => $data['priority'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($val) => ! is_null($val)));

        return $project->fresh(['creator', 'teams']);
    }

    /**
     * Delete a project.
     */
    public function deleteProject(Project $project): bool
    {
        return DB::transaction(function () use ($project) {
            $project->teams()->detach();

            return (bool) $project->delete();
        });
    }

    /**
     * Assign a team to the project.
     */
    public function assignTeam(Project $project, int $teamId): Project
    {
        $project->teams()->syncWithoutDetaching([
            $teamId => ['assigned_at' => now()],
        ]);

        return $project->fresh(['teams']);
    }

    /**
     * Remove a team from the project.
     */
    public function removeTeam(Project $project, int $teamId): Project
    {
        $project->teams()->detach($teamId);

        return $project->fresh(['teams']);
    }
}
