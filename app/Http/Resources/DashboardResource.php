<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kpis' => $this->resource['kpis'] ?? [],
            'project_overview' => $this->resource['project_overview'] ?? [],
            'active_projects' => $this->resource['active_projects'] ?? [],
            'team_progress' => $this->resource['team_progress'] ?? [],
            'recent_files' => $this->resource['recent_files'] ?? [],
            'upcoming_meetings' => $this->resource['upcoming_meetings'] ?? [],
        ];
    }
}
