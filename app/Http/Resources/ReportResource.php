<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title ?? ($this->report_type.' Report'),
            'report_type' => $this->report_type,
            'notes' => $this->notes,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'project' => $this->whenLoaded('project', function () {
                return $this->project ? [
                    'id' => $this->project->id,
                    'name' => $this->project->name ?? $this->project->title,
                ] : null;
            }),
            'team' => $this->whenLoaded('team', function () {
                return $this->team ? [
                    'id' => $this->team->id,
                    'name' => $this->team->name,
                ] : null;
            }),
            'target_user' => $this->whenLoaded('targetUser', function () {
                return $this->targetUser ? [
                    'id' => $this->targetUser->id,
                    'name' => $this->targetUser->full_name ?? $this->targetUser->name,
                    'avatar' => $this->targetUser->image ?? $this->targetUser->avatar ?? null,
                ] : null;
            }),
            'generator' => $this->whenLoaded('generator', function () {
                return $this->generator ? [
                    'id' => $this->generator->id,
                    'name' => $this->generator->full_name ?? $this->generator->name,
                    'avatar' => $this->generator->image ?? $this->generator->avatar ?? null,
                    'email' => $this->generator->email,
                ] : null;
            }),
            'metrics_data' => $this->metrics_data ?? [],
            'ai_analysis' => $this->ai_analysis,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
