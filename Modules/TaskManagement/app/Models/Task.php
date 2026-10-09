<?php

namespace Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Modules\TaskManagement\Database\Factories\TaskFactory;
use Modules\TaskManagement\Enums\TaskPriority;
use Modules\TaskManagement\Models\Concerns\BelongsToTaskWorkspace;
use Modules\TaskManagement\Models\Concerns\HasTicketIdentifier;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $task_list_id
 * @property int $task_status_id
 * @property int|null $parent_id
 * @property int|null $created_by
 * @property string $name
 * @property string|null $ticket_code
 * @property int|null $ticket_number
 * @property string|null $description
 * @property TaskPriority|null $priority
 * @property int $position
 * @property int|null $estimate_minutes
 * @property Carbon|null $start_at
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $archived_at
 */
class Task extends Model implements BelongsToTaskWorkspace, HasMedia
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasTicketIdentifier, InteractsWithMedia;

    protected $table = 'tasks';

    protected $fillable = [
        'name',
        'description',
        'priority',
        'position',
        'estimate_minutes',
        'start_at',
        'due_at',
        'metadata',
    ];

    protected $casts = [
        'priority' => TaskPriority::class,
        'metadata' => 'array',
        'start_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    /**
     * The media collection every file attached to a task is stored in.
     *
     * One named collection rather than the package's `default`, so a later
     * feature that stores something else against a task -- a generated export,
     * a cover image -- cannot land in the list people see as "attachments".
     */
    public const ATTACHMENTS = 'attachments';

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    public function taskWorkspaceId(): ?int
    {
        return $this->workspace_id;
    }

    /**
     * Files attached to a task are Attachments rather than the app-wide media
     * model, so they carry the uploader and the task-space authorization.
     */
    public function getMediaModel(): string
    {
        return Attachment::class;
    }

    /**
     * Register the media collections this model stores files in. The disk is
     * private: every fetch goes through TaskAttachmentController, which
     * authorizes it and streams the bytes back.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENTS)
            ->useDisk(config('filesystems.task_attachment_disk'));
    }

    /**
     * The files attached to this task, oldest first.
     *
     * A relation of its own rather than the package's `media()`, so the query
     * can be eager loaded and counted like any other, and so nothing outside
     * the attachments collection can appear in it.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'model')
            ->where('collection_name', self::ATTACHMENTS)
            ->orderBy('order_column')
            ->orderBy('id');
    }

    /**
     * @return BelongsTo<TaskList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'task_list_id');
    }

    /**
     * @return BelongsTo<TaskStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'task_status_id');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees', 'task_id', 'user_id')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'task_label_task', 'task_id', 'label_id')->withTimestamps();
    }

    /**
     * Limit the query to tasks inside spaces the given user can see.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn('task_list_id', TaskList::query()->visibleTo($user)->select('id'));
    }

    /**
     * Limit the query to tasks that are not archived.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeNotArchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Get the ids of this task and every task nested under it.
     *
     * Walked level by level instead of with a recursive CTE, because it is only
     * ever used to purge a subtree's files before a delete and the depth of a
     * task tree here is small.
     *
     * @return list<int>
     */
    public function subtreeIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = array_values(array_map(
                intval(...),
                self::query()->whereIn('parent_id', $frontier)->pluck('id')->all(),
            ));

            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }
}
