<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingParticipantResource extends JsonResource
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
            'status' => $this->pivot?->status ?? 'pending',
            'attended' => (bool) ($this->pivot?->attended ?? false),
        ];
    }
}
