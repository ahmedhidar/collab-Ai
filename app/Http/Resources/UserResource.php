<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->full_name ?? $this->name,
            'email' => $this->email,
            'role' => $this->system_role ?? $this->role ?? 'TeamMember',
            'avatar' => $this->image ?? $this->avatar ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
