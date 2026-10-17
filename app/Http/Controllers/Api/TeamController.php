<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Team\AssignTeamMemberRequest;
use App\Http\Requests\Api\Team\StoreTeamRequest;
use App\Http\Requests\Api\Team\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TeamController extends Controller
{
    public function __construct(
        protected TeamService $teamService
    ) {}

    /**
     * Display a listing of all teams.
     */
    public function index(): JsonResponse
    {
        $teams = $this->teamService->getAllTeams();

        return $this->successResponse(
            data: TeamResource::collection($teams),
            message: 'Teams retrieved successfully'
        );
    }

    /**
     * Store a newly created team.
     */
    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = $this->teamService->createTeam($request->validated());

        return $this->successResponse(
            data: new TeamResource($team),
            message: 'Team created successfully',
            statusCode: Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified team.
     */
    public function show(int $id): JsonResponse
    {
        $team = $this->teamService->getTeamById($id);

        return $this->successResponse(
            data: new TeamResource($team),
            message: 'Team details retrieved successfully'
        );
    }

    /**
     * Update the specified team.
     */
    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        $updatedTeam = $this->teamService->updateTeam($team, $request->validated());

        return $this->successResponse(
            data: new TeamResource($updatedTeam),
            message: 'Team updated successfully'
        );
    }

    /**
     * Remove the specified team.
     */
    public function destroy(Team $team): JsonResponse
    {
        $this->teamService->deleteTeam($team);

        return $this->successResponse(
            message: 'Team deleted successfully'
        );
    }

    /**
     * Add a member to the specified team.
     */
    public function addMember(AssignTeamMemberRequest $request, Team $team): JsonResponse
    {
        $updatedTeam = $this->teamService->addMember($team, (int) $request->validated('user_id'));

        return $this->successResponse(
            data: new TeamResource($updatedTeam),
            message: 'Member added to team successfully'
        );
    }

    /**
     * Remove a member from the specified team.
     */
    public function removeMember(Team $team, int $userId): JsonResponse
    {
        $updatedTeam = $this->teamService->removeMember($team, $userId);

        return $this->successResponse(
            data: new TeamResource($updatedTeam),
            message: 'Member removed from team successfully'
        );
    }
}
