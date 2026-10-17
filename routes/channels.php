<?php

use App\Models\InboxThread;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('user.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('project.{projectId}', function (User $user, int $projectId) {
    if ($user->isAdmin()) {
        return true;
    }

    $project = Project::find($projectId);
    if (! $project) {
        return false;
    }

    if ($project->created_by === $user->id) {
        return true;
    }

    return $project->teams()
        ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
        ->exists();
});

Broadcast::channel('inbox.{threadId}', function (User $user, int $threadId) {
    if ($user->isAdmin()) {
        return true;
    }

    return InboxThread::where('id', $threadId)
        ->forUser($user->id)
        ->exists();
});
