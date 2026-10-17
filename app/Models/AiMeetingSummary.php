<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meeting_id
 * @property string|null $summary_text
 * @property array<array-key, mixed>|null $key_points
 * @property array<array-key, mixed>|null $decisions_made
 * @property array<array-key, mixed>|null $action_items
 * @property array<array-key, mixed>|null $risks_identified
 * @property string|null $next_steps
 * @property Carbon $generated_at
 * @property Carbon|null $updated_at
 */
class AiMeetingSummary extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'meeting_id',
        'summary_text',
        'key_points',
        'decisions_made',
        'action_items',
        'risks_identified',
        'next_steps',
        'generated_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key_points' => 'array',
            'decisions_made' => 'array',
            'action_items' => 'array',
            'risks_identified' => 'array',
            'generated_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }
}
