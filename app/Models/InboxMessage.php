<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $thread_id
 * @property int $sender_id
 * @property int|null $parent_message_id
 * @property string|null $content
 * @property int|null $attachment_file_id
 * @property bool $is_read
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InboxMessage extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'thread_id',
        'sender_id',
        'parent_message_id',
        'content',
        'attachment_file_id',
        'is_read',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(InboxThread::class, 'thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(InboxMessage::class, 'parent_message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(InboxMessage::class, 'parent_message_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(File::class, 'attachment_file_id');
    }

    /**
     * Scope a query to only include root-level messages.
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_message_id');
    }
}
