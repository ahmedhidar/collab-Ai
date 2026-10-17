<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
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
            'name' => $this->full_name ?? $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->system_role ?? 'TeamMember',
            'job_title' => $this->job_title,
            'years_of_experience' => $this->years_of_experience ?? 0,
            'availability_status' => $this->availability_status ?? 'Available',
            'avatar' => $this->image ?? null,
            'joined_at' => $this->pivot?->joined_at,
        ];
    }
}
