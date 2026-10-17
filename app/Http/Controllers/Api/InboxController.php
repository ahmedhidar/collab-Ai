<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Chat\SendInboxMessageRequest;
use App\Http\Requests\Api\Chat\StartInboxThreadRequest;
use App\Http\Resources\InboxMessageResource;
use App\Http\Resources\InboxThreadResource;
use App\Services\DirectMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InboxController extends Controller
{
    public function __construct(
        protected DirectMessageService $directMessageService
    ) {}

    /**
     * Display a listing of the authenticated user's 1-on-1 inbox threads.
     */
    public function threads(Request $request): JsonResponse
    {
        $threads = $this->directMessageService->getUserThreads($request->user());

        return $this->successResponse(
            data: InboxThreadResource::collection($threads),
            message: 'Inbox conversations retrieved successfully'
        );
    }

    /**
     * Start a new thread or retrieve an existing one with another user.
     */
    public function startThread(StartInboxThreadRequest $request): JsonResponse
    {
        $thread = $this->directMessageService->startOrGetThread(
            $request->user(),
            (int) $request->validated('recipient_id'),
            $request->validated('content')
        );

        return $this->successResponse(
            data: new InboxThreadResource($thread),
            message: 'Direct message conversation initialized successfully',
            statusCode: Response::HTTP_CREATED
        );
    }

    /**
     * Retrieve paginated message history for a specific thread.
     */
    public function messages(int $threadId, Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 30);
        $messages = $this->directMessageService->getThreadMessages(
            $threadId,
            $request->user(),
            $perPage
        );

        return $this->successResponse(
            data: [
                'items' => InboxMessageResource::collection($messages),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ],
            ],
            message: 'Direct messages retrieved successfully'
        );
    }

    /**
     * Send a direct message in a thread.
     */
    public function sendMessage(SendInboxMessageRequest $request, int $threadId): JsonResponse
    {
        $message = $this->directMessageService->sendMessage(
            $threadId,
            $request->user(),
            $request->validated(),
            $request->file('attachment')
        );

        return $this->successResponse(
            data: new InboxMessageResource($message),
            message: 'Direct message sent successfully',
            statusCode: Response::HTTP_CREATED
        );
    }

    /**
     * Mark all incoming messages in a thread as read.
     */
    public function markRead(int $threadId, Request $request): JsonResponse
    {
        $this->directMessageService->markThreadAsRead($threadId, $request->user());

        return $this->successResponse(
            message: 'Conversation marked as read successfully'
        );
    }
}
