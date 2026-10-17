<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    /**
     * Get user profile with teams and metrics.
     */
    public function getProfile(User $user): User
    {
        return $user->load('teams');
    }

    /**
     * Update user personal info and experience settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data, ?string $ipAddress = null): User
    {
        return DB::transaction(function () use ($user, $data, $ipAddress) {
            $changed = [];
            $fields = [
                'full_name',
                'email',
                'phone',
                'job_title',
                'years_of_experience',
                'bio',
                'availability_status',
                'image',
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $user->{$field} = $data[$field];
                    $changed[$field] = $data[$field];
                }
            }

            $user->save();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'updated_profile',
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'details' => $changed,
                'ip_address' => $ipAddress,
                'created_at' => now(),
            ]);

            return $user->load('teams');
        });
    }

    /**
     * Upload and update user avatar image.
     */
    public function updateAvatar(User $user, UploadedFile $file, ?string $ipAddress = null): User
    {
        return DB::transaction(function () use ($user, $file, $ipAddress) {
            $path = $file->store('avatars', 'public');
            $url = Storage::url($path);

            $user->update(['image' => $url]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'updated_avatar',
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'details' => ['avatar_url' => $url],
                'ip_address' => $ipAddress,
                'created_at' => now(),
            ]);

            return $user->load('teams');
        });
    }

    /**
     * Get user task checklist segregated into To-Do and Done.
     *
     * @return array{todo: Collection<int, Task>, done: Collection<int, Task>}
     */
    public function getTaskChecklist(User $user): array
    {
        $tasks = Task::query()
            ->where('assigned_user_id', $user->id)
            ->with(['project:id,name'])
            ->get();

        $todo = $tasks->filter(fn ($t) => $t->status !== 'Completed')
            ->sortBy('due_date')
            ->values();

        $done = $tasks->filter(fn ($t) => $t->status === 'Completed')
            ->sortByDesc('updated_at')
            ->values();

        return [
            'todo' => $todo,
            'done' => $done,
        ];
    }

    /**
     * Quick toggle a checklist task between To-Do and Done.
     */
    public function toggleTask(User $user, int $taskId, ?string $ipAddress = null): Task
    {
        return DB::transaction(function () use ($user, $taskId, $ipAddress) {
            $task = Task::where('id', $taskId)
                ->where(function ($q) use ($user) {
                    $q->where('assigned_user_id', $user->id)
                        ->orWhere('created_by', $user->id);
                })
                ->firstOrFail();

            $isCompleted = $task->status === 'Completed';
            $newStatus = $isCompleted ? 'InProgress' : 'Completed';
            $newProgress = $isCompleted ? 50 : 100;

            $task->update([
                'status' => $newStatus,
                'progress' => $newProgress,
                'updated_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => $isCompleted ? 'reopened_task' : 'completed_task',
                'entity_type' => 'Task',
                'entity_id' => $task->id,
                'details' => ['previous_status' => $task->status, 'new_status' => $newStatus],
                'ip_address' => $ipAddress,
                'created_at' => now(),
            ]);

            return $task->load('project:id,name');
        });
    }

    /**
     * Get user activity audit timeline.
     *
     * @return Collection<int, AuditLog>
     */
    public function getActivityTimeline(User $user, int $limit = 20): Collection
    {
        return AuditLog::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
