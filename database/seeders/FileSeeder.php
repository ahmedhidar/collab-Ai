<?php

namespace Database\Seeders;

use App\Models\File;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class FileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::first();
        $task = Task::first();
        $user = User::where('email', 'backend@collabspace.ai')->first() ?? User::first();

        if (! $project || ! $user) {
            return;
        }

        File::create([
            'file_name' => 'CollabSpace_System_Architecture.pdf',
            'file_path' => 'uploads/projects/architecture_v1.pdf',
            'file_size_bytes' => 4520100, // ~4.5 MB
            'mime_type' => 'application/pdf',
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'uploaded_by' => $user->id,
            'uploaded_at' => now()->subDays(10),
        ]);

        File::create([
            'file_name' => 'Database_ERD_Schema_Export.png',
            'file_path' => 'uploads/projects/erd_diagram.png',
            'file_size_bytes' => 1240500, // ~1.2 MB
            'mime_type' => 'image/png',
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'uploaded_by' => $user->id,
            'uploaded_at' => now()->subDays(8),
        ]);
    }
}
