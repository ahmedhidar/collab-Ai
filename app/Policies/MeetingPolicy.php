<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    /**
     * Determine whether the user can view any meetings.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the meeting.
     */
    public function view(User $user, Meeting $meeting): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $meeting->organizer_id === (int) $user->id) {
            return true;
        }

        if ($meeting->participants()->where('users.id', $user->id)->exists()) {
            return true;
        }

        if ($meeting->project_id && $meeting->project?->teams()->whereHas('members', fn ($q) => $q->where('users.id', $user->id))->exists()) {
            return true;
        }

        if ($meeting->team_id && $meeting->team?->members()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create meetings.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['Admin', 'ProjectManager', 'TeamMember']);
    }

    /**
     * Determine whether the user can update the meeting.
     */
    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $meeting->organizer_id === (int) $user->id) {
            return true;
        }

        if ($user->isProjectManager() && $meeting->project_id && (int) $meeting->project?->manager_id === (int) $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the meeting.
     */
    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting);
    }

    /**
     * Determine whether the user can RSVP to the meeting.
     */
    public function rsvp(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting);
    }

    /**
     * Determine whether the user can manage participant attendance.
     */
    public function manageAttendance(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting);
    }

    /**
     * Determine whether the user can store transcripts.
     */
    public function storeTranscripts(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting);
    }

    /**
     * Determine whether the user can generate AI summaries.
     */
    public function generateSummary(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting);
    }
}
