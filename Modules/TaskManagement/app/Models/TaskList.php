<?php

namespace Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\TaskManagement\Database\Factories\TaskListFactory;
use Modules\TaskManagement\Models\Concerns\BelongsToTaskWorkspace;

/**
 * @property int $id
 * @property int $space_id
 * @property int|null $folder_id
 * @property string $name
 * @property string|null $description
 * @property int $position
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $archived_at
 */
class TaskList extends Model implements BelongsToTaskWorkspace
{
    /** @use HasFactory<TaskListFactory> */
    use HasFactory;

    protected $table = 'task_lists';

    protected $fillable = ['name', 'description', 'position', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
        'archived_at' => 'datetime',
    ];

    protected static function newFactory(): TaskListFactory
    {
        return TaskListFactory::new();
    }

    public function taskWorkspaceId(): ?int
    {
        return $this->space?->workspace_id;
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Limit the query to lists inside spaces the given user can see.
     *
     * @param  Builder<TaskList>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn('space_id', Space::query()->visibleTo($user)->select('id'));
    }
}
