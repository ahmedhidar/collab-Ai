<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected Project $project;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::create([
            'full_name' => 'Mohamed Salah',
            'email' => 'salah@collabspace.ai',
            'phone' => '+20 123 456 7890',
            'password' => bcrypt('password123'),
            'job_title' => 'Senior UI/UX Designer',
            'years_of_experience' => 6,
            'bio' => 'Passionate about crafting intuitive design systems.',
            'availability_status' => 'Available',
            'system_role' => 'TeamMember',
        ]);

        $this->team = Team::create(['name' => 'UI/UX Design Team', 'team_type' => 'UI_UX']);
        $this->team->members()->attach($this->user->id);

        $this->project = Project::create([
            'name' => 'CollabSpace - Web Platform',
            'status' => 'Active',
            'priority' => 'High',
            'created_by' => $this->user->id,
            'start_date' => now()->toDateString(),
            'deadline' => now()->addMonths(3)->toDateString(),
        ]);
        $this->project->teams()->attach($this->team->id);

        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_user_can_get_profile_with_stats_and_teams(): void
    {
        // Create 2 completed and 1 pending task
        Task::create([
            'title' => 'Update design system',
            'status' => 'Completed',
            'priority' => 'High',
            'project_id' => $this->project->id,
            'assigned_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'progress' => 100,
        ]);
        Task::create([
            'title' => 'Create information architecture',
            'status' => 'Completed',
            'priority' => 'Medium',
            'project_id' => $this->project->id,
            'assigned_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'progress' => 100,
        ]);
        Task::create([
            'title' => 'Create user interview protocol',
            'status' => 'InProgress',
            'priority' => 'Medium',
            'project_id' => $this->project->id,
            'assigned_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'progress' => 40,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->user->id,
                    'full_name' => 'Mohamed Salah',
                    'email' => 'salah@collabspace.ai',
                    'phone' => '+20 123 456 7890',
                    'job_title' => 'Senior UI/UX Designer',
                    'years_of_experience' => 6,
                    'availability_status' => 'Available',
                    'stats' => [
                        'completed_tasks' => 2,
                        'total_tasks' => 3,
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.teams'));
        $this->assertEquals('UI/UX Design Team', $response->json('data.teams.0.name'));
    }

    public function test_user_can_update_profile_info_and_experience(): void
    {
        $payload = [
            'full_name' => 'Mohamed Salah Updated',
            'job_title' => 'Lead Product Designer',
            'years_of_experience' => 8,
            'bio' => 'Leading product design and user research initiatives.',
            'availability_status' => 'Busy',
            'phone' => '+20 999 888 7777',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson('/api/profile', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User profile updated successfully',
                'data' => [
                    'full_name' => 'Mohamed Salah Updated',
                    'job_title' => 'Lead Product Designer',
                    'years_of_experience' => 8,
                    'availability_status' => 'Busy',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'full_name' => 'Mohamed Salah Updated',
            'job_title' => 'Lead Product Designer',
            'years_of_experience' => 8,
            'availability_status' => 'Busy',
        ]);

        // Verify audit log entry was created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'updated_profile',
            'entity_type' => 'User',
            'entity_id' => $this->user->id,
        ]);
    }

    public function test_user_can_upload_avatar(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 300, 300);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
            ]);

        $this->assertNotNull($response->json('data.avatar'));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'updated_avatar',
        ]);
    }

    public function test_user_can_get_tasks_checklist_segregated_into_todo_and_done(): void
    {
        Task::create([
            'title' => 'Design log in screen',
            'status' => 'Pending',
            'priority' => 'High',
            'project_id' => $this->project->id,
            'assigned_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'progress' => 10,
        ]);
        Task::create([
            'title' => 'Make survey',
            'status' => 'Completed',
            'priority' => 'Low',
            'project_id' => $this->project->id,
            'assigned_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'progress' => 100,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/profile/tasks');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'todo_count' => 1,
                    'done_count' => 1,
                ],
            ]);

        $this->assertEquals('Design log in screen', $response->json('data.todo.0.title'));
        $this->assertEquals('Make survey', $response->json('data.done.0.title'));
        $this->assertTrue($response->json('data.done.0.is_completed'));
    }

    public function test_user_can_toggle_task_completion_status(): void
    {
        $task = Task::create([
            'title' => 'Checklist Item To Toggle',
            'status' => 'Pending',
            'priority' => 'Medium',
            'project_id' => $this->project->id,
            'assigned_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'progress' => 0,
        ]);

        // Toggle to completed
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson("/api/profile/tasks/{$task->id}/toggle");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $task->id,
                    'status' => 'Completed',
                    'progress' => 100,
                ],
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'Completed',
            'progress' => 100,
        ]);

        // Toggle back to in progress
        $reopenResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson("/api/profile/tasks/{$task->id}/toggle");

        $reopenResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'InProgress',
                ],
            ]);
    }

    public function test_user_can_view_activity_timeline(): void
    {
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'created_task',
            'entity_type' => 'Task',
            'entity_id' => 12,
            'details' => ['title' => 'Design System Wireframe'],
            'created_at' => now()->subMinutes(10),
        ]);
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'joined_meeting',
            'entity_type' => 'Meeting',
            'entity_id' => 3,
            'details' => ['title' => 'Sprint Kickoff'],
            'created_at' => now()->subMinutes(5),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/profile/activity');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals('joined_meeting', $response->json('data.0.action'));
    }
}
