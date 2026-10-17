<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meeting_id
 * @property int|null $speaker_id
 * @property string|null $speaker_name
 * @property string $time_offset
 * @property string $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MeetingTranscript extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'meeting_id',
        'speaker_id',
        'speaker_name',
        'time_offset',
        'content',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function speaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'speaker_id');
    }
}
