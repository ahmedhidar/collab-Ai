<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    protected User $organizer;

    protected User $participant;

    protected User $outsider;

    protected Project $project;

    protected Team $team;

    protected string $organizerToken;

    protected string $participantToken;

    protected string $outsiderToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = User::create([
            'full_name' => 'Mohamed Salah',
            'email' => 'salah@example.com',
            'password' => bcrypt('password123'),
            'system_role' => 'TeamMember',
        ]);

        $this->participant = User::create([
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
        $this->team->members()->attach([$this->organizer->id, $this->participant->id]);

        $this->project = Project::create([
            'name' => 'CollabSpace Mobile App',
            'description' => 'Mobile collaboration app',
            'status' => 'Active',
            'priority' => 'High',
            'created_by' => $this->organizer->id,
            'start_date' => now()->toDateString(),
            'deadline' => now()->addMonths(3)->toDateString(),
        ]);
        $this->project->teams()->attach($this->team->id);

        $this->organizerToken = JWTAuth::fromUser($this->organizer);
        $this->participantToken = JWTAuth::fromUser($this->participant);
        $this->outsiderToken = JWTAuth::fromUser($this->outsider);
    }

    public function test_user_can_schedule_meeting_with_participants(): void
    {
        $payload = [
            'title' => 'Sprint Kickoff & Design Review',
            'description' => 'Review initial Figma prototypes with engineering.',
            'meeting_date' => now()->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'meeting_link' => 'https://meet.collabspace.ai/kickoff-room',
            'project_id' => $this->project->id,
            'team_id' => $this->team->id,
            'participant_ids' => [$this->participant->id],
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->organizerToken)
            ->postJson('/api/meetings', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Meeting scheduled successfully',
                'data' => [
                    'title' => 'Sprint Kickoff & Design Review',
                    'meeting_link' => 'https://meet.collabspace.ai/kickoff-room',
                    'status' => 'scheduled',
                    'is_cancelled' => false,
                ],
            ]);

        $this->assertDatabaseHas('meetings', [
            'title' => 'Sprint Kickoff & Design Review',
            'organizer_id' => $this->organizer->id,
            'project_id' => $this->project->id,
            'status' => 'scheduled',
        ]);

        // Verify organizer is automatically accepted and participant is pending
        $meetingId = $response->json('data.id');
        $this->assertDatabaseHas('meeting_participants', [
            'meeting_id' => $meetingId,
            'user_id' => $this->organizer->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('meeting_participants', [
            'meeting_id' => $meetingId,
            'user_id' => $this->participant->id,
            'status' => 'pending',
        ]);
    }

    public function test_user_can_list_meetings_with_calendar_filters(): void
    {
        $meetingToday = Meeting::create([
            'title' => 'Daily Standup',
            'meeting_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'meeting_link' => 'https://meet.collabspace.ai/daily',
            'project_id' => $this->project->id,
            'organizer_id' => $this->organizer->id,
            'status' => 'scheduled',
        ]);
        $meetingToday->participants()->attach([$this->organizer->id, $this->participant->id]);

        $meetingNextWeek = Meeting::create([
            'title' => 'Retrospective',
            'meeting_date' => now()->addDays(10)->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'meeting_link' => 'https://meet.collabspace.ai/retro',
            'project_id' => $this->project->id,
            'organizer_id' => $this->organizer->id,
            'status' => 'scheduled',
        ]);
        $meetingNextWeek->participants()->attach([$this->organizer->id]);

        // Filter by view=today
        $todayResponse = $this->withHeader('Authorization', 'Bearer '.$this->organizerToken)
            ->getJson('/api/meetings?view=today');

        $todayResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertCount(1, $todayResponse->json('data'));
        $this->assertEquals('Daily Standup', $todayResponse->json('data.0.title'));

        // Filter by project_id
        $projectResponse = $this->withHeader('Authorization', 'Bearer '.$this->organizerToken)
            ->getJson('/api/meetings?project_id='.$this->project->id);

        $projectResponse->assertStatus(200);
        $this->assertCount(2, $projectResponse->json('data'));
    }

    public function test_user_can_view_meeting_details_with_relations(): void
    {
        $meeting = Meeting::create([
            'title' => 'Design System Review',
            'description' => 'Evaluating updated button components',
            'meeting_date' => now()->toDateString(),
            'start_time' => '11:00:00',
            'end_time' => '11:45:00',
            'meeting_link' => 'https://meet.collabspace.ai/design',
            'project_id' => $this->project->id,
            'team_id' => $this->team->id,
            'organizer_id' => $this->organizer->id,
            'status' => 'scheduled',
        ]);
        $meeting->participants()->attach([
            $this->organizer->id => ['status' => 'accepted', 'attended' => true],
            $this->participant->id => ['status' => 'accepted', 'attended' => false],
        ]);

        MeetingTranscript::create([
            'meeting_id' => $meeting->id,
            'speaker_id' => $this->organizer->id,
            'speaker_name' => 'Mohamed Salah',
            'time_offset' => '00:05',
            'content' => 'Welcome to the design review.',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->participantToken)
            ->getJson("/api/meetings/{$meeting->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $meeting->id,
                    'title' => 'Design System Review',
                    'project' => [
                        'id' => $this->project->id,
                        'title' => 'CollabSpace Mobile App',
                    ],
                    'team' => [
                        'id' => $this->team->id,
                        'name' => 'UI/UX Design',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data.participants'));
        $this->assertCount(1, $response->json('data.transcripts'));
        $this->assertEquals('00:05', $response->json('data.transcripts.0.time_offset'));
    }

    public function test_user_can_update_meeting_and_reschedule(): void
    {
        $meeting = Meeting::create([
            'title' => 'Old Title',
            'meeting_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'meeting_link' => 'https://meet.collabspace.ai/test',
            'organizer_id' => $this->organizer->id,
            'status' => 'scheduled',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->organizerToken)
            ->putJson("/api/meetings/{$meeting->id}", [
                'title' => 'Updated Sprint Alignment',
                'start_time' => '11:00:00',
                'end_time' => '12:00:00',
                'status' => 'in_progress',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Updated Sprint Alignment',
                    'start_time' => '11:00:00',
                    'status' => 'in_progress',
                ],
            ]);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'title' => 'Updated Sprint Alignment',
            'status' => 'in_progress',
        ]);
    }

    public function test_user_can_cancel_meeting(): void
    {
        $meeting = Meeting::create([
            'title' => 'Sync to Cancel',
            'meeting_date' => now()->addDay()->toDateString(),
            'start_time' => '15:00:00',
            'end_time' => '15:30:00',
            'meeting_link' => 'https://meet.collabspace.ai/cancel',
            'organizer_id' => $this->organizer->id,
            'status' => 'scheduled',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->organizerToken)
            ->patchJson("/api/meetings/{$meeting->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'cancelled',
                    'is_cancelled' => true,
                ],
            ]);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'status' => 'cancelled',
            'is_cancelled' => true,
        ]);
    }

    public function test_user_can_delete_meeting(): void
    {
        $meeting = Meeting::create([
            'title' => 'Meeting To Delete',
            'meeting_date' => now()->addDay()->toDateString(),
            'start_time' => '16:00:00',
            'end_time' => '16:30:00',
            'meeting_link' => 'https://meet.collabspace.ai/delete',
            'organizer_id' => $this->organizer->id,
            'status' => 'scheduled',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->organizerToken)
            ->deleteJson("/api/meetings/{$meeting->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Meeting deleted successfully',
            ]);

        $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
    }

    public function test_participant_can_rsvp_to_meeting(): void
    {
        $meeting = Meeting::create([
            'title' => 'Quarterly Review',
            'meeting_date' => now()->addDays(3)->toDateString(),
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'meeting_link' => 'https://meet.collabspace.ai/quarterly',
            'organizer_id' => $this->organizer->id,
            'status' => 'scheduled',
        ]);
        $meeting->participants()->attach($this->participant->id, ['status' => 'pending']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->participantToken)
            ->postJson("/api/meetings/{$meeting->id}/rsvp", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'RSVP updated successfully',
            ]);

        $this->assertDatabaseHas('meeting_participants', [
            'meeting_id' => $meeting->id,
            'user_id' => $this->participant->id,
            'status' => 'accepted',
        ]);
    }

    public function test_organizer_can_update_participant_attendance(): void
    {
        $meeting = Meeting::create([
            'title' => 'Attendance Checked Meeting',
            'meeting_date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'meeting_link' => 'https://meet.collabspace.ai/attended',
            'organizer_id' => $this->organizer->id,
            'status' => 'completed',
        ]);
        $meeting->participants()->attach([
            $this->organizer->id => ['status' => 'accepted', 'attended' => true],
            $this->participant->id => ['status' => 'accepted', 'attended' => false],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->organizerToken)
            ->postJson("/api/meetings/{$meeting->id}/attendance", [
                'attendances' => [
                    [
                        'user_id' => $this->participant->id,
                        'attended' => true,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Attendance updated successfully',
            ]);

        $this->assertDatabaseHas('meeting_participants', [
            'meeting_id' => $meeting->id,
            'user_id' => $this->participant->id,
            'attended' => true,
        ]);
    }

    public function test_user_can_upload_and_store_transcripts(): void
    {
        $meeting = Meeting::create([
            'title' => 'Architecture Sync',
            'meeting_date' => now()->toDateString(),
            'start_time' => '15:00:00',
            'end_time' => '15:45:00',
            'meeting_link' => 'https://meet.collabspace.ai/arch',
            'organizer_id' => $this->organizer->id,
            'status' => 'completed',
        ]);
        $meeting->participants()->attach([$this->organizer->id, $this->participant->id]);

        // Upload a .txt transcript file
        $txtContent = "[00:01] Mohanad Wahib: Good morning everyone. Thanks for joining.\n[00:40] Ahmed Hassan: Morning. Reviewing new homepage design.\n[01:23] Omar Khaled: I agree the layout is much better.";
        $file = UploadedFile::fake()->createWithContent('meeting_transcript.txt', $txtContent);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->organizerToken)
            ->postJson("/api/meetings/{$meeting->id}/transcripts", [
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Meeting transcripts saved successfully',
            ]);

        $this->assertCount(3, $response->json('data'));
        $this->assertEquals('00:01', $response->json('data.0.time_offset'));
        $this->assertEquals('Mohanad Wahib', $response->json('data.0.speaker_name'));

        $this->assertDatabaseHas('meeting_transcripts', [
            'meeting_id' => $meeting->id,
            'speaker_name' => 'Mohanad Wahib',
            'time_offset' => '00:01',
        ]);
    }

    public function test_unauthorized_user_cannot_update_or_delete_other_user_meeting(): void
    {
        $meeting = Meeting::create([
            'title' => 'Private Organizer Meeting',
            'meeting_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'meeting_link' => 'https://meet.collabspace.ai/private',
            'organizer_id' => $this->organizer->id,
            'status' => 'scheduled',
        ]);

        $updateResponse = $this->withHeader('Authorization', 'Bearer '.$this->outsiderToken)
            ->putJson("/api/meetings/{$meeting->id}", [
                'title' => 'Malicious Hijack Title',
            ]);

        $updateResponse->assertStatus(403);

        $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$this->outsiderToken)
            ->deleteJson("/api/meetings/{$meeting->id}");

        $deleteResponse->assertStatus(403);
    }
}
