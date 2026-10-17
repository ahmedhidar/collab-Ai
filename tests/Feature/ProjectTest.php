<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $pm;

    protected User $member;

    protected string $adminToken;

    protected string $pmToken;

    protected string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'full_name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'Admin',
        ]);

        $this->pm = User::create([
            'full_name' => 'Project Manager',
            'email' => 'pm@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'ProjectManager',
        ]);

        $this->member = User::create([
            'full_name' => 'Team Member',
            'email' => 'member@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
        ]);

        $this->adminToken = JWTAuth::fromUser($this->admin);
        $this->pmToken = JWTAuth::fromUser($this->pm);
        $this->memberToken = JWTAuth::fromUser($this->member);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/projects');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_list_projects_with_pagination(): void
    {
        Project::create([
            'name' => 'Alpha Platform',
            'priority' => 'High',
            'status' => 'Active',
            'created_by' => $this->pm->id,
        ]);

        Project::create([
            'name' => 'SepetGo App',
            'priority' => 'Medium',
            'status' => 'Done',
            'created_by' => $this->pm->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->getJson('/api/projects?status=Active');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Projects retrieved successfully',
            ])
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.name', 'Alpha Platform');
    }

    public function test_project_manager_can_create_project(): void
    {
        $team = Team::create(['name' => 'Core Team']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->pmToken)
            ->postJson('/api/projects', [
                'name' => 'CollabSpace Round 13',
                'description' => 'Collaboration and AI suite',
                'start_date' => '2026-10-18',
                'deadline' => '2026-12-31',
                'priority' => 'High',
                'status' => 'Active',
                'team_ids' => [$team->id],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Project created successfully',
                'data' => [
                    'name' => 'CollabSpace Round 13',
                    'priority' => 'High',
                ],
            ]);

        $this->assertDatabaseHas('projects', ['name' => 'CollabSpace Round 13']);
        $this->assertDatabaseHas('project_teams', ['team_id' => $team->id]);
    }

    public function test_regular_team_member_cannot_create_project(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->postJson('/api/projects', [
                'name' => 'Unauthorized Project',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_can_view_single_project_detail(): void
    {
        $team = Team::create(['name' => 'Design Team']);
        $project = Project::create([
            'name' => 'Mobile App Redesign',
            'description' => 'Complete UI overhaul',
            'created_by' => $this->pm->id,
        ]);
        $project->teams()->attach($team->id);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->getJson('/api/projects/'.$project->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $project->id,
                    'name' => 'Mobile App Redesign',
                    'stats' => [
                        'total_tasks' => 0,
                    ],
                ],
            ]);
    }

    public function test_pm_can_update_project(): void
    {
        $project = Project::create([
            'name' => 'Initial Project Name',
            'priority' => 'Low',
            'created_by' => $this->pm->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->pmToken)
            ->putJson('/api/projects/'.$project->id, [
                'name' => 'Updated Project Name',
                'priority' => 'Critical',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Project Name',
                    'priority' => 'Critical',
                ],
            ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'priority' => 'Critical',
        ]);
    }

    public function test_pm_can_assign_and_remove_teams_from_project(): void
    {
        $project = Project::create([
            'name' => 'SaaS Platform',
            'created_by' => $this->pm->id,
        ]);

        $team = Team::create(['name' => 'Backend Engineers']);

        // Assign team
        $assignResponse = $this->withHeader('Authorization', 'Bearer '.$this->pmToken)
            ->postJson('/api/projects/'.$project->id.'/teams', [
                'team_id' => $team->id,
            ]);

        $assignResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Team assigned to project successfully',
            ]);

        $this->assertDatabaseHas('project_teams', [
            'project_id' => $project->id,
            'team_id' => $team->id,
        ]);

        // Remove team
        $removeResponse = $this->withHeader('Authorization', 'Bearer '.$this->pmToken)
            ->deleteJson('/api/projects/'.$project->id.'/teams/'.$team->id);

        $removeResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Team removed from project successfully',
            ]);

        $this->assertDatabaseMissing('project_teams', [
            'project_id' => $project->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_admin_can_delete_project(): void
    {
        $project = Project::create([
            'name' => 'Obsolete Project',
            'created_by' => $this->pm->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/projects/'.$project->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Project deleted successfully',
            ]);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}
