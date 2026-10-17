<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $team_type
 * @property Carbon $created_at
 */
class Team extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'team_type',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('joined_at');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_teams')
            ->withPivot('assigned_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_team_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'team_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'team_id');
    }

    public function projectMessages(): HasMany
    {
        return $this->hasMany(ProjectMessage::class, 'team_id');
    }
}
