<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $start_date
 * @property Carbon|null $deadline
 * @property string $priority
 * @property string $status
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Project extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'start_date',
        'deadline',
        'priority',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'deadline' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_teams')
            ->withPivot('assigned_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class, 'project_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProjectMessage::class, 'project_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'project_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'project_id');
    }
}
