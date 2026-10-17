<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherMember;

    protected Project $activeProject;

    protected Project $doneProject;

    protected Team $team;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'full_name' => 'Mohamed Salah',
            'email' => 'salah@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'ProjectManager',
            'job_title' => 'UI/UX Lead',
        ]);

        $this->otherMember = User::create([
            'full_name' => 'Ali Mohamed',
            'email' => 'ali@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
            'job_title' => 'Frontend Engineer',
        ]);

        $this->team = Team::create([
            'name' => 'UI/UX Team',
            'team_type' => 'UI_UX',
        ]);

        $this->team->members()->attach([$this->user->id, $this->otherMember->id]);

        $this->activeProject = Project::create([
            'name' => 'CollabSpace AI Web',
            'description' => 'Collaboration suite',
            'status' => 'Active',
            'priority' => 'High',
            'start_date' => '2026-01-01',
            'deadline' => '2026-12-31',
            'created_by' => $this->user->id,
        ]);
        $this->activeProject->teams()->attach($this->team->id);

        $this->doneProject = Project::create([
            'name' => 'Legacy System Migration',
            'status' => 'Done',
            'priority' => 'Low',
            'created_by' => $this->user->id,
        ]);

        // Tasks setup
        Task::create([
            'title' => 'Design Wireframes',
            'status' => 'Pending',
            'priority' => 'High',
            'project_id' => $this->activeProject->id,
            'assigned_team_id' => $this->team->id,
            'assigned_user_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        Task::create([
            'title' => 'Build UI Prototype',
            'status' => 'InProgress',
            'priority' => 'Medium',
            'progress' => 50,
            'project_id' => $this->activeProject->id,
            'assigned_team_id' => $this->team->id,
            'assigned_user_id' => $this->otherMember->id,
            'created_by' => $this->user->id,
        ]);

        Task::create([
            'title' => 'Setup Architecture',
            'status' => 'Completed',
            'priority' => 'Critical',
            'progress' => 100,
            'project_id' => $this->activeProject->id,
            'assigned_team_id' => $this->team->id,
            'assigned_user_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(401);
    }

    public function test_user_can_retrieve_full_unified_dashboard_payload(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'kpis' => [
                        'pending_tasks',
                        'in_progress_tasks',
                        'completed_tasks',
                        'completion_rate',
                        'summary',
                    ],
                    'project_overview' => [
                        'year',
                        'labels',
                        'datasets',
                    ],
                    'active_projects',
                    'team_progress',
                    'recent_files',
                    'upcoming_meetings',
                ],
            ]);
    }

    public function test_kpi_stats_calculation_accuracy(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pending_tasks' => [
                        'count' => 1,
                        'total_tasks' => 3,
                    ],
                    'in_progress_tasks' => [
                        'count' => 1,
                        'total_tasks' => 3,
                    ],
                    'completed_tasks' => [
                        'count' => 1,
                        'total_tasks' => 3,
                    ],
                    'completion_rate' => [
                        'percentage' => 33, // 1/3 is 33%
                    ],
                    'summary' => [
                        'total_projects' => 2,
                        'active_projects' => 1,
                    ],
                ],
            ]);
    }

    public function test_kpi_stats_scoped_to_specific_project(): void
    {
        $otherProject = Project::create([
            'name' => 'Other Project',
            'status' => 'Active',
            'created_by' => $this->user->id,
        ]);

        Task::create([
            'title' => 'Other Task 1',
            'status' => 'Completed',
            'project_id' => $otherProject->id,
            'created_by' => $this->user->id,
        ]);

        Task::create([
            'title' => 'Other Task 2',
            'status' => 'Completed',
            'project_id' => $otherProject->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/dashboard/stats?project_id='.$otherProject->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'completed_tasks' => [
                        'count' => 2,
                        'total_tasks' => 2,
                    ],
                    'completion_rate' => [
                        'percentage' => 100,
                    ],
                ],
            ]);
    }

    public function test_project_overview_chart_structure(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/dashboard/chart?year=2026');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'year' => 2026,
                    'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                ],
            ])
            ->assertJsonCount(2, 'data.datasets');
    }

    public function test_active_projects_widget(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/dashboard/active-projects');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'CollabSpace AI Web')
            ->assertJsonPath('data.0.progress_percentage', 33)
            ->assertJsonPath('data.0.task_progress', '1 / 3');
    }

    public function test_team_progress_widget(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/dashboard/team-progress');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'UI/UX Team')
            ->assertJsonPath('data.0.members_count', 2)
            ->assertJsonPath('data.0.completion_rate', 33);
    }

    public function test_recent_files_widget(): void
    {
        File::create([
            'file_name' => 'UX_Research_Summary.pdf',
            'file_path' => 'uploads/UX_Research_Summary.pdf',
            'file_size_bytes' => 1048576,
            'mime_type' => 'application/pdf',
            'project_id' => $this->activeProject->id,
            'uploaded_by' => $this->user->id,
            'uploaded_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/dashboard/recent-files');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.file_name', 'UX_Research_Summary.pdf')
            ->assertJsonPath('data.0.uploaded_by', 'Mohamed Salah');
    }

    public function test_upcoming_meetings_widget(): void
    {
        $meeting = Meeting::create([
            'title' => 'Design Review Session',
            'meeting_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'meeting_link' => 'https://meet.google.com/xyz',
            'project_id' => $this->activeProject->id,
            'team_id' => $this->team->id,
            'organizer_id' => $this->user->id,
            'is_cancelled' => false,
        ]);
        $meeting->participants()->attach([$this->user->id, $this->otherMember->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/dashboard/upcoming-meetings');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Design Review Session')
            ->assertJsonPath('data.0.time_range', '14:00 - 15:00');
    }
}
