<?php

namespace Database\Seeders;

use App\Models\AiActivityLog;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class LogAndReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('system_role', 'Admin')->first() ?? User::first();
        $pm = User::where('system_role', 'ProjectManager')->first() ?? User::first();
        $backend = User::where('email', 'backend@collabspace.ai')->first();
        $project = Project::first();
        $team = Team::first();

        // 1. Audit Logs
        AuditLog::create([
            'user_id' => $admin?->id,
            'action' => 'PROJECT_CREATED',
            'entity_type' => 'Project',
            'entity_id' => $project?->id,
            'details' => ['name' => $project?->name, 'priority' => $project?->priority],
            'ip_address' => '192.168.1.10',
            'created_at' => now()->subDays(15),
        ]);

        AuditLog::create([
            'user_id' => $pm?->id,
            'action' => 'TASK_STATUS_UPDATED',
            'entity_type' => 'Task',
            'entity_id' => 1,
            'details' => ['old_status' => 'InProgress', 'new_status' => 'Completed'],
            'ip_address' => '192.168.1.15',
            'created_at' => now()->subDays(2),
        ]);

        // 2. AI Activity Logs
        AiActivityLog::create([
            'user_id' => $pm?->id,
            'prompt_query' => 'Generate an executive summary and action items for the Sprint 1 Architecture kickoff meeting.',
            'ai_response' => 'Summary generated successfully with 3 strategic decisions, 2 action items, and 2 critical risks.',
            'tokens_used' => 1450,
            'execution_time_ms' => 1240,
            'created_at' => now()->subDays(2),
        ]);

        AiActivityLog::create([
            'user_id' => $backend?->id,
            'prompt_query' => 'Suggest optimal PostgreSQL indexing strategy for team_messages and inbox_messages high throughput.',
            'ai_response' => 'Recommended composite index on (thread_id, created_at DESC) and partial index on (is_read) where is_read = false.',
            'tokens_used' => 820,
            'execution_time_ms' => 640,
            'created_at' => now()->subDays(1),
        ]);

        // 3. Reports
        Report::create([
            'title' => 'Q4 Sprint Velocity & Delivery Health Report',
            'report_type' => 'SprintPerformance',
            'project_id' => $project?->id,
            'team_id' => $team?->id,
            'target_user_id' => $backend?->id,
            'start_date' => now()->subDays(30),
            'end_date' => now(),
            'notes' => 'Sprint velocity exceeded initial forecasts by 18% with zero regression bugs reported.',
            'generated_by' => $pm?->id,
            'metrics_data' => [
                'tasks_completed' => 24,
                'story_points_delivered' => 86,
                'bug_density_rate' => 0.02,
                'code_coverage_percentage' => 92.4,
            ],
            'ai_analysis' => 'AI Insight: Team collaboration increased significantly after introducing asynchronous chat channels. High code quality observed across backend services.',
            'created_at' => now()->subDay(),
        ]);
    }
}
