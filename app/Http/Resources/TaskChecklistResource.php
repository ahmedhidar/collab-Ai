<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskChecklistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $todoCollection = $this->resource['todo'] ?? collect();
        $doneCollection = $this->resource['done'] ?? collect();

        $formatTask = function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'progress' => (int) ($task->progress ?? ($task->status === 'Completed' ? 100 : 0)),
                'priority' => $task->priority,
                'due_date' => $task->due_date?->format('Y-m-d'),
                'is_completed' => $task->status === 'Completed',
                'project' => $task->project ? [
                    'id' => $task->project->id,
                    'name' => $task->project->name ?? $task->project->title,
                ] : null,
            ];
        };

        return [
            'todo_count' => $todoCollection->count(),
            'done_count' => $doneCollection->count(),
            'todo' => $todoCollection->map($formatTask),
            'done' => $doneCollection->map($formatTask),
        ];
    }
}
