<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pm = User::where('system_role', 'ProjectManager')->first() ?? User::first();
        $admin = User::where('system_role', 'Admin')->first() ?? User::first();

        $backendTeam = Team::where('team_type', 'Backend')->first();
        $frontendTeam = Team::where('team_type', 'Frontend')->first();
        $uiuxTeam = Team::where('team_type', 'UI_UX')->first();
        $qaTeam = Team::where('team_type', 'QA')->first();
        $devopsTeam = Team::where('team_type', 'DevOps')->first();

        // Project 1
        $project1 = Project::create([
            'name' => 'CollabSpace AI Workspace Platform',
            'description' => 'The next-generation collaborative workspace enabling cross-functional teams with AI-assisted workflows and smart meeting notes.',
            'start_date' => now()->subMonths(1),
            'deadline' => now()->addMonths(3),
            'priority' => 'Critical',
            'status' => 'Active',
            'created_by' => $pm->id,
        ]);
        if ($backendTeam) {
            $project1->teams()->attach($backendTeam->id, ['assigned_at' => now()->subDays(25)]);
        }
        if ($frontendTeam) {
            $project1->teams()->attach($frontendTeam->id, ['assigned_at' => now()->subDays(25)]);
        }
        if ($uiuxTeam) {
            $project1->teams()->attach($uiuxTeam->id, ['assigned_at' => now()->subDays(25)]);
        }
        if ($qaTeam) {
            $project1->teams()->attach($qaTeam->id, ['assigned_at' => now()->subDays(20)]);
        }

        // Project 2
        $project2 = Project::create([
            'name' => 'AI Copilot & Smart Summarization Engine',
            'description' => 'Integration of LLM embeddings to automatically generate real-time meeting transcripts, risk assessments, and executive summaries.',
            'start_date' => now()->subDays(15),
            'deadline' => now()->addMonths(2),
            'priority' => 'High',
            'status' => 'Active',
            'created_by' => $admin->id,
        ]);
        if ($backendTeam) {
            $project2->teams()->attach($backendTeam->id, ['assigned_at' => now()->subDays(12)]);
        }
        if ($devopsTeam) {
            $project2->teams()->attach($devopsTeam->id, ['assigned_at' => now()->subDays(10)]);
        }

        // Project 3
        $project3 = Project::create([
            'name' => 'Multi-Region Cloud Infrastructure Migration',
            'description' => 'Scaling Kubernetes clusters, establishing read replicas, and hardening API security for high availability.',
            'start_date' => now()->subMonths(2),
            'deadline' => now()->subDays(5),
            'priority' => 'Medium',
            'status' => 'Done',
            'created_by' => $admin->id,
        ]);
        if ($devopsTeam) {
            $project3->teams()->attach($devopsTeam->id, ['assigned_at' => now()->subDays(55)]);
        }
    }
}
