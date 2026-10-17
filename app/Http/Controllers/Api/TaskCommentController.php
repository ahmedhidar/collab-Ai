<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Task\StoreTaskCommentRequest;
use App\Http\Resources\TaskCommentResource;
use App\Models\TaskComment;
use App\Services\TaskCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaskCommentController extends Controller
{
    public function __construct(
        protected TaskCommentService $commentService
    ) {}

    /**
     * Display a listing of comments for the specified task.
     */
    public function index(int $taskId): JsonResponse
    {
        $comments = $this->commentService->getTaskComments($taskId);

        return $this->successResponse(
            data: TaskCommentResource::collection($comments),
            message: 'Task comments retrieved successfully'
        );
    }

    /**
     * Store a newly created comment or reply for the specified task.
     */
    public function store(StoreTaskCommentRequest $request, int $taskId): JsonResponse
    {
        $comment = $this->commentService->createComment(
            $taskId,
            (int) $request->user()->id,
            $request->validated()
        );

        return $this->successResponse(
            data: new TaskCommentResource($comment),
            message: 'Comment posted successfully',
            statusCode: Response::HTTP_CREATED
        );
    }

    /**
     * Remove the specified comment.
     */
    public function destroy(int $taskId, int $commentId, Request $request): JsonResponse
    {
        $comment = TaskComment::where('id', $commentId)
            ->where('task_id', $taskId)
            ->firstOrFail();

        $this->commentService->deleteComment($comment, $request->user());

        return $this->successResponse(
            message: 'Comment deleted successfully'
        );
    }
}
