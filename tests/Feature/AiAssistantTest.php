<?php

namespace Tests\Feature;

use App\Models\AiActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.api_key' => 'test-api-key']);
        config(['services.gemini.model' => 'gemini-1.5-flash']);

        $this->user = User::create([
            'full_name' => 'Mohamed Salah',
            'email' => 'salah@collabspace.ai',
            'password' => bcrypt('password123'),
            'job_title' => 'UI/UX Designer',
            'system_role' => 'TeamMember',
        ]);

        $team = Team::create(['name' => 'UI/UX Team', 'team_type' => 'UI_UX']);
        $team->members()->attach($this->user->id);

        $this->project = Project::create([
            'name' => 'CollabSpace AI Assistant App',
            'status' => 'Active',
            'priority' => 'High',
            'created_by' => $this->user->id,
            'start_date' => now()->toDateString(),
            'deadline' => now()->addMonths(2)->toDateString(),
        ]);

        Task::create([
            'title' => 'Design new onboarding flow',
            'status' => 'InProgress',
            'priority' => 'High',
            'project_id' => $this->project->id,
            'assigned_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'due_date' => now()->addDays(3)->toDateString(),
            'progress' => 50,
        ]);

        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_user_can_chat_with_ai_assistant_with_gemini_api(): void
    {
        $geminiResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => "Hi Mohamed! You currently have 1 active task 'Design new onboarding flow' due in 3 days. Your progress is at 50%. I recommend prioritizing the wireframe iterations today to stay on track for your team.",
                            ],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => [
                'totalTokenCount' => 190,
            ],
        ];

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response($geminiResponse, 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/ai/chat', [
                'message' => 'What should I focus on today?',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'AI response generated successfully',
                'data' => [
                    'query' => 'What should I focus on today?',
                    'response' => "Hi Mohamed! You currently have 1 active task 'Design new onboarding flow' due in 3 days. Your progress is at 50%. I recommend prioritizing the wireframe iterations today to stay on track for your team.",
                    'tokens_used' => 190,
                ],
            ]);

        $this->assertNotEmpty($response->json('data.suggested_actions'));

        // Verify logged in ai_activity_logs
        $this->assertDatabaseHas('ai_activity_logs', [
            'user_id' => $this->user->id,
            'prompt_query' => 'What should I focus on today?',
        ]);
    }

    public function test_user_can_use_profile_ai_assistant_alias_route(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'All systems operational.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/profile/ai-assistant', [
                'message' => 'System status summary',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'query' => 'System status summary',
                    'response' => 'All systems operational.',
                ],
            ]);
    }

    public function test_user_can_view_ai_chat_history(): void
    {
        AiActivityLog::create([
            'user_id' => $this->user->id,
            'prompt_query' => 'Summary of my sprint',
            'ai_response' => 'You completed 5 tasks this sprint with high velocity.',
            'tokens_used' => 120,
            'execution_time_ms' => 450,
            'created_at' => now()->subHour(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/ai/history');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Summary of my sprint', $response->json('data.0.query'));
    }

    public function test_ai_assistant_falls_back_gracefully_when_no_api_key_or_offline(): void
    {
        config(['services.gemini.api_key' => '']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/ai/chat', [
                'message' => 'Hello Collabspace AI',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'query' => 'Hello Collabspace AI',
                ],
            ]);

        $this->assertStringContainsStringIgnoringCase('CollabSpace', $response->json('data.response'));
    }
}
