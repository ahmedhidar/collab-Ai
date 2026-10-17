<?php

namespace Database\Seeders;

use App\Models\AiMeetingSummary;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class MeetingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::first();
        $team = Team::first();
        $pm = User::where('system_role', 'ProjectManager')->first() ?? User::first();
        $backend = User::where('email', 'backend@collabspace.ai')->first();
        $frontend = User::where('email', 'frontend@collabspace.ai')->first();
        $qa = User::where('email', 'qa@collabspace.ai')->first();

        if (! $project || ! $pm) {
            return;
        }

        // 1. Completed Sprint Alignment Meeting
        $meeting1 = Meeting::create([
            'title' => 'Sprint 1 Architecture & AI Pipeline Kickoff',
            'description' => 'Discussing data pipelines, LLM prompts orchestration, and database relationships.',
            'meeting_date' => now()->subDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'meeting_link' => 'https://meet.collabspace.ai/room-sprint1-arch',
            'project_id' => $project->id,
            'team_id' => $team?->id,
            'organizer_id' => $pm->id,
            'is_cancelled' => false,
            'created_at' => now()->subDays(5),
        ]);

        if ($backend) {
            MeetingParticipant::create(['meeting_id' => $meeting1->id, 'user_id' => $backend->id, 'attended' => true]);
        }
        if ($frontend) {
            MeetingParticipant::create(['meeting_id' => $meeting1->id, 'user_id' => $frontend->id, 'attended' => true]);
        }
        if ($qa) {
            MeetingParticipant::create(['meeting_id' => $meeting1->id, 'user_id' => $qa->id, 'attended' => false]);
        }

        // AI Summary for Meeting 1
        AiMeetingSummary::create([
            'meeting_id' => $meeting1->id,
            'summary_text' => 'The team aligned on using Laravel 11/12 for backend microservices and implementing an asynchronous event-driven architecture for AI tasks.',
            'decisions_made' => [
                'Adopt SQLite for testing and MySQL / PostgreSQL for production clusters',
                'Integrate Redis queues for background AI tasks to avoid HTTP timeouts',
                'Use UUIDs / Foreign Keys strictly constrained with cascade delete safeguards',
            ],
            'action_items' => [
                ['task' => 'Implement complete database migrations and seeders', 'assignee' => 'Backend Team', 'status' => 'Done'],
                ['task' => 'Create mockup for Chat and Meeting Summarizer dashboard', 'assignee' => 'Frontend & UI/UX', 'status' => 'InProgress'],
            ],
            'risks_identified' => [
                'Rate limits on LLM API endpoints during high peak usage',
                'Large file attachment storage cost over S3',
            ],
            'next_steps' => 'Backend will deploy staging build; frontend will connect WebSocket endpoints next Monday.',
            'generated_at' => now()->subDays(2)->addHours(1),
        ]);

        // 2. Upcoming meeting
        Meeting::create([
            'title' => 'Weekly Cross-Functional Sync & Demo',
            'description' => 'Live demo of sprint deliverables and sprint retrospective.',
            'meeting_date' => now()->addDays(3)->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'meeting_link' => 'https://meet.collabspace.ai/room-weekly-demo',
            'project_id' => $project->id,
            'team_id' => $team?->id,
            'organizer_id' => $pm->id,
            'is_cancelled' => false,
            'created_at' => now()->subDay(),
        ]);
    }
}
