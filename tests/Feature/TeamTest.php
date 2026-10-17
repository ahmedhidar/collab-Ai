<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $member;

    protected string $adminToken;

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

        $this->member = User::create([
            'full_name' => 'Team Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
        ]);

        $this->adminToken = JWTAuth::fromUser($this->admin);
        $this->memberToken = JWTAuth::fromUser($this->member);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/teams');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_list_teams(): void
    {
        Team::create([
            'name' => 'UI/UX Team',
            'team_type' => 'UI_UX',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->getJson('/api/teams');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Teams retrieved successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'team_type',
                        'members_count',
                        'active_projects_count',
                        'total_tasks_count',
                        'completed_tasks_count',
                        'completion_rate',
                    ],
                ],
            ]);
    }

    public function test_admin_can_create_a_team(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/teams', [
                'name' => 'Backend Developers',
                'description' => 'Core API services and infrastructure',
                'team_type' => 'Backend',
                'member_ids' => [$this->member->id],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Team created successfully',
                'data' => [
                    'name' => 'Backend Developers',
                    'team_type' => 'Backend',
                ],
            ]);

        $this->assertDatabaseHas('teams', ['name' => 'Backend Developers']);
        $this->assertDatabaseHas('team_members', [
            'user_id' => $this->member->id,
        ]);
    }

    public function test_team_member_cannot_create_a_team(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->postJson('/api/teams', [
                'name' => 'QA Team',
                'team_type' => 'QA',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_can_view_single_team(): void
    {
        $team = Team::create([
            'name' => 'Frontend Team',
            'team_type' => 'Frontend',
        ]);

        $team->members()->attach($this->member->id);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->getJson('/api/teams/'.$team->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $team->id,
                    'name' => 'Frontend Team',
                ],
            ]);
    }

    public function test_admin_can_update_team(): void
    {
        $team = Team::create([
            'name' => 'DevOps Team',
            'team_type' => 'DevOps',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson('/api/teams/'.$team->id, [
                'name' => 'Cloud & Infrastructure',
                'description' => 'AWS & Docker specialists',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Cloud & Infrastructure',
                    'description' => 'AWS & Docker specialists',
                ],
            ]);

        $this->assertDatabaseHas('teams', ['name' => 'Cloud & Infrastructure']);
    }

    public function test_admin_can_add_and_remove_member(): void
    {
        $team = Team::create([
            'name' => 'Marketing Team',
            'team_type' => 'Marketing',
        ]);

        // Add member
        $addResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/teams/'.$team->id.'/members', [
                'user_id' => $this->member->id,
            ]);

        $addResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Member added to team successfully',
            ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $this->member->id,
        ]);

        // Remove member
        $removeResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/teams/'.$team->id.'/members/'.$this->member->id);

        $removeResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Member removed from team successfully',
            ]);

        $this->assertDatabaseMissing('team_members', [
            'team_id' => $team->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_admin_can_delete_team(): void
    {
        $team = Team::create([
            'name' => 'Temporary Team',
            'team_type' => 'Other',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/teams/'.$team->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Team deleted successfully',
            ]);

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }
}
