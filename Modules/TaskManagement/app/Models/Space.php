<?php

namespace Modules\TaskManagement\Models;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\TaskManagement\Database\Factories\SpaceFactory;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Concerns\BelongsToTaskWorkspace;
use Modules\TaskManagement\Support\TicketCodes;

/**
 * The top layer of the Space > Folder > List > Task hierarchy. A space lives
 * inside one Artemis workspace and has its own owner and members, each with a
 * SpaceRole that decides what they may do inside it.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $owner_id
 * @property string $name
 * @property string|null $code
 * @property int $last_ticket_number
 * @property string|null $description
 * @property string|null $color
 * @property int $position
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $archived_at
 */
class Space extends Model implements BelongsToTaskWorkspace
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory;

    protected $table = 'task_spaces';

    protected $fillable = ['name', 'code', 'description', 'color', 'position', 'metadata'];

    /**
     * Mirror the database default so a freshly created space reports its position
     * before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
    ];

    protected $casts = [
        'metadata' => 'array',
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Every space numbers the tasks in its loose lists under its own code
        // (Artemis -> ART-1), so one is chosen as it is created when none was.
        static::creating(function (Space $space): void {
            $space->code ??= TicketCodes::firstFreeFor($space->workspace_id, $space->name);
        });
    }

    protected static function newFactory(): SpaceFactory
    {
        return SpaceFactory::new();
    }

    public function taskWorkspaceId(): ?int
    {
        return $this->workspace_id;
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_space_members', 'space_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /**
     * @return HasMany<TaskList, $this>
     */
    public function lists(): HasMany
    {
        return $this->hasMany(TaskList::class);
    }

    /**
     * @return HasMany<TaskStatus, $this>
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(TaskStatus::class);
    }

    /**
     * @return HasMany<Label, $this>
     */
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    /**
     * @return HasManyThrough<Task, TaskList, $this>
     */
    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, TaskList::class);
    }

    /**
     * Create the default set of statuses for the space.
     */
    public function seedDefaultStatuses(): void
    {
        foreach (TaskStatus::DEFAULTS as $position => $status) {
            $this->statuses()->create([...$status, 'position' => $position]);
        }
    }

    /**
     * The people who may be added to this space: the members of its Artemis
     * workspace, owner included. Matrix offered every account on the system;
     * here a space never reaches outside its workspace.
     *
     * @return Builder<User>
     */
    public function workspaceUsers(): Builder
    {
        return User::query()->where(fn (Builder $query) => $query
            ->whereIn('id', DB::table('workspace_user')->where('workspace_id', $this->workspace_id)->select('user_id'))
            ->orWhereIn('id', DB::table('workspaces')->where('id', $this->workspace_id)->select('owner_id')));
    }

    /**
     * Get the role the given user holds in the space, if any.
     */
    public function roleFor(User $user): ?SpaceRole
    {
        if ($this->owner_id === $user->id) {
            return SpaceRole::Owner;
        }

        $membership = $this->relationLoaded('members')
            ? $this->members->firstWhere('id', $user->id)
            : $this->members()->find($user->id);

        return $membership === null
            ? null
            : SpaceRole::from($membership->getAttribute('pivot')->role);
    }

    /**
     * Limit the query to spaces the given user owns or belongs to.
     *
     * @param  Builder<Space>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->where('owner_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id));
        });
    }

    /**
     * Limit the query to the spaces of one Artemis workspace.
     *
     * @param  Builder<Space>  $query
     */
    public function scopeInWorkspace(Builder $query, Workspace $workspace): void
    {
        $query->where('workspace_id', $workspace->id);
    }

    /**
     * Limit the query to spaces that are not archived.
     *
     * @param  Builder<Space>  $query
     */
    public function scopeNotArchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }
}
