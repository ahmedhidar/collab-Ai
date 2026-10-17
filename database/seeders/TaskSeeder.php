<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project1 = Project::first();
        $backendTeam = Team::where('team_type', 'Backend')->first();
        $frontendTeam = Team::where('team_type', 'Frontend')->first();
        $backendUser = User::where('email', 'backend@collabspace.ai')->first();
        $frontendUser = User::where('email', 'frontend@collabspace.ai')->first();
        $pmUser = User::where('system_role', 'ProjectManager')->first();

        if (! $project1 || ! $backendUser || ! $pmUser) {
            return;
        }

        // Task 1
        $task1 = Task::create([
            'title' => 'Design & Implement Authentication via OAuth2 & JWT',
            'description' => 'Implement secure user authentication including Google SSO, Sanctum / Passport tokens, and role-based permissions.',
            'status' => 'Completed',
            'priority' => 'High',
            'project_id' => $project1->id,
            'assigned_team_id' => $backendTeam?->id,
            'assigned_user_id' => $backendUser->id,
            'created_by' => $pmUser->id,
            'due_date' => now()->addDays(5),
        ]);

        TaskComment::create([
            'task_id' => $task1->id,
            'user_id' => $backendUser->id,
            'comment' => 'Completed token handling, refreshing logic, and rate limiting middlewares.',
            'created_at' => now()->subDays(2),
        ]);

        TaskComment::create([
            'task_id' => $task1->id,
            'user_id' => $pmUser->id,
            'comment' => 'Great work! Code review passed, PR merged into staging.',
            'created_at' => now()->subDay(),
        ]);

        // Task 2
        $task2 = Task::create([
            'title' => 'Real-Time Project Messaging Interface',
            'description' => 'Build reactive chat UI with WebSocket integration for team channels, message reactions, and threaded discussions.',
            'status' => 'InProgress',
            'priority' => 'Critical',
            'project_id' => $project1->id,
            'assigned_team_id' => $frontendTeam?->id,
            'assigned_user_id' => $frontendUser?->id,
            'created_by' => $pmUser->id,
            'due_date' => now()->addDays(10),
        ]);

        if ($frontendUser) {
            TaskComment::create([
                'task_id' => $task2->id,
                'user_id' => $frontendUser->id,
                'comment' => 'Socket connection established; currently working on UI optimistic updates.',
                'created_at' => now()->subHours(6),
            ]);
        }

        // Task 3
        Task::create([
            'title' => 'AI Automated Meeting Summarization Pipeline',
            'description' => 'Hook WebRTC audio stream into Whisper STT and Claude / Gemini prompt engine to extract key decisions.',
            'status' => 'Pending',
            'priority' => 'High',
            'project_id' => $project1->id,
            'assigned_team_id' => $backendTeam?->id,
            'assigned_user_id' => $backendUser->id,
            'created_by' => $pmUser->id,
            'due_date' => now()->addDays(14),
        ]);
    }
}
