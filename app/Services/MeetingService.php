<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingTranscript;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class MeetingService
{
    /**
     * List meetings accessible to the user, with filters.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Meeting>
     */
    public function list(User $user, array $filters = []): Collection
    {
        $query = Meeting::query()
            ->with([
                'organizer:id,full_name,name,email,image',
                'project:id,name',
                'team:id,name',
                'participants',
                'aiSummary',
            ])
            ->withCount('participants');

        $this->applyScope($query, $user);
        $this->applyFilters($query, $filters);

        return $query->orderBy('meeting_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
    }

    /**
     * Find meeting by ID with all relations.
     */
    public function find(int $id): Meeting
    {
        return Meeting::query()
            ->with([
                'organizer:id,full_name,name,email,image',
                'project:id,name',
                'team:id,name',
                'participants',
                'transcripts.speaker:id,full_name,name,image',
                'aiSummary',
            ])
            ->withCount('participants')
            ->findOrFail($id);
    }

    /**
     * Create a new meeting and attach initial participants.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Meeting
    {
        return DB::transaction(function () use ($user, $data) {
            $status = $data['status'] ?? 'scheduled';
            $isCancelled = $status === 'cancelled';

            $meeting = Meeting::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'meeting_date' => $data['meeting_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'meeting_link' => $data['meeting_link'] ?? $data['meeting_url'] ?? 'https://meet.collabspace.ai/'.uniqid(),
                'project_id' => $data['project_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'organizer_id' => $user->id,
                'status' => $status,
                'is_cancelled' => $isCancelled,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $participants = [];
            // Automatically add organizer as accepted participant
            $participants[$user->id] = ['status' => 'accepted', 'attended' => false];

            if (! empty($data['participant_ids']) && is_array($data['participant_ids'])) {
                foreach ($data['participant_ids'] as $participantId) {
                    if ((int) $participantId !== (int) $user->id) {
                        $participants[$participantId] = ['status' => 'pending', 'attended' => false];
                    }
                }
            }

            $meeting->participants()->sync($participants);

            return $this->find($meeting->id);
        });
    }

    /**
     * Update meeting metadata and participants.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Meeting $meeting, array $data): Meeting
    {
        return DB::transaction(function () use ($meeting, $data) {
            $updates = [];

            if (isset($data['title'])) {
                $updates['title'] = $data['title'];
            }
            if (array_key_exists('description', $data)) {
                $updates['description'] = $data['description'];
            }
            if (isset($data['meeting_date'])) {
                $updates['meeting_date'] = $data['meeting_date'];
            }
            if (isset($data['start_time'])) {
                $updates['start_time'] = $data['start_time'];
            }
            if (isset($data['end_time'])) {
                $updates['end_time'] = $data['end_time'];
            }
            if (isset($data['meeting_link']) || isset($data['meeting_url'])) {
                $updates['meeting_link'] = $data['meeting_link'] ?? $data['meeting_url'];
            }
            if (array_key_exists('project_id', $data)) {
                $updates['project_id'] = $data['project_id'];
            }
            if (array_key_exists('team_id', $data)) {
                $updates['team_id'] = $data['team_id'];
            }

            if (isset($data['status'])) {
                $updates['status'] = $data['status'];
                $updates['is_cancelled'] = $data['status'] === 'cancelled';
            } elseif (isset($data['is_cancelled'])) {
                $updates['is_cancelled'] = (bool) $data['is_cancelled'];
                if ($data['is_cancelled']) {
                    $updates['status'] = 'cancelled';
                }
            }

            $updates['updated_at'] = now();
            $meeting->update($updates);

            if (isset($data['participant_ids']) && is_array($data['participant_ids'])) {
                $existingPivot = $meeting->participants()->get()->keyBy('id');
                $syncData = [];

                // Always include organizer
                $syncData[$meeting->organizer_id] = [
                    'status' => $existingPivot->get($meeting->organizer_id)?->pivot?->status ?? 'accepted',
                    'attended' => (bool) ($existingPivot->get($meeting->organizer_id)?->pivot?->attended ?? false),
                ];

                foreach ($data['participant_ids'] as $pid) {
                    $pid = (int) $pid;
                    $syncData[$pid] = [
                        'status' => $existingPivot->get($pid)?->pivot?->status ?? 'pending',
                        'attended' => (bool) ($existingPivot->get($pid)?->pivot?->attended ?? false),
                    ];
                }

                $meeting->participants()->sync($syncData);
            }

            return $this->find($meeting->id);
        });
    }

    /**
     * Cancel a scheduled meeting.
     */
    public function cancel(Meeting $meeting): Meeting
    {
        return DB::transaction(function () use ($meeting) {
            $meeting->update([
                'status' => 'cancelled',
                'is_cancelled' => true,
                'updated_at' => now(),
            ]);

            return $this->find($meeting->id);
        });
    }

    /**
     * Delete a meeting.
     */
    public function delete(Meeting $meeting): bool
    {
        return (bool) $meeting->delete();
    }

    /**
     * Update RSVP status for a participant.
     */
    public function updateRsvp(Meeting $meeting, User $user, string $status): void
    {
        DB::transaction(function () use ($meeting, $user, $status) {
            $existing = $meeting->participants()->where('users.id', $user->id)->first();

            if ($existing) {
                $meeting->participants()->updateExistingPivot($user->id, ['status' => $status]);
            } else {
                $meeting->participants()->attach($user->id, [
                    'status' => $status,
                    'attended' => false,
                ]);
            }
        });
    }

    /**
     * Update attendance records for participants.
     *
     * @param  array<int, array{user_id: int, attended: bool}>  $attendances
     */
    public function updateAttendance(Meeting $meeting, array $attendances): void
    {
        DB::transaction(function () use ($meeting, $attendances) {
            foreach ($attendances as $record) {
                $userId = $record['user_id'];
                $attended = (bool) $record['attended'];

                $meeting->participants()->updateExistingPivot($userId, ['attended' => $attended]);
            }
        });
    }

    /**
     * Store transcript lines via file upload or payload.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, MeetingTranscript>
     */
    public function storeTranscripts(Meeting $meeting, array $data, ?UploadedFile $file = null): Collection
    {
        return DB::transaction(function () use ($meeting, $data, $file) {
            $transcriptItems = [];

            if ($file) {
                $transcriptItems = $this->parseTranscriptFile($file);
            } elseif (! empty($data['transcripts']) && is_array($data['transcripts'])) {
                $transcriptItems = $data['transcripts'];
            } elseif (! empty($data['content'])) {
                $transcriptItems = [[
                    'speaker_id' => $data['speaker_id'] ?? null,
                    'speaker_name' => $data['speaker_name'] ?? null,
                    'time_offset' => $data['time_offset'] ?? '00:00',
                    'content' => $data['content'],
                ]];
            }

            foreach ($transcriptItems as $item) {
                MeetingTranscript::create([
                    'meeting_id' => $meeting->id,
                    'speaker_id' => $item['speaker_id'] ?? null,
                    'speaker_name' => $item['speaker_name'] ?? $item['speaker'] ?? null,
                    'time_offset' => $item['time_offset'] ?? $item['time'] ?? '00:00',
                    'content' => $item['content'] ?? $item['text'] ?? '',
                ]);
            }

            return $meeting->transcripts()->with('speaker')->orderBy('id')->get();
        });
    }

    /**
     * Parse transcript from uploaded txt or json file.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseTranscriptFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'json' || str_starts_with(trim($content), '[')) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Parse text format: [00:01] Mohanad Wahib: Good morning everyone...
        $items = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Match [MM:SS] Speaker: Content
            if (preg_match('/^\[?(\d{1,2}:\d{2}(?::\d{2})?)\]?\s*([^:]+):\s*(.+)$/', $line, $matches)) {
                $items[] = [
                    'time_offset' => $matches[1],
                    'speaker_name' => trim($matches[2]),
                    'content' => trim($matches[3]),
                ];
            } else {
                $items[] = [
                    'time_offset' => '00:00',
                    'speaker_name' => null,
                    'content' => $line,
                ];
            }
        }

        return $items;
    }

    /**
     * Scope meeting query to accessible items for the user.
     */
    protected function applyScope(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('organizer_id', $user->id)
                ->orWhereHas('participants', function (Builder $partQuery) use ($user) {
                    $partQuery->where('users.id', $user->id);
                })
                ->orWhereHas('project', function (Builder $projQuery) use ($user) {
                    $projQuery->whereHas('teams.members', function (Builder $memQuery) use ($user) {
                        $memQuery->where('users.id', $user->id);
                    });
                })
                ->orWhereHas('team.members', function (Builder $teamQuery) use ($user) {
                    $teamQuery->where('users.id', $user->id);
                });
        });
    }

    /**
     * Apply filter parameters to meeting query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['date'])) {
            $query->whereDate('meeting_date', $filters['date']);
        } elseif (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->whereBetween('meeting_date', [$filters['start_date'], $filters['end_date']]);
        } elseif (! empty($filters['view'])) {
            $now = Carbon::now();
            switch ($filters['view']) {
                case 'today':
                    $query->whereDate('meeting_date', $now->toDateString());
                    break;
                case 'week':
                    $startOfWeek = $now->copy()->startOfWeek()->toDateString();
                    $endOfWeek = $now->copy()->endOfWeek()->toDateString();
                    $query->whereBetween('meeting_date', [$startOfWeek, $endOfWeek]);
                    break;
                case 'month':
                    $startOfMonth = $now->copy()->startOfMonth()->toDateString();
                    $endOfMonth = $now->copy()->endOfMonth()->toDateString();
                    $query->whereBetween('meeting_date', [$startOfMonth, $endOfMonth]);
                    break;
                case 'upcoming':
                    $query->whereDate('meeting_date', '>=', $now->toDateString())
                        ->where('is_cancelled', false);
                    break;
            }
        }
    }
}
