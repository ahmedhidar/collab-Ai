<?php

namespace App\Services;

use App\Models\File;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Aggregate high-level KPI cards matching Figma dashboard.
     *
     * @return array<string, mixed>
     */
    public function getKpiStats(?int $projectId = null, ?User $user = null): array
    {
        $taskQuery = Task::query();

        if ($projectId) {
            $taskQuery->where('project_id', $projectId);
        }

        $stats = $taskQuery->selectRaw("
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks,
            SUM(CASE WHEN status = 'InProgress' THEN 1 ELSE 0 END) as in_progress_tasks,
            SUM(CASE WHEN status = 'InReview' THEN 1 ELSE 0 END) as in_review_tasks,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
        ")->first();

        $totalTasks = (int) ($stats->total_tasks ?? 0);
        $pendingTasks = (int) ($stats->pending_tasks ?? 0);
        $inProgressTasks = (int) ($stats->in_progress_tasks ?? 0);
        $inReviewTasks = (int) ($stats->in_review_tasks ?? 0);
        $completedTasks = (int) ($stats->completed_tasks ?? 0);
        $completionRate = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

        $projectQuery = Project::query();
        $totalProjects = $projectQuery->count();
        $activeProjects = (clone $projectQuery)->where('status', 'Active')->count();
        $activeMembers = User::count();

        return [
            'pending_tasks' => [
                'count' => $pendingTasks,
                'total_tasks' => $totalTasks,
                'growth_rate' => '+9% from last month',
                'progress_ratio' => "{$pendingTasks}/{$totalTasks}",
            ],
            'in_progress_tasks' => [
                'count' => $inProgressTasks,
                'total_tasks' => $totalTasks,
                'growth_rate' => '+12% from last month',
                'progress_ratio' => "{$inProgressTasks}/{$totalTasks}",
            ],
            'completed_tasks' => [
                'count' => $completedTasks,
                'total_tasks' => $totalTasks,
                'growth_rate' => '+16% from last month',
                'progress_ratio' => "{$completedTasks}/{$totalTasks}",
            ],
            'completion_rate' => [
                'percentage' => $completionRate,
                'growth_rate' => '+22% from last month',
            ],
            'summary' => [
                'total_projects' => $totalProjects,
                'active_projects' => $activeProjects,
                'active_members' => $activeMembers,
            ],
        ];
    }

    /**
     * Compute monthly overview datasets for the project overview chart.
     *
     * @return array<string, mixed>
     */
    public function getProjectOverviewChart(?int $projectId = null, ?int $year = null): array
    {
        $targetYear = $year ?? (int) now()->year;
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $createdPerMonth = array_fill(1, 12, 0);
        $completedPerMonth = array_fill(1, 12, 0);

        $query = Task::query()
            ->whereYear('created_at', $targetYear);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $tasks = $query->get(['id', 'status', 'created_at', 'updated_at']);

        foreach ($tasks as $task) {
            $createdMonth = (int) $task->created_at?->format('n');
            if ($createdMonth >= 1 && $createdMonth <= 12) {
                $createdPerMonth[$createdMonth]++;
            }

            if ($task->status === 'Completed' && $task->updated_at) {
                $completedMonth = (int) $task->updated_at->format('n');
                if ($completedMonth >= 1 && $completedMonth <= 12) {
                    $completedPerMonth[$completedMonth]++;
                }
            }
        }

        return [
            'year' => $targetYear,
            'labels' => $months,
            'datasets' => [
                [
                    'label' => 'Tasks Created',
                    'data' => array_values($createdPerMonth),
                ],
                [
                    'label' => 'Tasks Completed',
                    'data' => array_values($completedPerMonth),
                ],
            ],
        ];
    }

    /**
     * Retrieve top active projects with task progress indicators.
     */
    public function getActiveProjects(int $limit = 5): Collection
    {
        return Project::query()
            ->where('status', 'Active')
            ->with(['teams'])
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'Completed'),
            ])
            ->latest()
            ->take($limit)
            ->get()
            ->map(function (Project $project) {
                $total = (int) $project->tasks_count;
                $completed = (int) $project->completed_tasks_count;
                $percentage = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'progress_percentage' => $percentage,
                    'task_progress' => "{$completed} / {$total}",
                    'total_tasks' => $total,
                    'completed_tasks' => $completed,
                    'deadline' => $project->deadline?->format('Y-m-d'),
                    'teams' => $project->teams->map(fn ($team) => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'team_type' => $team->team_type,
                    ]),
                ];
            });
    }

    /**
     * Retrieve teams breakdown with member counts and completion rate.
     */
    public function getTeamProgress(): Collection
    {
        return Team::query()
            ->with(['members'])
            ->withCount([
                'members',
                'tasks',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'Completed'),
            ])
            ->get()
            ->map(function (Team $team) {
                $totalTasks = (int) $team->tasks_count;
                $completedTasks = (int) $team->completed_tasks_count;
                $completionRate = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'team_type' => $team->team_type,
                    'members_count' => (int) $team->members_count,
                    'completion_rate' => $completionRate,
                    'tasks_ratio' => "{$completedTasks}/{$totalTasks}",
                    'members' => $team->members->map(function (User $member) use ($team) {
                        $memberTotal = Task::where('assigned_team_id', $team->id)
                            ->where('assigned_user_id', $member->id)
                            ->count();
                        $memberCompleted = Task::where('assigned_team_id', $team->id)
                            ->where('assigned_user_id', $member->id)
                            ->where('status', 'Completed')
                            ->count();

                        return [
                            'id' => $member->id,
                            'name' => $member->full_name ?? $member->name,
                            'role' => $member->job_title ?? $member->system_role,
                            'avatar' => $member->image,
                            'tasks_ratio' => "{$memberCompleted}/{$memberTotal}",
                            'is_leader' => $member->isAdmin() || $member->isProjectManager(),
                        ];
                    }),
                ];
            });
    }

    /**
     * Retrieve recent file uploads across projects.
     */
    public function getRecentFiles(int $limit = 5): Collection
    {
        return File::query()
            ->with(['uploader', 'project'])
            ->latest('uploaded_at')
            ->take($limit)
            ->get()
            ->map(function (File $file) {
                return [
                    'id' => $file->id,
                    'file_name' => $file->file_name,
                    'file_size_bytes' => $file->file_size_bytes,
                    'mime_type' => $file->mime_type,
                    'uploaded_by' => $file->uploader?->full_name ?? $file->uploader?->name,
                    'uploaded_at' => $file->uploaded_at?->toIso8601String(),
                    'uploaded_at_human' => $file->uploaded_at?->diffForHumans(),
                    'project' => [
                        'id' => $file->project?->id,
                        'name' => $file->project?->name,
                    ],
                ];
            });
    }

    /**
     * Retrieve upcoming scheduled meetings.
     */
    public function getUpcomingMeetings(?User $user = null, int $limit = 5): Collection
    {
        return Meeting::query()
            ->with(['participants', 'project'])
            ->where('is_cancelled', false)
            ->whereDate('meeting_date', '>=', now()->toDateString())
            ->orderBy('meeting_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->take($limit)
            ->get()
            ->map(function (Meeting $meeting) {
                $isToday = $meeting->meeting_date?->isToday();
                $dateBadge = $isToday ? 'Today' : $meeting->meeting_date?->format('M d');

                $startTime = Carbon::parse($meeting->start_time)->format('H:i');
                $endTime = Carbon::parse($meeting->end_time)->format('H:i');

                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'date_badge' => $dateBadge,
                    'time_range' => "{$startTime} - {$endTime}",
                    'meeting_link' => $meeting->meeting_link,
                    'project' => [
                        'id' => $meeting->project?->id,
                        'name' => $meeting->project?->name,
                    ],
                    'participants' => $meeting->participants->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->full_name ?? $p->name,
                        'avatar' => $p->image,
                    ]),
                ];
            });
    }

    /**
     * Retrieve complete unified dashboard payload in a single response.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(?int $projectId = null, ?User $user = null, ?int $year = null): array
    {
        return [
            'kpis' => $this->getKpiStats($projectId, $user),
            'project_overview' => $this->getProjectOverviewChart($projectId, $year),
            'active_projects' => $this->getActiveProjects(),
            'team_progress' => $this->getTeamProgress(),
            'recent_files' => $this->getRecentFiles(),
            'upcoming_meetings' => $this->getUpcomingMeetings($user),
        ];
    }
}
