<?php

namespace Modules\Products\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A market the catalog is sold into — "Cardiovascular", with "Hypertension"
 * and friends underneath it.
 *
 * Deliberately two levels deep and no further: a row with a parent is a sub
 * category and can't take children of its own. The tree on the page, the
 * "Add under" picker and the counts in the header all assume that shape, so
 * anything deeper would render nowhere.
 */
class TargetMarket extends Model
{
    protected $fillable = [
        'workspace_id',
        'parent_id',
        'name',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->ordered();
    }

    /**
     * Siblings in the order they were arranged, with the name only as a
     * tiebreak for rows that share a position.
     */
    public function scopeOrdered(Builder $builder): Builder
    {
        return $builder->orderBy('position')->orderBy('name');
    }

    /**
     * Where the next row filed under $parentId belongs — the end of the list.
     */
    public static function nextPosition(Workspace $workspace, ?int $parentId): int
    {
        return (int) static::ofWorkspace($workspace)
            ->where('parent_id', $parentId)
            ->max('position') + 1;
    }

    public function scopeOfWorkspace(Builder $builder, Workspace $workspace): Builder
    {
        return $builder->where('workspace_id', $workspace->id);
    }

    /** Top-level rows — the categories. */
    public function scopeCategories(Builder $builder): Builder
    {
        return $builder->whereNull('parent_id');
    }

    /** The rows underneath them — the sub categories. */
    public function scopeSubCategories(Builder $builder): Builder
    {
        return $builder->whereNotNull('parent_id');
    }

    public function isCategory(): bool
    {
        return $this->parent_id === null;
    }
}
