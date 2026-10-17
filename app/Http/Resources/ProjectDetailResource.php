<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalTasks = (int) ($this->tasks_count ?? $this->tasks()->count());
        $pendingTasks = (int) ($this->pending_tasks_count ?? $this->tasks()->where('status', 'Pending')->count());
        $inProgressTasks = (int) ($this->in_progress_tasks_count ?? $this->tasks()->where('status', 'In Progress')->count());
        $inReviewTasks = (int) ($this->in_review_tasks_count ?? $this->tasks()->where('status', 'In Review')->count());
        $completedTasks = (int) ($this->completed_tasks_count ?? $this->tasks()->where('status', 'Completed')->count());
        $progress = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'deadline' => $this->deadline?->format('Y-m-d'),
            'priority' => $this->priority,
            'status' => $this->status,
            'progress_percentage' => $progress,
            'stats' => [
                'total_tasks' => $totalTasks,
                'pending_tasks' => $pendingTasks,
                'in_progress_tasks' => $inProgressTasks,
                'in_review_tasks' => $inReviewTasks,
                'completed_tasks' => $completedTasks,
                'files_count' => (int) ($this->files_count ?? $this->files()->count()),
            ],
            'creator' => new UserResource($this->whenLoaded('creator')),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'recent_files' => $this->whenLoaded('files', function () {
                return $this->files->take(5)->map(fn ($file) => [
                    'id' => $file->id,
                    'file_name' => $file->file_name,
                    'file_type' => $file->file_type,
                    'file_size' => $file->file_size,
                    'file_path' => $file->file_path,
                    'uploaded_by' => $file->uploader?->full_name,
                    'created_at' => $file->created_at?->toIso8601String(),
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
