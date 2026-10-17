<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Project\AssignProjectTeamRequest;
use App\Http\Requests\Api\Project\ProjectFilterRequest;
use App\Http\Requests\Api\Project\StoreProjectRequest;
use App\Http\Requests\Api\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectDetailResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends Controller
{
    public function __construct(
        protected ProjectService $projectService
    ) {}

    /**
     * Display a listing of projects with search, filter, and pagination.
     */
    public function index(ProjectFilterRequest $request): JsonResponse
    {
        $projects = $this->projectService->getProjects($request->validated());

        return $this->successResponse(
            data: [
                'items' => ProjectResource::collection($projects),
                'pagination' => [
                    'current_page' => $projects->currentPage(),
                    'last_page' => $projects->lastPage(),
                    'per_page' => $projects->perPage(),
                    'total' => $projects->total(),
                ],
            ],
            message: 'Projects retrieved successfully'
        );
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->createProject(
            $request->validated(),
            (int) $request->user()->id
        );

        return $this->successResponse(
            data: new ProjectResource($project),
            message: 'Project created successfully',
            statusCode: Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified project with detailed overview.
     */
    public function show(int $id): JsonResponse
    {
        $project = $this->projectService->getProjectDetail($id);

        return $this->successResponse(
            data: new ProjectDetailResource($project),
            message: 'Project details retrieved successfully'
        );
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $updatedProject = $this->projectService->updateProject($project, $request->validated());

        return $this->successResponse(
            data: new ProjectResource($updatedProject),
            message: 'Project updated successfully'
        );
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->projectService->deleteProject($project);

        return $this->successResponse(
            message: 'Project deleted successfully'
        );
    }

    /**
     * Assign a team to the project.
     */
    public function assignTeam(AssignProjectTeamRequest $request, Project $project): JsonResponse
    {
        $updatedProject = $this->projectService->assignTeam(
            $project,
            (int) $request->validated('team_id')
        );

        return $this->successResponse(
            data: new ProjectResource($updatedProject),
            message: 'Team assigned to project successfully'
        );
    }

    /**
     * Remove a team from the project.
     */
    public function removeTeam(Project $project, int $teamId): JsonResponse
    {
        $updatedProject = $this->projectService->removeTeam($project, $teamId);

        return $this->successResponse(
            data: new ProjectResource($updatedProject),
            message: 'Team removed from project successfully'
        );
    }
}
