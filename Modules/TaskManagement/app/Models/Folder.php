<?php

namespace Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\TaskManagement\Database\Factories\FolderFactory;
use Modules\TaskManagement\Models\Concerns\BelongsToTaskWorkspace;
use Modules\TaskManagement\Support\TicketCodes;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $space_id
 * @property string $name
 * @property string $code
 * @property int $last_ticket_number
 * @property string|null $description
 * @property int $position
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $archived_at
 */
class Folder extends Model implements BelongsToTaskWorkspace
{
    /** @use HasFactory<FolderFactory> */
    use HasFactory;

    protected $table = 'task_folders';

    protected $fillable = ['name', 'code', 'description', 'position', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
        'archived_at' => 'datetime',
    ];

    /**
     * Kept for callers that named it here; the namespace lives in TicketCodes.
     */
    public const UNASSIGNED_CODE = TicketCodes::FALLBACK;

    protected static function booted(): void
    {
        // The code namespace is per workspace, so the folder carries the
        // workspace of its space rather than looking it up on every check.
        static::creating(function (Folder $folder): void {
            $folder->workspace_id ??= Space::query()->whereKey($folder->space_id)->value('workspace_id');
            $folder->code ??= TicketCodes::firstFreeFor($folder->workspace_id, $folder->name);
        });
    }

    protected static function newFactory(): FolderFactory
    {
        return FolderFactory::new();
    }

    public static function deriveCodeFrom(string $name): string
    {
        return TicketCodes::deriveFrom($name);
    }

    public function taskWorkspaceId(): ?int
    {
        return $this->workspace_id;
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return HasMany<TaskList, $this>
     */
    public function lists(): HasMany
    {
        return $this->hasMany(TaskList::class);
    }

    /**
     * Limit the query to folders inside spaces the given user can see.
     *
     * @param  Builder<Folder>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn('space_id', Space::query()->visibleTo($user)->select('id'));
    }
}
