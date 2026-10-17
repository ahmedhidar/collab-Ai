<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportAnalyticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'task_completion_rate' => [
                'rate' => $this->resource['task_completion_rate']['rate'] ?? 0,
                'change' => $this->resource['task_completion_rate']['change'] ?? 0,
                'trend' => $this->resource['task_completion_rate']['trend'] ?? 'neutral',
            ],
            'projects_completion_rate' => [
                'rate' => $this->resource['projects_completion_rate']['rate'] ?? 0,
                'change' => $this->resource['projects_completion_rate']['change'] ?? 0,
                'trend' => $this->resource['projects_completion_rate']['trend'] ?? 'neutral',
            ],
            'teams_activity_rate' => [
                'rate' => $this->resource['teams_activity_rate']['rate'] ?? 0,
                'change' => $this->resource['teams_activity_rate']['change'] ?? 0,
                'trend' => $this->resource['teams_activity_rate']['trend'] ?? 'neutral',
            ],
            'summary' => $this->resource['summary'] ?? [],
        ];
    }
}
