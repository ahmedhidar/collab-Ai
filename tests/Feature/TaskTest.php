<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $reviewer;

    protected Project $project;

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
        ]);

        $this->reviewer = User::create([
            'full_name' => 'Ali Hassan',
            'email' => 'ali@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
        ]);

        $this->team = Team::create(['name' => 'UI/UX Team', 'team_type' => 'UI_UX']);

        $this->project = Project::create([
            'name' => 'CollabSpace Redesign',
            'created_by' => $this->user->id,
        ]);

        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/tasks');

        $response->assertStatus(401);
    }

    public function test_user_can_list_tasks_with_filters(): void
    {
        Task::create([
            'title' => 'Create Wireframes',
            'status' => 'Pending',
            'priority' => 'High',
            'project_id' => $this->project->id,
            'assigned_user_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        Task::create([
            'title' => 'Backend Database Setup',
            'status' => 'Completed',
            'priority' => 'Medium',
            'project_id' => $this->project->id,
            'assigned_user_id' => $this->reviewer->id,
            'created_by' => $this->user->id,
        ]);

        // Filter by status Pending
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/tasks?status=Pending');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.title', 'Create Wireframes');

        // Filter by my_tasks
        $myTasksResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/tasks?my_tasks=1');

        $myTasksResponse->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.title', 'Create Wireframes');
    }

    public function test_user_can_retrieve_kanban_columns(): void
    {
        Task::create([
            'title' => 'Wireframe Exploration',
            'status' => 'Pending',
            'priority' => 'High',
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        Task::create([
            'title' => 'Interactive Prototype',
            'status' => 'InProgress',
            'priority' => 'Medium',
            'progress' => 50,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/tasks?kanban=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'columns' => [
                        'Pending',
                        'InProgress',
                        'InReview',
                        'Completed',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data.columns.Pending')
            ->assertJsonCount(1, 'data.columns.InProgress')
            ->assertJsonCount(0, 'data.columns.Completed');
    }

    public function test_user_can_create_task_with_reviewer(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/tasks', [
                'title' => 'Mobile Responsiveness',
                'description' => 'Ensure full responsive layout across devices',
                'status' => 'InReview',
                'priority' => 'High',
                'progress' => 80,
                'project_id' => $this->project->id,
                'assigned_team_id' => $this->team->id,
                'assigned_user_id' => $this->user->id,
                'reviewer_id' => $this->reviewer->id,
                'due_date' => '2026-12-15 18:00:00',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Task created successfully',
                'data' => [
                    'title' => 'Mobile Responsiveness',
                    'status' => 'InReview',
                    'priority' => 'High',
                    'progress' => 80,
                    'reviewer' => [
                        'id' => $this->reviewer->id,
                        'name' => 'Ali Hassan',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Mobile Responsiveness',
            'reviewer_id' => $this->reviewer->id,
            'progress' => 80,
        ]);
    }

    public function test_user_can_view_single_task_detail(): void
    {
        $task = Task::create([
            'title' => 'Design System Updates',
            'status' => 'Pending',
            'priority' => 'Low',
            'project_id' => $this->project->id,
            'reviewer_id' => $this->reviewer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/tasks/'.$task->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $task->id,
                    'title' => 'Design System Updates',
                    'reviewer' => [
                        'id' => $this->reviewer->id,
                        'name' => 'Ali Hassan',
                    ],
                ],
            ]);
    }

    public function test_user_can_update_task(): void
    {
        $task = Task::create([
            'title' => 'Initial Task Title',
            'status' => 'Pending',
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson('/api/tasks/'.$task->id, [
                'title' => 'Updated Task Title',
                'priority' => 'Critical',
                'progress' => 45,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Updated Task Title',
                    'priority' => 'Critical',
                    'progress' => 45,
                ],
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated Task Title',
            'priority' => 'Critical',
        ]);
    }

    public function test_user_can_transition_task_status(): void
    {
        $task = Task::create([
            'title' => 'Task to Complete',
            'status' => 'InProgress',
            'progress' => 50,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/tasks/'.$task->id.'/status', [
                'status' => 'Completed',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'Completed',
                    'progress' => 100,
                ],
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'Completed',
            'progress' => 100,
        ]);
    }

    public function test_user_can_delete_task(): void
    {
        $task = Task::create([
            'title' => 'Task to be deleted',
            'status' => 'Pending',
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson('/api/tasks/'.$task->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Task deleted successfully',
            ]);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
