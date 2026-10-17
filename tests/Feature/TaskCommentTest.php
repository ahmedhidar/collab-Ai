<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class TaskCommentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected User $admin;

    protected Project $project;

    protected Task $task;

    protected string $token;

    protected string $otherToken;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'full_name' => 'Mohamed Salah',
            'email' => 'salah@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
        ]);

        $this->otherUser = User::create([
            'full_name' => 'Sara Hassan',
            'email' => 'sara@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
        ]);

        $this->admin = User::create([
            'full_name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'Admin',
        ]);

        $this->project = Project::create([
            'name' => 'CollabSpace Mobile',
            'created_by' => $this->admin->id,
        ]);

        $this->task = Task::create([
            'title' => 'Chat Screen Prototyping',
            'status' => 'InProgress',
            'project_id' => $this->project->id,
            'created_by' => $this->admin->id,
        ]);

        $this->token = JWTAuth::fromUser($this->user);
        $this->otherToken = JWTAuth::fromUser($this->otherUser);
        $this->adminToken = JWTAuth::fromUser($this->admin);
    }

    public function test_unauthenticated_user_cannot_access_comments(): void
    {
        $response = $this->getJson("/api/tasks/{$this->task->id}/comments");

        $response->assertStatus(401);
    }

    public function test_user_can_list_comments_for_a_task(): void
    {
        TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
            'comment' => 'Great progress on the initial prototype!',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/tasks/{$this->task->id}/comments");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Task comments retrieved successfully',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comment', 'Great progress on the initial prototype!')
            ->assertJsonPath('data.0.user.name', 'Mohamed Salah');
    }

    public function test_user_can_post_a_root_comment(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/tasks/{$this->task->id}/comments", [
                'comment' => 'Please review the typography scale on this component.',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Comment posted successfully',
                'data' => [
                    'task_id' => $this->task->id,
                    'parent_id' => null,
                    'comment' => 'Please review the typography scale on this component.',
                    'user' => [
                        'id' => $this->user->id,
                        'name' => 'Mohamed Salah',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
            'comment' => 'Please review the typography scale on this component.',
            'parent_id' => null,
        ]);
    }

    public function test_user_can_reply_to_an_existing_comment(): void
    {
        $parentComment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
            'comment' => 'Should we use Tailwind or Vanilla CSS here?',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->otherToken)
            ->postJson("/api/tasks/{$this->task->id}/comments", [
                'comment' => 'Vanilla CSS as agreed in the tech stack guidelines.',
                'parent_id' => $parentComment->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'parent_id' => $parentComment->id,
                    'comment' => 'Vanilla CSS as agreed in the tech stack guidelines.',
                    'user' => [
                        'id' => $this->otherUser->id,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $this->task->id,
            'user_id' => $this->otherUser->id,
            'parent_id' => $parentComment->id,
        ]);

        // Verify nested structure when fetching comments
        $listResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson("/api/tasks/{$this->task->id}/comments");

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data') // only 1 root comment
            ->assertJsonCount(1, 'data.0.replies') // 1 reply nested under it
            ->assertJsonPath('data.0.replies.0.comment', 'Vanilla CSS as agreed in the tech stack guidelines.');
    }

    public function test_reply_rejects_parent_comment_from_different_task(): void
    {
        $otherTask = Task::create([
            'title' => 'Different Task',
            'project_id' => $this->project->id,
            'created_by' => $this->admin->id,
        ]);

        $otherComment = TaskComment::create([
            'task_id' => $otherTask->id,
            'user_id' => $this->user->id,
            'comment' => 'Unrelated comment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson("/api/tasks/{$this->task->id}/comments", [
                'comment' => 'Trying to link to wrong task comment',
                'parent_id' => $otherComment->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_comment_author_can_delete_their_comment(): void
    {
        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
            'comment' => 'Accidental comment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson("/api/tasks/{$this->task->id}/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Comment deleted successfully',
            ]);

        $this->assertDatabaseMissing('task_comments', ['id' => $comment->id]);
    }

    public function test_user_cannot_delete_another_users_comment(): void
    {
        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
            'comment' => 'Protected comment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->otherToken)
            ->deleteJson("/api/tasks/{$this->task->id}/comments/{$comment->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_any_comment(): void
    {
        $comment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
            'comment' => 'Comment to be moderated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/tasks/{$this->task->id}/comments/{$comment->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('task_comments', ['id' => $comment->id]);
    }
}
