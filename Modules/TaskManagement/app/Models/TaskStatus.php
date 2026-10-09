<?php

namespace Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\TaskManagement\Database\Factories\TaskStatusFactory;
use Modules\TaskManagement\Enums\TaskStatusType;
use Modules\TaskManagement\Models\Concerns\BelongsToTaskWorkspace;

/**
 * @property int $id
 * @property int $space_id
 * @property string $name
 * @property string $slug
 * @property string|null $color
 * @property TaskStatusType $type
 * @property int $position
 * @property bool $is_default
 */
class TaskStatus extends Model implements BelongsToTaskWorkspace
{
    /** @use HasFactory<TaskStatusFactory> */
    use HasFactory;

    protected $table = 'task_statuses';

    protected $fillable = ['name', 'slug', 'color', 'type', 'position', 'is_default'];

    protected $casts = [
        'type' => TaskStatusType::class,
        'is_default' => 'boolean',
    ];

    /**
     * The default statuses seeded into every new space.
     *
     * @var array<int, array{name: string, slug: string, color: string, type: TaskStatusType, is_default: bool}>
     */
    public const DEFAULTS = [
        ['name' => 'To Do', 'slug' => 'to-do', 'color' => '#8E90A6', 'type' => TaskStatusType::NotStarted, 'is_default' => true],
        ['name' => 'In Progress', 'slug' => 'in-progress', 'color' => '#2D7CC9', 'type' => TaskStatusType::Active, 'is_default' => false],
        ['name' => 'Done', 'slug' => 'done', 'color' => '#0F8C77', 'type' => TaskStatusType::Done, 'is_default' => false],
    ];

    protected static function newFactory(): TaskStatusFactory
    {
        return TaskStatusFactory::new();
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
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Limit the query to statuses inside spaces the given user can see.
     *
     * @param  Builder<TaskStatus>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn('space_id', Space::query()->visibleTo($user)->select('id'));
    }
}
