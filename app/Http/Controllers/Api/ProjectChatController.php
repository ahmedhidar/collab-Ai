<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Chat\SendProjectMessageRequest;
use App\Http\Resources\ProjectMessageResource;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProjectChatController extends Controller
{
    public function __construct(
        protected ChatService $chatService
    ) {}

    /**
     * List all available chat channels for a project.
     */
    public function channels(int $projectId, Request $request): JsonResponse
    {
        $channels = $this->chatService->getProjectChannels($projectId, $request->user());

        return $this->successResponse(
            data: $channels,
            message: 'Project channels retrieved successfully'
        );
    }

    /**
     * Retrieve paginated messages for a project channel.
     */
    public function messages(int $projectId, Request $request): JsonResponse
    {
        $channelName = $request->query('channel_name', 'general');
        $perPage = (int) $request->query('per_page', 25);

        $messages = $this->chatService->getChannelMessages(
            $projectId,
            $channelName,
            $request->user(),
            $perPage
        );

        return $this->successResponse(
            data: [
                'items' => ProjectMessageResource::collection($messages),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ],
            ],
            message: 'Channel messages retrieved successfully'
        );
    }

    /**
     * Post a new message to a project channel.
     */
    public function sendMessage(SendProjectMessageRequest $request, int $projectId): JsonResponse
    {
        $message = $this->chatService->sendMessage(
            $projectId,
            $request->user(),
            $request->validated(),
            $request->file('attachment')
        );

        return $this->successResponse(
            data: new ProjectMessageResource($message),
            message: 'Message sent successfully',
            statusCode: Response::HTTP_CREATED
        );
    }

    /**
     * Mark specific messages in a project channel as read.
     */
    public function markRead(Request $request, int $projectId): JsonResponse
    {
        $request->validate([
            'message_ids' => ['required', 'array'],
            'message_ids.*' => ['integer'],
        ]);

        $this->chatService->markMessagesAsRead(
            $projectId,
            $request->user(),
            $request->input('message_ids')
        );

        return $this->successResponse(
            message: 'Messages marked as read successfully'
        );
    }

    /**
     * Retrieve recent channels for the chat sidebar.
     */
    public function sidebar(Request $request): JsonResponse
    {
        $recent = $this->chatService->getRecentChannels($request->user());

        return $this->successResponse(
            data: $recent,
            message: 'Recent channels retrieved successfully'
        );
    }
}
