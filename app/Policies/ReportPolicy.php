<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * Determine whether the user can view any reports.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the report.
     */
    public function view(User $user, Report $report): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $report->generated_by === (int) $user->id) {
            return true;
        }

        if ((int) $report->target_user_id === (int) $user->id) {
            return true;
        }

        if ($report->project_id && $report->project?->teams()->whereHas('members', fn ($q) => $q->where('users.id', $user->id))->exists()) {
            return true;
        }

        if ($report->team_id && $report->team?->members()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create reports.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['Admin', 'ProjectManager', 'TeamMember']);
    }

    /**
     * Determine whether the user can delete the report.
     */
    public function delete(User $user, Report $report): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $report->generated_by === (int) $user->id) {
            return true;
        }

        if ($user->isProjectManager() && $report->project_id && (int) $report->project?->created_by === (int) $user->id) {
            return true;
        }

        return false;
    }
}
