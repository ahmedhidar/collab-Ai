<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $worker;

    protected User $outsider;

    protected Project $project;

    protected Team $team;

    protected string $managerToken;

    protected string $workerToken;

    protected string $outsiderToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::create([
            'full_name' => 'Mohamed Salah',
            'email' => 'salah@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'ProjectManager',
        ]);

        $this->worker = User::create([
            'full_name' => 'Mohanad Wahib',
            'email' => 'wahib@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
        ]);

        $this->outsider = User::create([
            'full_name' => 'Outsider User',
            'email' => 'outsider@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
        ]);

        $this->team = Team::create(['name' => 'UI/UX Design', 'team_type' => 'UI_UX']);
        $this->team->members()->attach([$this->manager->id, $this->worker->id]);

        $this->project = Project::create([
            'name' => 'E-Commerce Platform',
            'description' => 'Flagship ecommerce portal',
            'status' => 'Active',
            'priority' => 'High',
            'created_by' => $this->manager->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'deadline' => now()->addMonths(2)->toDateString(),
        ]);
        $this->project->teams()->attach($this->team->id);

        // Seed some tasks
        Task::create([
            'title' => 'Wireframes',
            'status' => 'Completed',
            'priority' => 'High',
            'project_id' => $this->project->id,
            'assigned_team_id' => $this->team->id,
            'assigned_user_id' => $this->worker->id,
            'created_by' => $this->manager->id,
            'due_date' => now()->subDays(5)->toDateString(),
            'progress' => 100,
        ]);
        Task::create([
            'title' => 'Cart Component',
            'status' => 'Completed',
            'priority' => 'Medium',
            'project_id' => $this->project->id,
            'assigned_team_id' => $this->team->id,
            'assigned_user_id' => $this->worker->id,
            'created_by' => $this->manager->id,
            'due_date' => now()->subDays(2)->toDateString(),
            'progress' => 100,
        ]);
        Task::create([
            'title' => 'Payment Integration',
            'status' => 'InProgress',
            'priority' => 'Critical',
            'project_id' => $this->project->id,
            'assigned_team_id' => $this->team->id,
            'assigned_user_id' => $this->worker->id,
            'created_by' => $this->manager->id,
            'due_date' => now()->addDays(10)->toDateString(),
            'progress' => 60,
        ]);
        Task::create([
            'title' => 'QA Testing',
            'status' => 'Pending',
            'priority' => 'Low',
            'project_id' => $this->project->id,
            'assigned_team_id' => $this->team->id,
            'assigned_user_id' => null,
            'created_by' => $this->manager->id,
            'due_date' => now()->addDays(20)->toDateString(),
            'progress' => 0,
        ]);

        $this->managerToken = JWTAuth::fromUser($this->manager);
        $this->workerToken = JWTAuth::fromUser($this->worker);
        $this->outsiderToken = JWTAuth::fromUser($this->outsider);
    }

    public function test_user_can_get_analytics_kpi_cards(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->managerToken)
            ->getJson('/api/reports/analytics');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Report analytics retrieved successfully',
                'data' => [
                    'task_completion_rate' => [
                        'rate' => 50, // 2 completed out of 4 total tasks = 50%
                    ],
                    'projects_completion_rate' => [
                        'rate' => 0, // 0 done out of 1 active
                    ],
                    'summary' => [
                        'total_tasks' => 4,
                        'completed_tasks' => 2,
                        'in_progress_tasks' => 1,
                        'pending_tasks' => 1,
                    ],
                ],
            ]);
    }

    public function test_user_can_get_task_deadline_chart(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->managerToken)
            ->getJson('/api/reports/task-deadline-chart?year='.now()->year);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                ],
            ]);

        $this->assertCount(12, $response->json('data.series.0.data'));
        $this->assertCount(12, $response->json('data.breakdown'));
    }

    public function test_user_can_get_projects_progress_chart(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->managerToken)
            ->getJson('/api/reports/projects-progress-chart?project_id='.$this->project->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                ],
            ]);

        $this->assertCount(12, $response->json('data.series.0.data'));
    }

    public function test_user_can_get_worker_activity_chart_monthly_and_weekly(): void
    {
        // Monthly
        $monthly = $this->withHeader('Authorization', 'Bearer '.$this->managerToken)
            ->getJson('/api/reports/worker-activity-chart?period=monthly');

        $monthly->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'period' => 'monthly',
                ],
            ]);
        $this->assertCount(12, $monthly->json('data.categories'));

        // Weekly
        $weekly = $this->withHeader('Authorization', 'Bearer '.$this->managerToken)
            ->getJson('/api/reports/worker-activity-chart?period=weekly');

        $weekly->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'period' => 'weekly',
                    'categories' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                ],
            ]);
        $this->assertCount(4, $weekly->json('data.series.0.data'));
    }

    public function test_user_can_submit_detailed_report(): void
    {
        $payload = [
            'title' => 'Q3 Sprint Review Report',
            'report_type' => 'ProjectMilestone',
            'notes' => 'Team achieved 50% completion rate for milestone Alpha.',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->toDateString(),
            'project_id' => $this->project->id,
            'team_id' => $this->team->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->managerToken)
            ->postJson('/api/reports', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Report submitted successfully',
                'data' => [
                    'title' => 'Q3 Sprint Review Report',
                    'report_type' => 'ProjectMilestone',
                    'notes' => 'Team achieved 50% completion rate for milestone Alpha.',
                    'project' => [
                        'id' => $this->project->id,
                        'name' => 'E-Commerce Platform',
                    ],
                    'team' => [
                        'id' => $this->team->id,
                        'name' => 'UI/UX Design',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('reports', [
            'title' => 'Q3 Sprint Review Report',
            'report_type' => 'ProjectMilestone',
            'generated_by' => $this->manager->id,
            'project_id' => $this->project->id,
        ]);

        // Verify metrics snapshot was automatically captured
        $reportId = $response->json('data.id');
        $report = Report::find($reportId);
        $this->assertNotNull($report->metrics_data);
        $this->assertEquals(50, $report->metrics_data['task_completion_rate']);
    }

    public function test_user_can_list_and_filter_reports(): void
    {
        Report::create([
            'title' => 'Milestone 1 Report',
            'report_type' => 'Milestone',
            'project_id' => $this->project->id,
            'generated_by' => $this->manager->id,
            'created_at' => now(),
        ]);
        Report::create([
            'title' => 'Security Audit Report',
            'report_type' => 'Audit',
            'project_id' => $this->project->id,
            'generated_by' => $this->manager->id,
            'created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->managerToken)
            ->getJson('/api/reports?report_type=Audit');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('Security Audit Report', $response->json('data.items.0.title'));
    }

    public function test_user_can_view_report_details(): void
    {
        $report = Report::create([
            'title' => 'Design Sprint Overview',
            'report_type' => 'SprintReview',
            'notes' => 'Detailed notes about the design sprint.',
            'project_id' => $this->project->id,
            'team_id' => $this->team->id,
            'generated_by' => $this->manager->id,
            'metrics_data' => ['task_completion_rate' => 50],
            'created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->workerToken)
            ->getJson("/api/reports/{$report->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $report->id,
                    'title' => 'Design Sprint Overview',
                    'report_type' => 'SprintReview',
                    'notes' => 'Detailed notes about the design sprint.',
                ],
            ]);
    }

    public function test_user_can_delete_own_report(): void
    {
        $report = Report::create([
            'title' => 'Temporary Report',
            'report_type' => 'Temp',
            'generated_by' => $this->manager->id,
            'created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->managerToken)
            ->deleteJson("/api/reports/{$report->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Report deleted successfully',
            ]);

        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
    }

    public function test_unauthorized_user_cannot_delete_other_user_report(): void
    {
        $report = Report::create([
            'title' => 'Manager Report',
            'report_type' => 'Executive',
            'generated_by' => $this->manager->id,
            'created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->outsiderToken)
            ->deleteJson("/api/reports/{$report->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('reports', ['id' => $report->id]);
    }

    public function test_user_can_generate_ai_insights_for_report(): void
    {
        $report = Report::create([
            'title' => 'Executive Progress Report',
            'report_type' => 'Executive',
            'notes' => 'Evaluating overall quarter progress and team velocity.',
            'project_id' => $this->project->id,
            'team_id' => $this->team->id,
            'generated_by' => $this->manager->id,
            'metrics_data' => [
                'task_completion_rate' => 75,
                'projects_completion_rate' => 88,
                'teams_activity_rate' => 67,
            ],
            'created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->managerToken)
            ->postJson("/api/reports/{$report->id}/ai-insights");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Report AI insights generated successfully',
            ]);

        $this->assertNotNull($response->json('data.ai_analysis'));
        $this->assertStringContainsString('Task completion', $response->json('data.ai_analysis'));
    }
}
