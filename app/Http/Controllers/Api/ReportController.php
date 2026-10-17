<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Report\AnalyticsFilterRequest;
use App\Http\Requests\Api\Report\ReportFilterRequest;
use App\Http\Requests\Api\Report\SubmitReportRequest;
use App\Http\Resources\ReportAnalyticsResource;
use App\Http\Resources\ReportResource;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Get high-level KPI cards (Task, Projects, and Teams rates).
     */
    public function analytics(AnalyticsFilterRequest $request): JsonResponse
    {
        $kpis = $this->reportService->getAnalyticsKpis(
            $request->input('project_id'),
            $request->input('team_id')
        );

        return $this->successResponse(
            data: new ReportAnalyticsResource($kpis),
            message: 'Report analytics retrieved successfully'
        );
    }

    /**
     * Get Task Completion Time vs. Deadline bar chart dataset.
     */
    public function taskDeadlineChart(AnalyticsFilterRequest $request): JsonResponse
    {
        $chartData = $this->reportService->getTaskDeadlineChart($request->validated());

        return $this->successResponse(
            data: $chartData,
            message: 'Task deadline chart data retrieved successfully'
        );
    }

    /**
     * Get Projects Progress area chart dataset.
     */
    public function projectsProgressChart(AnalyticsFilterRequest $request): JsonResponse
    {
        $chartData = $this->reportService->getProjectsProgressChart($request->validated());

        return $this->successResponse(
            data: $chartData,
            message: 'Projects progress chart data retrieved successfully'
        );
    }

    /**
     * Get Worker Activity Rate line chart dataset (monthly or weekly).
     */
    public function workerActivityChart(AnalyticsFilterRequest $request): JsonResponse
    {
        $period = (string) $request->input('period', 'monthly');
        $chartData = $this->reportService->getWorkerActivityChart($period, $request->validated());

        return $this->successResponse(
            data: $chartData,
            message: 'Worker activity chart data retrieved successfully'
        );
    }

    /**
     * Display a listing of generated reports.
     */
    public function index(ReportFilterRequest $request): JsonResponse
    {
        $reports = $this->reportService->listReports($request->user(), $request->validated());

        return $this->successResponse(
            data: [
                'items' => ReportResource::collection($reports),
                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'last_page' => $reports->lastPage(),
                    'per_page' => $reports->perPage(),
                    'total' => $reports->total(),
                ],
            ],
            message: 'Reports retrieved successfully'
        );
    }

    /**
     * Submit a detailed custom report.
     */
    public function store(SubmitReportRequest $request): JsonResponse
    {
        $report = $this->reportService->createReport($request->user(), $request->validated());

        return $this->successResponse(
            data: new ReportResource($report),
            message: 'Report submitted successfully',
            statusCode: Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified report.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $report = $this->reportService->findReport($id);
        Gate::authorize('view', $report);

        return $this->successResponse(
            data: new ReportResource($report),
            message: 'Report details retrieved successfully'
        );
    }

    /**
     * Remove the specified report.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $report = $this->reportService->findReport($id);
        Gate::authorize('delete', $report);

        $this->reportService->deleteReport($report);

        return $this->successResponse(
            data: ['deleted' => true],
            message: 'Report deleted successfully'
        );
    }

    /**
     * Trigger AI analysis/insights generation for a report.
     */
    public function generateAiInsights(Request $request, int $id): JsonResponse
    {
        $report = $this->reportService->findReport($id);
        Gate::authorize('view', $report);

        $updated = $this->reportService->generateAiInsights($report, $request->user());

        return $this->successResponse(
            data: new ReportResource($updated),
            message: 'Report AI insights generated successfully'
        );
    }
}
