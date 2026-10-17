<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Http\Resources\InboxMessageResource;
use App\Models\File;
use App\Models\InboxMessage;
use App\Models\InboxThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DirectMessageService
{
    /**
     * Retrieve all direct message threads for the user.
     */
    public function getUserThreads(User $user): Collection
    {
        return InboxThread::query()
            ->forUser($user->id)
            ->with(['userOne', 'userTwo', 'latestMessage.sender'])
            ->orderByDesc('last_message_at')
            ->get();
    }

    /**
     * Retrieve paginated message history for a specific direct thread.
     */
    public function getThreadMessages(int $threadId, User $user, int $perPage = 30): LengthAwarePaginator
    {
        $thread = InboxThread::findOrFail($threadId);
        $this->authorizeThreadAccess($thread, $user);

        // Mark incoming unread messages as read automatically
        InboxMessage::where('thread_id', $thread->id)
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return InboxMessage::query()
            ->where('thread_id', $threadId)
            ->root()
            ->with(['sender', 'attachment', 'replies.sender', 'replies.attachment'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Start a new thread or retrieve an existing one between two users.
     */
    public function startOrGetThread(User $user, int $recipientId, ?string $content = null): InboxThread
    {
        $recipient = User::findOrFail($recipientId);

        $thread = InboxThread::where(function ($q) use ($user, $recipient) {
            $q->where('user_one_id', $user->id)->where('user_two_id', $recipient->id);
        })->orWhere(function ($q) use ($user, $recipient) {
            $q->where('user_one_id', $recipient->id)->where('user_two_id', $user->id);
        })->first();

        if (! $thread) {
            $thread = InboxThread::create([
                'user_one_id' => $user->id,
                'user_two_id' => $recipient->id,
                'last_message_at' => now(),
                'created_at' => now(),
            ]);
        }

        if (! empty($content)) {
            $this->sendMessage($thread->id, $user, ['content' => $content]);
        }

        return $thread->load(['userOne', 'userTwo', 'latestMessage.sender']);
    }

    /**
     * Send a direct message in a thread.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendMessage(int $threadId, User $user, array $data, ?UploadedFile $uploadedFile = null): InboxMessage
    {
        $thread = InboxThread::findOrFail($threadId);
        $this->authorizeThreadAccess($thread, $user);

        $attachmentId = $data['attachment_file_id'] ?? null;

        if ($uploadedFile) {
            $storedPath = $uploadedFile->store('inbox_attachments', 'public');

            $fileRecord = File::create([
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $storedPath,
                'file_size_bytes' => $uploadedFile->getSize(),
                'mime_type' => $uploadedFile->getClientMimeType(),
                'project_id' => null,
                'uploaded_by' => $user->id,
                'uploaded_at' => now(),
            ]);

            $attachmentId = $fileRecord->id;
        }

        return DB::transaction(function () use ($thread, $user, $data, $attachmentId) {
            $message = InboxMessage::create([
                'thread_id' => $thread->id,
                'sender_id' => $user->id,
                'parent_message_id' => $data['parent_message_id'] ?? null,
                'content' => $data['content'] ?? null,
                'attachment_file_id' => $attachmentId,
                'is_read' => false,
            ]);

            $thread->update(['last_message_at' => now()]);

            $loadedMessage = $message->load(['sender', 'attachment', 'replies']);

            // Broadcast real-time message event
            $resourceData = (new InboxMessageResource($loadedMessage))->resolve();
            broadcast(new MessageSent($resourceData, 'inbox', $thread->id))->toOthers();

            return $loadedMessage;
        });
    }

    /**
     * Mark all unread messages in a thread as read.
     */
    public function markThreadAsRead(int $threadId, User $user): void
    {
        $thread = InboxThread::findOrFail($threadId);
        $this->authorizeThreadAccess($thread, $user);

        InboxMessage::where('thread_id', $threadId)
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Authorize user access to the direct message thread.
     */
    protected function authorizeThreadAccess(InboxThread $thread, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($thread->user_one_id !== $user->id && $thread->user_two_id !== $user->id) {
            throw new AccessDeniedHttpException('You are not a participant in this conversation.');
        }
    }
}
