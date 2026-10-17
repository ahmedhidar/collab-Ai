<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ChatChannelTest extends TestCase
{
    use RefreshDatabase;

    protected User $member;

    protected User $otherMember;

    protected User $outsider;

    protected Project $project;

    protected Team $team;

    protected string $memberToken;

    protected string $outsiderToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = User::create([
            'full_name' => 'Mohamed Salah',
            'email' => 'salah@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
        ]);

        $this->otherMember = User::create([
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

        $this->team = Team::create(['name' => 'UI/UX Team', 'team_type' => 'UI_UX']);
        $this->team->members()->attach([$this->member->id, $this->otherMember->id]);

        $this->project = Project::create([
            'name' => 'Pet Website Project',
            'status' => 'Active',
            'created_by' => $this->member->id,
        ]);
        $this->project->teams()->attach($this->team->id);

        $this->memberToken = JWTAuth::fromUser($this->member);
        $this->outsiderToken = JWTAuth::fromUser($this->outsider);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson("/api/chats/projects/{$this->project->id}/channels");

        $response->assertStatus(401);
    }

    public function test_member_can_list_project_channels(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->getJson("/api/chats/projects/{$this->project->id}/channels");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Project channels retrieved successfully',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'general')
            ->assertJsonPath('data.0.label', '#Public Channel')
            ->assertJsonPath('data.1.name', 'team_'.$this->team->id)
            ->assertJsonPath('data.1.label', '#UI/UX Team Channel');
    }

    public function test_outsider_cannot_access_project_channels(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->outsiderToken)
            ->getJson("/api/chats/projects/{$this->project->id}/channels");

        $response->assertStatus(403);
    }

    public function test_member_can_send_message_and_broadcasts_event(): void
    {
        Event::fake([MessageSent::class]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->postJson("/api/chats/projects/{$this->project->id}/messages", [
                'channel_name' => 'general',
                'content' => 'Hello team, the new design is ready for review!',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => [
                    'content' => 'Hello team, the new design is ready for review!',
                    'channel_name' => 'general',
                    'sender' => [
                        'id' => $this->member->id,
                        'name' => 'Mohamed Salah',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('project_messages', [
            'project_id' => $this->project->id,
            'channel_name' => 'general',
            'content' => 'Hello team, the new design is ready for review!',
            'sender_id' => $this->member->id,
        ]);

        Event::assertDispatched(MessageSent::class, function ($event) {
            return $event->channelType === 'project' && $event->targetId === $this->project->id;
        });
    }

    public function test_member_can_send_message_with_attachment_and_mentions(): void
    {
        Event::fake([MessageSent::class]);
        Storage::fake('public');

        $fakeFile = UploadedFile::fake()->create('wireframe.png', 500, 'image/png');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->postJson("/api/chats/projects/{$this->project->id}/messages", [
                'channel_name' => 'general',
                'content' => 'Check out the wireframe @Wahib',
                'attachment' => $fakeFile,
                'mention_user_ids' => [$this->otherMember->id],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.attachment.file_name', 'wireframe.png')
            ->assertJsonCount(1, 'data.mentions');

        $this->assertDatabaseHas('files', [
            'file_name' => 'wireframe.png',
            'project_id' => $this->project->id,
        ]);

        $this->assertDatabaseHas('message_mentions', [
            'mentioned_user_id' => $this->otherMember->id,
        ]);
    }

    public function test_user_can_fetch_channel_messages(): void
    {
        ProjectMessage::create([
            'project_id' => $this->project->id,
            'channel_name' => 'general',
            'sender_id' => $this->member->id,
            'content' => 'First test message',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->getJson("/api/chats/projects/{$this->project->id}/messages?channel_name=general");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.content', 'First test message');
    }

    public function test_user_can_mark_messages_as_read(): void
    {
        $message = ProjectMessage::create([
            'project_id' => $this->project->id,
            'channel_name' => 'general',
            'sender_id' => $this->otherMember->id,
            'content' => 'Please review this update',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->postJson("/api/chats/projects/{$this->project->id}/read", [
                'message_ids' => [$message->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Messages marked as read successfully',
            ]);

        $this->assertDatabaseHas('message_read_receipts', [
            'message_id' => $message->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_user_can_view_chat_sidebar(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->memberToken)
            ->getJson('/api/chats/sidebar');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.project_name', 'Pet Website Project');
    }
}
