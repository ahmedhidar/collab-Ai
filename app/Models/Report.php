<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $report_type
 * @property int|null $project_id
 * @property int|null $team_id
 * @property int|null $target_user_id
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string|null $notes
 * @property int|null $generated_by
 * @property array<array-key, mixed>|null $metrics_data
 * @property string|null $ai_analysis
 * @property Carbon $created_at
 */
class Report extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'report_type',
        'project_id',
        'team_id',
        'target_user_id',
        'start_date',
        'end_date',
        'notes',
        'generated_by',
        'metrics_data',
        'ai_analysis',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'metrics_data' => 'array',
            'created_at' => 'datetime',
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

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
