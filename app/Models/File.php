<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $file_name
 * @property string $file_path
 * @property int $file_size_bytes
 * @property string|null $mime_type
 * @property int|null $project_id
 * @property int|null $task_id
 * @property int $uploaded_by
 * @property Carbon $uploaded_at
 */
class File extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'file_name',
        'file_path',
        'file_size_bytes',
        'mime_type',
        'project_id',
        'task_id',
        'uploaded_by',
        'uploaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function projectMessages(): HasMany
    {
        return $this->hasMany(ProjectMessage::class, 'attachment_file_id');
    }

    public function inboxMessages(): HasMany
    {
        return $this->hasMany(InboxMessage::class, 'attachment_file_id');
    }
}
