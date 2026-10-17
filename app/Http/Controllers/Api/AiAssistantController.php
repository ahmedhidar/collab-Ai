<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ai\AskAiAssistantRequest;
use App\Http\Resources\AiMessageResource;
use App\Services\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function __construct(
        protected AiAssistantService $aiAssistantService
    ) {}

    /**
     * Interactive Q&A chat with CollabSpace AI.
     */
    public function chat(AskAiAssistantRequest $request): JsonResponse
    {
        $message = (string) ($request->validated('message') ?? $request->validated('prompt'));
        $projectId = $request->validated('project_id');
        $teamId = $request->validated('team_id');

        $result = $this->aiAssistantService->ask(
            $request->user(),
            $message,
            $projectId,
            $teamId
        );

        return $this->successResponse(
            data: new AiMessageResource($result),
            message: 'AI response generated successfully'
        );
    }

    /**
     * Get user AI interaction history.
     */
    public function history(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 20);
        $logs = $this->aiAssistantService->getHistory($request->user(), $limit);

        return $this->successResponse(
            data: $logs->map(fn ($log) => [
                'id' => $log->id,
                'query' => $log->prompt_query,
                'response' => $log->ai_response,
                'tokens_used' => $log->tokens_used,
                'execution_time_ms' => $log->execution_time_ms,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            message: 'AI chat history retrieved successfully'
        );
    }
}
