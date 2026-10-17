<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'query' => $this->resource['query'] ?? '',
            'response' => $this->resource['response'] ?? '',
            'suggested_actions' => $this->resource['suggested_actions'] ?? [],
            'tokens_used' => $this->resource['tokens_used'] ?? null,
            'execution_time_ms' => $this->resource['execution_time_ms'] ?? null,
            'created_at' => $this->resource['created_at'] ?? now()->toIso8601String(),
        ];
    }
}
