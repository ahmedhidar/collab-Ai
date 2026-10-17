<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $team_id
 * @property string $channel_name
 * @property int $sender_id
 * @property int|null $parent_message_id
 * @property string|null $content
 * @property int|null $attachment_file_id
 * @property bool $is_edited
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProjectMessage extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'team_id',
        'channel_name',
        'sender_id',
        'parent_message_id',
        'content',
        'attachment_file_id',
        'is_edited',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_edited' => 'boolean',
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

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProjectMessage::class, 'parent_message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ProjectMessage::class, 'parent_message_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(File::class, 'attachment_file_id');
    }

    public function mentions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_mentions', 'message_id', 'mentioned_user_id');
    }

    public function readReceipts(): HasMany
    {
        return $this->hasMany(MessageReadReceipt::class, 'message_id');
    }

    /**
     * Scope a query to only include root-level messages.
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_message_id');
    }
}
