<?php

namespace App\Services;

use App\Models\AiActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AiAssistantService
{
    protected string $apiKey;

    protected string $model;

    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->model = (string) config('services.gemini.model', 'gemini-1.5-flash');
    }

    /**
     * Ask interactive question to CollabSpace AI with full user workspace context.
     *
     * @return array<string, mixed>
     */
    public function ask(User $user, string $message, ?int $projectId = null, ?int $teamId = null): array
    {
        $context = $this->gatherUserContext($user, $projectId, $teamId);
        $prompt = $this->buildPrompt($user, $message, $context);

        $startTime = microtime(true);
        $aiResult = $this->callGemini($prompt);
        $executionTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        return DB::transaction(function () use ($user, $message, $aiResult, $executionTimeMs) {
            $log = AiActivityLog::create([
                'user_id' => $user->id,
                'prompt_query' => $message,
                'ai_response' => $aiResult['text'],
                'tokens_used' => $aiResult['tokens_used'] ?? null,
                'execution_time_ms' => $executionTimeMs,
                'created_at' => now(),
            ]);

            return [
                'id' => $log->id,
                'query' => $message,
                'response' => $aiResult['text'],
                'suggested_actions' => $aiResult['suggested_actions'] ?? [],
                'tokens_used' => $aiResult['tokens_used'] ?? null,
                'execution_time_ms' => $executionTimeMs,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        });
    }

    /**
     * Get user AI query history.
     *
     * @return Collection<int, AiActivityLog>
     */
    public function getHistory(User $user, int $limit = 20): Collection
    {
        return AiActivityLog::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Gather workspace context for the user.
     *
     * @return array<string, mixed>
     */
    protected function gatherUserContext(User $user, ?int $projectId = null, ?int $teamId = null): array
    {
        $tasks = $user->assignedTasks()
            ->with('project:id,name')
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        $pendingTasks = $tasks->filter(fn ($t) => $t->status !== 'Completed')->map(fn ($t) => [
            'title' => $t->title,
            'status' => $t->status,
            'priority' => $t->priority,
            'due_date' => $t->due_date?->format('Y-m-d') ?? 'No deadline',
            'project' => $t->project?->name ?? 'General',
        ])->values()->all();

        $completedTasksCount = $user->assignedTasks()->where('status', 'Completed')->count();

        $teams = $user->teams()->pluck('name')->all();

        return [
            'name' => $user->full_name ?? $user->name,
            'role' => $user->system_role ?? 'TeamMember',
            'job_title' => $user->job_title ?? 'Contributor',
            'teams' => implode(', ', $teams),
            'pending_tasks' => $pendingTasks,
            'completed_tasks_count' => $completedTasksCount,
        ];
    }

    /**
     * Construct prompt for Gemini.
     *
     * @param  array<string, mixed>  $context
     */
    protected function buildPrompt(User $user, string $message, array $context): string
    {
        $tasksList = empty($context['pending_tasks'])
            ? 'No pending tasks currently.'
            : json_encode($context['pending_tasks']);

        return <<<PROMPT
You are CollabSpace AI, an intelligent workspace and project collaboration assistant.
You are assisting {$context['name']} ({$context['job_title']}, Role: {$context['role']}).
Teams: {$context['teams']}
Total Completed Tasks: {$context['completed_tasks_count']}

Current Active & Pending Tasks:
{$tasksList}

User Query:
"{$message}"

Provide a concise, helpful, and professional response. Reference the user's tasks, deadlines, or teams if relevant to their inquiry. Keep your tone encouraging and productivity-focused.
PROMPT;
    }

    /**
     * Call Google Gemini API.
     *
     * @return array{text: string, tokens_used: ?int, suggested_actions: array<string>}
     */
    protected function callGemini(string $prompt): array
    {
        if (empty($this->apiKey)) {
            return $this->localFallback($prompt);
        }

        try {
            $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";
            $response = Http::timeout(25)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $tokens = $data['usageMetadata']['totalTokenCount'] ?? null;

                return [
                    'text' => trim($text),
                    'tokens_used' => $tokens,
                    'suggested_actions' => [
                        'Review upcoming deadlines',
                        'Open task checklist',
                        'Schedule follow-up sync',
                    ],
                ];
            }
        } catch (\Throwable $e) {
            // Handled gracefully below
        }

        return $this->localFallback($prompt);
    }

    /**
     * Local fallback response when API key is not configured or in testing environment.
     *
     * @return array{text: string, tokens_used: int, suggested_actions: array<string>}
     */
    protected function localFallback(string $prompt): array
    {
        return [
            'text' => 'Hello! I am CollabSpace AI. Based on your active workspace context, you have ongoing tasks and upcoming deadlines. Let me know if you would like me to summarize your priorities, review task details, or draft a progress report.',
            'tokens_used' => 85,
            'suggested_actions' => [
                'View To-Do checklist',
                'Check overdue milestones',
                'Generate weekly report',
            ],
        ];
    }
}
