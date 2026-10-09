<?php

namespace Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\TaskManagement\Database\Factories\LabelFactory;
use Modules\TaskManagement\Models\Concerns\BelongsToTaskWorkspace;

/**
 * @property int $id
 * @property int $space_id
 * @property string $name
 * @property string|null $color
 */
class Label extends Model implements BelongsToTaskWorkspace
{
    /** @use HasFactory<LabelFactory> */
    use HasFactory;

    protected $table = 'task_labels';

    protected $fillable = ['name', 'color'];

    protected static function newFactory(): LabelFactory
    {
        return LabelFactory::new();
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
     * @return BelongsToMany<Task, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_label_task', 'label_id', 'task_id');
    }

    /**
     * Limit the query to labels inside spaces the given user can see.
     *
     * @param  Builder<Label>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn('space_id', Space::query()->visibleTo($user)->select('id'));
    }
}
