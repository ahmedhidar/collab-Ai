<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Task\StoreTaskRequest;
use App\Http\Requests\Api\Task\TaskFilterRequest;
use App\Http\Requests\Api\Task\UpdateTaskRequest;
use App\Http\Requests\Api\Task\UpdateTaskStatusRequest;
use App\Http\Resources\TaskDetailResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService
    ) {}

    /**
     * Display a listing of tasks with filtering and Kanban grouping.
     */
    public function index(TaskFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $tasks = $this->taskService->getTasks($filters, (int) $request->user()?->id);

        if (! empty($filters['kanban'])) {
            return $this->successResponse(
                data: [
                    'columns' => [
                        'Pending' => TaskResource::collection($tasks['Pending']),
                        'InProgress' => TaskResource::collection($tasks['InProgress']),
                        'InReview' => TaskResource::collection($tasks['InReview']),
                        'Completed' => TaskResource::collection($tasks['Completed']),
                    ],
                ],
                message: 'Kanban tasks retrieved successfully'
            );
        }

        return $this->successResponse(
            data: [
                'items' => TaskResource::collection($tasks),
                'pagination' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                ],
            ],
            message: 'Tasks retrieved successfully'
        );
    }

    /**
     * Store a newly created task.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->createTask(
            $request->validated(),
            (int) $request->user()->id
        );

        return $this->successResponse(
            data: new TaskResource($task),
            message: 'Task created successfully',
            statusCode: Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified task with details and comments.
     */
    public function show(int $id): JsonResponse
    {
        $task = $this->taskService->getTaskById($id);

        return $this->successResponse(
            data: new TaskDetailResource($task),
            message: 'Task details retrieved successfully'
        );
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $updatedTask = $this->taskService->updateTask($task, $request->validated());

        return $this->successResponse(
            data: new TaskResource($updatedTask),
            message: 'Task updated successfully'
        );
    }

    /**
     * Update only task status and progress (Kanban drag-and-drop transition).
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): JsonResponse
    {
        $updatedTask = $this->taskService->updateStatus(
            $task,
            $request->validated('status'),
            $request->validated('progress')
        );

        return $this->successResponse(
            data: new TaskResource($updatedTask),
            message: 'Task status updated successfully'
        );
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Task $task): JsonResponse
    {
        $this->taskService->deleteTask($task);

        return $this->successResponse(
            message: 'Task deleted successfully'
        );
    }
}
