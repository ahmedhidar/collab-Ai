<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskService
{
    /**
     * Retrieve tasks based on filters with optional Kanban column grouping.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator|array<string, Collection>
     */
    public function getTasks(array $filters, ?int $currentUserId = null): LengthAwarePaginator|array
    {
        $query = Task::query()
            ->with(['project', 'assignedTeam', 'assignedUser', 'reviewer', 'creator'])
            ->withCount(['comments', 'files']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['assigned_team_id'])) {
            $query->where('assigned_team_id', $filters['assigned_team_id']);
        }

        if (! empty($filters['assigned_user_id'])) {
            $query->where('assigned_user_id', $filters['assigned_user_id']);
        }

        if (! empty($filters['my_tasks']) && $currentUserId) {
            $query->where('assigned_user_id', $currentUserId);
        }

        if (! empty($filters['kanban'])) {
            $tasks = $query->latest()->get();

            return [
                'Pending' => $tasks->where('status', 'Pending')->values(),
                'InProgress' => $tasks->where('status', 'InProgress')->values(),
                'InReview' => $tasks->where('status', 'InReview')->values(),
                'Completed' => $tasks->where('status', 'Completed')->values(),
            ];
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->latest()->paginate($perPage);
    }

    /**
     * Retrieve single task with complete relations, files, and nested comments.
     */
    public function getTaskById(int $id): Task
    {
        return Task::query()
            ->with([
                'project',
                'assignedTeam',
                'assignedUser',
                'reviewer',
                'creator',
                'files.uploader',
                'comments' => fn ($q) => $q->root()->with(['user', 'replies.user'])->latest(),
            ])
            ->withCount(['comments', 'files'])
            ->findOrFail($id);
    }

    /**
     * Create a new task.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTask(array $data, int $userId): Task
    {
        return DB::transaction(function () use ($data, $userId) {
            $task = Task::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'Pending',
                'priority' => $data['priority'] ?? 'Medium',
                'progress' => $data['progress'] ?? 0,
                'project_id' => $data['project_id'],
                'assigned_team_id' => $data['assigned_team_id'] ?? null,
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'reviewer_id' => $data['reviewer_id'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'created_by' => $userId,
            ]);

            return $task->load(['project', 'assignedTeam', 'assignedUser', 'reviewer', 'creator']);
        });
    }

    /**
     * Update an existing task.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTask(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $task->update(array_filter([
                'title' => $data['title'] ?? null,
                'description' => array_key_exists('description', $data) ? $data['description'] : null,
                'status' => $data['status'] ?? null,
                'priority' => $data['priority'] ?? null,
                'progress' => array_key_exists('progress', $data) ? $data['progress'] : null,
                'project_id' => $data['project_id'] ?? null,
                'assigned_team_id' => array_key_exists('assigned_team_id', $data) ? $data['assigned_team_id'] : null,
                'assigned_user_id' => array_key_exists('assigned_user_id', $data) ? $data['assigned_user_id'] : null,
                'reviewer_id' => array_key_exists('reviewer_id', $data) ? $data['reviewer_id'] : null,
                'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : null,
            ], fn ($val) => ! is_null($val)));

            return $task->fresh(['project', 'assignedTeam', 'assignedUser', 'reviewer', 'creator']);
        });
    }

    /**
     * Update task status with optional progress value.
     */
    public function updateStatus(Task $task, string $status, ?int $progress = null): Task
    {
        $updateData = ['status' => $status];

        if (! is_null($progress)) {
            $updateData['progress'] = $progress;
        } elseif ($status === 'Completed') {
            $updateData['progress'] = 100;
        }

        $task->update($updateData);

        return $task->fresh(['project', 'assignedTeam', 'assignedUser', 'reviewer', 'creator']);
    }

    /**
     * Delete a task and its comments.
     */
    public function deleteTask(Task $task): bool
    {
        return DB::transaction(function () use ($task) {
            $task->comments()->delete();

            return (bool) $task->delete();
        });
    }
}
