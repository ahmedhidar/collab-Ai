<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TeamService
{
    /**
     * Retrieve all teams with associated counts and member relations.
     */
    public function getAllTeams(): Collection
    {
        return Team::withCount([
            'members',
            'projects as active_projects_count' => fn ($query) => $query->where('status', 'Active'),
            'tasks',
            'tasks as completed_tasks_count' => fn ($query) => $query->where('status', 'Completed'),
        ])
            ->with(['members'])
            ->latest()
            ->get();
    }

    /**
     * Find a team by its ID or fail.
     */
    public function getTeamById(int $id): Team
    {
        return Team::withCount([
            'members',
            'projects as active_projects_count' => fn ($query) => $query->where('status', 'Active'),
            'tasks',
            'tasks as completed_tasks_count' => fn ($query) => $query->where('status', 'Completed'),
        ])
            ->with(['members', 'projects'])
            ->findOrFail($id);
    }

    /**
     * Create a new team and optionally sync members.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTeam(array $data): Team
    {
        return DB::transaction(function () use ($data) {
            $team = Team::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'team_type' => $data['team_type'] ?? null,
            ]);

            if (! empty($data['member_ids'])) {
                $team->members()->sync($data['member_ids']);
            }

            return $team->load('members');
        });
    }

    /**
     * Update an existing team.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTeam(Team $team, array $data): Team
    {
        $team->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => array_key_exists('description', $data) ? $data['description'] : null,
            'team_type' => array_key_exists('team_type', $data) ? $data['team_type'] : null,
        ], fn ($val) => ! is_null($val)));

        return $team->fresh(['members', 'projects']);
    }

    /**
     * Delete a team.
     */
    public function deleteTeam(Team $team): bool
    {
        return DB::transaction(function () use ($team) {
            $team->members()->detach();
            $team->projects()->detach();

            return (bool) $team->delete();
        });
    }

    /**
     * Add a member to the specified team.
     */
    public function addMember(Team $team, int $userId): Team
    {
        $team->members()->syncWithoutDetaching([
            $userId => ['joined_at' => now()],
        ]);

        return $team->fresh(['members']);
    }

    /**
     * Remove a member from the specified team.
     */
    public function removeMember(Team $team, int $userId): Team
    {
        $team->members()->detach($userId);

        return $team->fresh(['members']);
    }
}
