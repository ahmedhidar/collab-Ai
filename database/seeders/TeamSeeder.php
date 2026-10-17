<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $backendUser = User::where('email', 'backend@collabspace.ai')->first();
        $frontendUser = User::where('email', 'frontend@collabspace.ai')->first();
        $uiuxUser = User::where('email', 'uiux@collabspace.ai')->first();
        $qaUser = User::where('email', 'qa@collabspace.ai')->first();
        $devopsUser = User::where('email', 'devops@collabspace.ai')->first();
        $marketingUser = User::where('email', 'marketing@collabspace.ai')->first();
        $pmUser = User::where('email', 'pm@collabspace.ai')->first();

        // 1. Backend Team
        $backendTeam = Team::create([
            'name' => 'Core Backend Engineering',
            'description' => 'Responsible for microservices, REST APIs, database schemas, and AI workflow pipelines.',
            'team_type' => 'Backend',
        ]);
        if ($backendUser) {
            $backendTeam->members()->attach($backendUser->id, ['joined_at' => now()->subDays(60)]);
        }
        if ($pmUser) {
            $backendTeam->members()->attach($pmUser->id, ['joined_at' => now()->subDays(60)]);
        }

        // 2. Frontend Team
        $frontendTeam = Team::create([
            'name' => 'Web & Mobile Client Experience',
            'description' => 'Building modern responsive interfaces, interactive dashboards, and real-time collaboration canvas.',
            'team_type' => 'Frontend',
        ]);
        if ($frontendUser) {
            $frontendTeam->members()->attach($frontendUser->id, ['joined_at' => now()->subDays(50)]);
        }
        if ($pmUser) {
            $frontendTeam->members()->attach($pmUser->id, ['joined_at' => now()->subDays(50)]);
        }

        // 3. UI/UX Team
        $uiuxTeam = Team::create([
            'name' => 'Product Design & UI/UX',
            'description' => 'Creating user journeys, design systems in Figma, and interaction prototypes.',
            'team_type' => 'UI_UX',
        ]);
        if ($uiuxUser) {
            $uiuxTeam->members()->attach($uiuxUser->id, ['joined_at' => now()->subDays(70)]);
        }

        // 4. QA Team
        $qaTeam = Team::create([
            'name' => 'Quality Assurance & Testing',
            'description' => 'Automated regression testing, load testing, integration test pipelines, and QA signoffs.',
            'team_type' => 'QA',
        ]);
        if ($qaUser) {
            $qaTeam->members()->attach($qaUser->id, ['joined_at' => now()->subDays(40)]);
        }

        // 5. DevOps Team
        $devopsTeam = Team::create([
            'name' => 'Cloud Infrastructure & DevOps',
            'description' => 'Docker, Kubernetes clusters, CI/CD pipelines, observability, and server security.',
            'team_type' => 'DevOps',
        ]);
        if ($devopsUser) {
            $devopsTeam->members()->attach($devopsUser->id, ['joined_at' => now()->subDays(80)]);
        }

        // 6. Marketing Team
        $marketingTeam = Team::create([
            'name' => 'Growth & Product Marketing',
            'description' => 'User acquisition, analytics, customer onboarding campaigns, and product announcements.',
            'team_type' => 'Marketing',
        ]);
        if ($marketingUser) {
            $marketingTeam->members()->attach($marketingUser->id, ['joined_at' => now()->subDays(30)]);
        }
    }
}
