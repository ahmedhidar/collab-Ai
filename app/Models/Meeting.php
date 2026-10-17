<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property Carbon $meeting_date
 * @property string $start_time
 * @property string $end_time
 * @property string $meeting_link
 * @property int|null $project_id
 * @property int|null $team_id
 * @property int $organizer_id
 * @property string $status
 * @property bool $is_cancelled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Meeting extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'meeting_date',
        'start_time',
        'end_time',
        'meeting_link',
        'project_id',
        'team_id',
        'organizer_id',
        'status',
        'is_cancelled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'is_cancelled' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot(['attended', 'status']);
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(MeetingTranscript::class, 'meeting_id')->orderBy('id');
    }

    public function aiSummary(): HasOne
    {
        return $this->hasOne(AiMeetingSummary::class, 'meeting_id');
    }
}
