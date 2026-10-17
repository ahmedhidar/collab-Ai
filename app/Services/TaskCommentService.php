<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskCommentService
{
    /**
     * Retrieve all top-level comments with recursive replies for a task.
     */
    public function getTaskComments(int $taskId): Collection
    {
        return TaskComment::query()
            ->where('task_id', $taskId)
            ->root()
            ->with(['user', 'replies.user'])
            ->latest('created_at')
            ->get();
    }

    /**
     * Create a new comment or a reply to an existing comment.
     *
     * @param  array{comment: string, parent_id?: int|null}  $data
     */
    public function createComment(int $taskId, int $userId, array $data): TaskComment
    {
        // Verify task exists
        Task::findOrFail($taskId);

        if (! empty($data['parent_id'])) {
            $parent = TaskComment::where('id', $data['parent_id'])
                ->where('task_id', $taskId)
                ->first();

            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The selected parent comment does not belong to this task.',
                ]);
            }
        }

        return DB::transaction(function () use ($taskId, $userId, $data) {
            $comment = TaskComment::create([
                'task_id' => $taskId,
                'user_id' => $userId,
                'parent_id' => $data['parent_id'] ?? null,
                'comment' => $data['comment'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $comment->load(['user', 'replies']);
        });
    }

    /**
     * Delete a comment and its child replies.
     */
    public function deleteComment(TaskComment $comment, User $user): bool
    {
        $canDelete = $user->isAdmin() ||
            $user->isProjectManager() ||
            $comment->user_id === $user->id;

        if (! $canDelete) {
            abort(403, 'You do not have permission to delete this comment.');
        }

        return DB::transaction(function () use ($comment) {
            $comment->replies()->delete();

            return (bool) $comment->delete();
        });
    }
}
