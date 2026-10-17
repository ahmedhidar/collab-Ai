<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Http\Resources\ProjectMessageResource;
use App\Models\File;
use App\Models\MessageReadReceipt;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChatService
{
    /**
     * Check if user is authorized to access the given project chat.
     */
    public function authorizeProjectAccess(Project $project, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($project->created_by === $user->id) {
            return;
        }

        $hasTeamAccess = $project->teams()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->exists();

        if (! $hasTeamAccess) {
            throw new AccessDeniedHttpException('You do not have access to this project chat.');
        }
    }

    /**
     * List all available chat channels for a project.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProjectChannels(int $projectId, User $user): array
    {
        $project = Project::with(['teams'])->findOrFail($projectId);
        $this->authorizeProjectAccess($project, $user);

        $channels = [
            [
                'id' => 'general',
                'name' => 'general',
                'label' => '#Public Channel',
                'type' => 'public',
                'team_id' => null,
                'project_id' => $project->id,
            ],
        ];

        foreach ($project->teams as $team) {
            $channels[] = [
                'id' => 'team_'.$team->id,
                'name' => 'team_'.$team->id,
                'label' => '#'.$team->name.' Channel',
                'type' => 'team',
                'team_id' => $team->id,
                'project_id' => $project->id,
            ];
        }

        return $channels;
    }

    /**
     * Retrieve paginated channel messages with sender, attachments, mentions, and replies.
     */
    public function getChannelMessages(int $projectId, string $channelName, User $user, int $perPage = 25): LengthAwarePaginator
    {
        $project = Project::findOrFail($projectId);
        $this->authorizeProjectAccess($project, $user);

        return ProjectMessage::query()
            ->where('project_id', $projectId)
            ->where('channel_name', $channelName)
            ->root()
            ->with([
                'sender',
                'attachment',
                'mentions',
                'replies.sender',
                'replies.attachment',
                'readReceipts',
            ])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Send a new message to a project channel.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendMessage(int $projectId, User $user, array $data, ?UploadedFile $uploadedFile = null): ProjectMessage
    {
        $project = Project::findOrFail($projectId);
        $this->authorizeProjectAccess($project, $user);

        $attachmentId = $data['attachment_file_id'] ?? null;

        if ($uploadedFile) {
            $storedPath = $uploadedFile->store('chat_attachments', 'public');

            $fileRecord = File::create([
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $storedPath,
                'file_size_bytes' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getClientMimeType(),
                'project_id' => $projectId,
                'uploaded_by' => $user->id,
                'uploaded_at' => now(),
            ]);

            $attachmentId = $fileRecord->id;
        }

        return DB::transaction(function () use ($projectId, $user, $data, $attachmentId) {
            $message = ProjectMessage::create([
                'project_id' => $projectId,
                'channel_name' => $data['channel_name'] ?? 'general',
                'team_id' => $data['team_id'] ?? null,
                'sender_id' => $user->id,
                'parent_message_id' => $data['parent_message_id'] ?? null,
                'content' => $data['content'] ?? null,
                'attachment_file_id' => $attachmentId,
            ]);

            if (! empty($data['mention_user_ids'])) {
                $message->mentions()->sync($data['mention_user_ids']);
            }

            // Mark automatically as read by sender
            MessageReadReceipt::firstOrCreate([
                'message_id' => $message->id,
                'user_id' => $user->id,
            ], ['read_at' => now()]);

            $loadedMessage = $message->load(['sender', 'attachment', 'mentions', 'replies']);

            // Real-time broadcasting event
            $resourceData = (new ProjectMessageResource($loadedMessage))->resolve();
            broadcast(new MessageSent($resourceData, 'project', $projectId))->toOthers();

            return $loadedMessage;
        });
    }

    /**
     * Mark specified messages in a project as read by the user.
     *
     * @param  array<int, int>  $messageIds
     */
    public function markMessagesAsRead(int $projectId, User $user, array $messageIds): void
    {
        $project = Project::findOrFail($projectId);
        $this->authorizeProjectAccess($project, $user);

        $validIds = ProjectMessage::where('project_id', $projectId)
            ->whereIn('id', $messageIds)
            ->pluck('id');

        foreach ($validIds as $id) {
            MessageReadReceipt::firstOrCreate([
                'message_id' => $id,
                'user_id' => $user->id,
            ], ['read_at' => now()]);
        }
    }

    /**
     * Retrieve recent chat conversations and accessible channels for the sidebar.
     */
    public function getRecentChannels(User $user): Collection
    {
        $projectsQuery = Project::query()->with(['teams']);

        if (! $user->isAdmin()) {
            $projectsQuery->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('teams.members', fn ($mq) => $mq->where('users.id', $user->id));
            });
        }

        $projects = $projectsQuery->get();
        $recent = collect();

        foreach ($projects as $project) {
            $latestMessage = ProjectMessage::where('project_id', $project->id)
                ->latest()
                ->first();

            $recent->push([
                'project_id' => $project->id,
                'project_name' => $project->name,
                'channel_name' => 'general',
                'channel_label' => "{$project->name} Public Channel",
                'latest_message' => $latestMessage?->content ?? 'No messages yet',
                'latest_time' => $latestMessage?->created_at?->format('H:i'),
            ]);
        }

        return $recent;
    }
}
