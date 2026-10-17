<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Dashboard\DashboardFilterRequest;
use App\Http\Resources\DashboardResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Retrieve the complete unified dashboard dataset in a single call.
     */
    public function index(DashboardFilterRequest $request): JsonResponse
    {
        $data = $this->dashboardService->getDashboardData(
            $request->validated('project_id'),
            $request->user(),
            $request->validated('year')
        );

        return $this->successResponse(
            data: new DashboardResource($data),
            message: 'Dashboard data retrieved successfully'
        );
    }

    /**
     * Retrieve high-level KPI cards and task distribution counts.
     */
    public function stats(DashboardFilterRequest $request): JsonResponse
    {
        $stats = $this->dashboardService->getKpiStats(
            $request->validated('project_id'),
            $request->user()
        );

        return $this->successResponse(
            data: $stats,
            message: 'KPI metrics retrieved successfully'
        );
    }

    /**
     * Retrieve project overview monthly chart data.
     */
    public function chart(DashboardFilterRequest $request): JsonResponse
    {
        $chart = $this->dashboardService->getProjectOverviewChart(
            $request->validated('project_id'),
            $request->validated('year')
        );

        return $this->successResponse(
            data: $chart,
            message: 'Project overview chart data retrieved successfully'
        );
    }

    /**
     * Retrieve top active projects summary with progress percentages.
     */
    public function activeProjects(): JsonResponse
    {
        $activeProjects = $this->dashboardService->getActiveProjects();

        return $this->successResponse(
            data: $activeProjects,
            message: 'Active projects retrieved successfully'
        );
    }

    /**
     * Retrieve team progress and workload distribution.
     */
    public function teamProgress(): JsonResponse
    {
        $teamProgress = $this->dashboardService->getTeamProgress();

        return $this->successResponse(
            data: $teamProgress,
            message: 'Team progress retrieved successfully'
        );
    }

    /**
     * Retrieve recent file uploads across projects.
     */
    public function recentFiles(): JsonResponse
    {
        $recentFiles = $this->dashboardService->getRecentFiles();

        return $this->successResponse(
            data: $recentFiles,
            message: 'Recent files retrieved successfully'
        );
    }

    /**
     * Retrieve upcoming scheduled meetings.
     */
    public function upcomingMeetings(Request $request): JsonResponse
    {
        $meetings = $this->dashboardService->getUpcomingMeetings($request->user());

        return $this->successResponse(
            data: $meetings,
            message: 'Upcoming meetings retrieved successfully'
        );
    }
}
