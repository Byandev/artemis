<?php

namespace Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\TaskManagement\Database\Factories\CommentFactory;
use Modules\TaskManagement\Models\Concerns\BelongsToTaskWorkspace;

/**
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $edited_at
 */
class Comment extends Model implements BelongsToTaskWorkspace
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    protected $table = 'task_comments';

    protected $fillable = ['body'];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    protected static function newFactory(): CommentFactory
    {
        return CommentFactory::new();
    }

    public function taskWorkspaceId(): ?int
    {
        return $this->task?->workspace_id;
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Whether the comment has been changed since it was posted.
     */
    public function wasEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /**
     * Limit the query to comments on tasks inside spaces the given user can see.
     *
     * @param  Builder<Comment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn('task_id', Task::query()->visibleTo($user)->select('id'));
    }
}
