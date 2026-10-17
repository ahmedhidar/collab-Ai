<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'progress' => $this->progress ?? 0,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'due_date_human' => $this->due_date?->format('M d'),
            'comments_count' => (int) ($this->comments_count ?? $this->comments()->count()),
            'files_count' => (int) ($this->files_count ?? $this->files()->count()),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'assigned_team' => new TeamResource($this->whenLoaded('assignedTeam')),
            'assigned_user' => new UserResource($this->whenLoaded('assignedUser')),
            'reviewer' => new UserResource($this->whenLoaded('reviewer')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),
            'files' => $this->whenLoaded('files', function () {
                return $this->files->map(fn ($f) => [
                    'id' => $f->id,
                    'file_name' => $f->file_name,
                    'file_size' => $f->file_size,
                    'file_type' => $f->file_type,
                    'file_path' => $f->file_path,
                    'uploaded_by' => $f->uploader?->full_name,
                    'created_at' => $f->created_at?->toIso8601String(),
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
