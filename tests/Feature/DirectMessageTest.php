<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\InboxMessage;
use App\Models\InboxThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class DirectMessageTest extends TestCase
{
    use RefreshDatabase;

    protected User $userOne;

    protected User $userTwo;

    protected User $outsider;

    protected string $tokenOne;

    protected string $tokenTwo;

    protected string $outsiderToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userOne = User::create([
            'full_name' => 'Mohamed Salah',
            'email' => 'salah@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
            'job_title' => 'Senior UI/UX',
        ]);

        $this->userTwo = User::create([
            'full_name' => 'Mohamed Ahmed',
            'email' => 'ahmed@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
            'job_title' => 'UI/UX Designer',
        ]);

        $this->outsider = User::create([
            'full_name' => 'Ramzy Khaled',
            'email' => 'ramzy@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
        ]);

        $this->tokenOne = JWTAuth::fromUser($this->userOne);
        $this->tokenTwo = JWTAuth::fromUser($this->userTwo);
        $this->outsiderToken = JWTAuth::fromUser($this->outsider);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/inbox/threads');

        $response->assertStatus(401);
    }

    public function test_user_can_initialize_inbox_thread_with_another_user(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenOne)
            ->postJson('/api/inbox/threads', [
                'recipient_id' => $this->userTwo->id,
                'content' => 'Yes, we can do this and edit screen',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Direct message conversation initialized successfully',
                'data' => [
                    'other_user' => [
                        'id' => $this->userTwo->id,
                        'name' => 'Mohamed Ahmed',
                    ],
                    'latest_message' => [
                        'content' => 'Yes, we can do this and edit screen',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('inbox_threads', [
            'user_one_id' => $this->userOne->id,
            'user_two_id' => $this->userTwo->id,
        ]);

        $this->assertDatabaseHas('inbox_messages', [
            'content' => 'Yes, we can do this and edit screen',
            'sender_id' => $this->userOne->id,
        ]);
    }

    public function test_user_cannot_start_thread_with_self(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenOne)
            ->postJson('/api/inbox/threads', [
                'recipient_id' => $this->userOne->id,
                'content' => 'Talking to myself',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_id']);
    }

    public function test_user_can_list_inbox_threads_with_unread_counts(): void
    {
        $thread = InboxThread::create([
            'user_one_id' => $this->userOne->id,
            'user_two_id' => $this->userTwo->id,
            'last_message_at' => now(),
            'created_at' => now(),
        ]);

        InboxMessage::create([
            'thread_id' => $thread->id,
            'sender_id' => $this->userTwo->id,
            'content' => 'Ok, i wait you',
            'is_read' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenOne)
            ->getJson('/api/inbox/threads');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Inbox conversations retrieved successfully',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.other_user.name', 'Mohamed Ahmed')
            ->assertJsonPath('data.0.latest_message.content', 'Ok, i wait you');
    }

    public function test_user_can_send_direct_message_and_broadcasts(): void
    {
        Event::fake([MessageSent::class]);

        $thread = InboxThread::create([
            'user_one_id' => $this->userOne->id,
            'user_two_id' => $this->userTwo->id,
            'last_message_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenOne)
            ->postJson("/api/inbox/threads/{$thread->id}/messages", [
                'content' => 'Review the layout in Figma',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'content' => 'Review the layout in Figma',
                    'sender' => [
                        'id' => $this->userOne->id,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('inbox_messages', [
            'thread_id' => $thread->id,
            'sender_id' => $this->userOne->id,
            'content' => 'Review the layout in Figma',
        ]);

        Event::assertDispatched(MessageSent::class, function ($event) use ($thread) {
            return $event->channelType === 'inbox' && $event->targetId === $thread->id;
        });
    }

    public function test_user_can_send_direct_message_with_attachment(): void
    {
        Event::fake([MessageSent::class]);
        Storage::fake('public');

        $fakeFile = UploadedFile::fake()->create('contract.pdf', 300, 'application/pdf');

        $thread = InboxThread::create([
            'user_one_id' => $this->userOne->id,
            'user_two_id' => $this->userTwo->id,
            'last_message_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenOne)
            ->postJson("/api/inbox/threads/{$thread->id}/messages", [
                'content' => 'Here is the contract document',
                'attachment' => $fakeFile,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.attachment.file_name', 'contract.pdf');

        $this->assertDatabaseHas('files', [
            'file_name' => 'contract.pdf',
        ]);
    }

    public function test_viewing_thread_marks_incoming_messages_as_read(): void
    {
        $thread = InboxThread::create([
            'user_one_id' => $this->userOne->id,
            'user_two_id' => $this->userTwo->id,
            'last_message_at' => now(),
            'created_at' => now(),
        ]);

        $message = InboxMessage::create([
            'thread_id' => $thread->id,
            'sender_id' => $this->userTwo->id,
            'content' => 'Unread message before open',
            'is_read' => false,
        ]);

        $this->assertEquals(0, $message->fresh()->is_read);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenOne)
            ->getJson("/api/inbox/threads/{$thread->id}/messages");

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1);

        $this->assertEquals(1, $message->fresh()->is_read);
    }

    public function test_outsider_cannot_read_or_post_in_private_thread(): void
    {
        $thread = InboxThread::create([
            'user_one_id' => $this->userOne->id,
            'user_two_id' => $this->userTwo->id,
            'last_message_at' => now(),
            'created_at' => now(),
        ]);

        // Attempt to read
        $readResponse = $this->withHeader('Authorization', 'Bearer '.$this->outsiderToken)
            ->getJson("/api/inbox/threads/{$thread->id}/messages");

        $readResponse->assertStatus(403);

        // Attempt to send
        $sendResponse = $this->withHeader('Authorization', 'Bearer '.$this->outsiderToken)
            ->postJson("/api/inbox/threads/{$thread->id}/messages", [
                'content' => 'Intruder message',
            ]);

        $sendResponse->assertStatus(403);
    }
}
