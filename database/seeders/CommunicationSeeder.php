<?php

namespace Database\Seeders;

use App\Models\File;
use App\Models\InboxMessage;
use App\Models\InboxThread;
use App\Models\MessageMention;
use App\Models\MessageReadReceipt;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommunicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::first();
        $pm = User::where('system_role', 'ProjectManager')->first();
        $backend = User::where('email', 'backend@collabspace.ai')->first();
        $frontend = User::where('email', 'frontend@collabspace.ai')->first();
        $file = File::first();

        if ($project && $pm && $backend && $frontend) {
            // 1. Root project message
            $msg1 = ProjectMessage::create([
                'project_id' => $project->id,
                'sender_id' => $pm->id,
                'content' => 'Welcome team! Please review the attached architectural blueprint before the sprint kickoff.',
                'attachment_file_id' => $file?->id,
                'is_edited' => false,
                'created_at' => now()->subDays(3),
            ]);

            // Mentions
            MessageMention::create([
                'message_id' => $msg1->id,
                'mentioned_user_id' => $backend->id,
                'created_at' => now()->subDays(3),
            ]);
            MessageMention::create([
                'message_id' => $msg1->id,
                'mentioned_user_id' => $frontend->id,
                'created_at' => now()->subDays(3),
            ]);

            // Read receipts
            MessageReadReceipt::create([
                'message_id' => $msg1->id,
                'user_id' => $backend->id,
                'read_at' => now()->subDays(3)->addMinutes(15),
            ]);
            MessageReadReceipt::create([
                'message_id' => $msg1->id,
                'user_id' => $frontend->id,
                'read_at' => now()->subDays(3)->addMinutes(30),
            ]);

            // 2. Thread reply
            ProjectMessage::create([
                'project_id' => $project->id,
                'sender_id' => $backend->id,
                'parent_message_id' => $msg1->id,
                'content' => 'Looks solid! I have verified the database indexes and API contracts.',
                'is_edited' => false,
                'created_at' => now()->subDays(2),
            ]);
        }

        // Direct Inbox conversation between PM and Backend
        if ($pm && $backend) {
            $thread = InboxThread::create([
                'user_one_id' => $pm->id,
                'user_two_id' => $backend->id,
                'last_message_at' => now()->subMinutes(10),
                'created_at' => now()->subDays(5),
            ]);

            InboxMessage::create([
                'thread_id' => $thread->id,
                'sender_id' => $pm->id,
                'content' => 'Hey Kareem, how is the milestone 1 delivery looking?',
                'is_read' => true,
                'created_at' => now()->subHours(2),
            ]);

            InboxMessage::create([
                'thread_id' => $thread->id,
                'sender_id' => $backend->id,
                'content' => 'On track! All core migrations and unit tests are passing 100%.',
                'is_read' => false,
                'created_at' => now()->subMinutes(10),
            ]);
        }
    }
}
