<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class MeetingAiTest extends TestCase
{
    use RefreshDatabase;

    protected User $organizer;

    protected User $outsider;

    protected Meeting $meeting;

    protected string $organizerToken;

    protected string $outsiderToken;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.api_key' => 'test-fake-gemini-key']);
        config(['services.gemini.model' => 'gemini-1.5-flash']);

        $this->organizer = User::create([
            'full_name' => 'Mohanad Wahib',
            'email' => 'mohanad@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
        ]);

        $this->outsider = User::create([
            'full_name' => 'Outsider User',
            'email' => 'outsider@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
        ]);

        $team = Team::create(['name' => 'Product Team', 'team_type' => 'Frontend']);
        $team->members()->attach($this->organizer->id);

        $project = Project::create([
            'name' => 'CollabSpace AI Assistant',
            'status' => 'Active',
            'priority' => 'High',
            'created_by' => $this->organizer->id,
            'start_date' => now()->toDateString(),
            'deadline' => now()->addMonths(2)->toDateString(),
        ]);
        $project->teams()->attach($team->id);

        $this->meeting = Meeting::create([
            'title' => 'Design Review',
            'meeting_date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'meeting_link' => 'https://meet.collabspace.ai/design-review',
            'project_id' => $project->id,
            'team_id' => $team->id,
            'organizer_id' => $this->organizer->id,
            'status' => 'completed',
        ]);
        $this->meeting->participants()->attach($this->organizer->id, ['status' => 'accepted', 'attended' => true]);

        $this->organizerToken = JWTAuth::fromUser($this->organizer);
        $this->outsiderToken = JWTAuth::fromUser($this->outsider);
    }

    public function test_generate_ai_summary_success_with_gemini_api(): void
    {
        // Add sample transcripts from Figma design
        MeetingTranscript::create([
            'meeting_id' => $this->meeting->id,
            'speaker_name' => 'Mohanad Wahib',
            'time_offset' => '00:01',
            'content' => 'Good morning everyone. Thanks for joining on time. Today we will focus on reviewing the latest design updates.',
        ]);
        MeetingTranscript::create([
            'meeting_id' => $this->meeting->id,
            'speaker_name' => 'Ahmed Hassan',
            'time_offset' => '00:40',
            'content' => 'Morning. I went through the new homepage design, and overall it looks cleaner, but I think we still have some usability issues.',
        ]);
        MeetingTranscript::create([
            'meeting_id' => $this->meeting->id,
            'speaker_name' => 'Omar Khaled',
            'time_offset' => '01:23',
            'content' => 'I agree. The layout is better, but the call-to-action is not very clear.',
        ]);

        $mockGeminiPayload = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'summary' => 'The team reviewed the latest homepage design and agreed on the proposed layout.',
                                    'key_points' => [
                                        'The team reviewed the latest homepage design and agreed on the proposed layout.',
                                        'Several usability improvements were suggested for the sign-up flow.',
                                        'Final color decisions were postponed to the next meeting.',
                                        'The design team was asked to update the wireframes based on feedback.',
                                        'A follow-up review meeting was scheduled for next week.',
                                    ],
                                    'decisions' => [
                                        'Adopt the new cleaner homepage wireframe layout.',
                                    ],
                                    'action_items' => [
                                        'Design team to update CTA hierarchy and sign-up flow.',
                                        'Schedule follow-up review meeting for next week.',
                                    ],
                                    'risks' => [
                                        'Sign-up flow might experience drop-offs if too complex.',
                                    ],
                                    'next_steps' => 'A follow-up review meeting was scheduled for next week.',
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => [
                'totalTokenCount' => 245,
            ],
        ];

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response($mockGeminiPayload, 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->organizerToken)
            ->postJson("/api/meetings/{$this->meeting->id}/generate-summary");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'AI meeting summary generated successfully',
                'data' => [
                    'meeting_id' => $this->meeting->id,
                    'summary' => 'The team reviewed the latest homepage design and agreed on the proposed layout.',
                    'key_points' => [
                        'The team reviewed the latest homepage design and agreed on the proposed layout.',
                        'Several usability improvements were suggested for the sign-up flow.',
                        'Final color decisions were postponed to the next meeting.',
                        'The design team was asked to update the wireframes based on feedback.',
                        'A follow-up review meeting was scheduled for next week.',
                    ],
                    'decisions' => [
                        'Adopt the new cleaner homepage wireframe layout.',
                    ],
                    'action_items' => [
                        'Design team to update CTA hierarchy and sign-up flow.',
                        'Schedule follow-up review meeting for next week.',
                    ],
                    'risks' => [
                        'Sign-up flow might experience drop-offs if too complex.',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('ai_meeting_summaries', [
            'meeting_id' => $this->meeting->id,
            'summary_text' => 'The team reviewed the latest homepage design and agreed on the proposed layout.',
        ]);

        $this->assertDatabaseHas('ai_activity_logs', [
            'user_id' => $this->organizer->id,
        ]);
    }

    public function test_generate_ai_summary_fails_when_meeting_has_no_transcripts(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->organizerToken)
            ->postJson("/api/meetings/{$this->meeting->id}/generate-summary");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation error',
                'errors' => [
                    'transcripts' => [
                        'Cannot generate AI summary without meeting transcripts. Please upload or record transcripts first.',
                    ],
                ],
            ]);
    }

    public function test_generate_ai_summary_parses_markdown_wrapped_json_response(): void
    {
        MeetingTranscript::create([
            'meeting_id' => $this->meeting->id,
            'speaker_name' => 'Mohanad Wahib',
            'time_offset' => '00:01',
            'content' => 'Reviewing the API documentation.',
        ]);

        $wrappedJson = "```json\n".json_encode([
            'summary' => 'API documentation was finalized.',
            'key_points' => ['Endpoints reviewed', 'Security specs approved'],
            'decisions' => ['Use JWT tokens'],
            'action_items' => ['Publish docs'],
            'risks' => ['None'],
            'next_steps' => 'Deploy next sprint',
        ])."\n```";

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $wrappedJson],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Test alias route: /api/meetings/{id}/ai-summary
        $response = $this->withHeader('Authorization', 'Bearer '.$this->organizerToken)
            ->postJson("/api/meetings/{$this->meeting->id}/ai-summary");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'summary' => 'API documentation was finalized.',
                    'key_points' => ['Endpoints reviewed', 'Security specs approved'],
                    'decisions' => ['Use JWT tokens'],
                ],
            ]);
    }

    public function test_outsider_cannot_generate_summary_for_private_meeting(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->outsiderToken)
            ->postJson("/api/meetings/{$this->meeting->id}/generate-summary");

        $response->assertStatus(403);
    }
}
