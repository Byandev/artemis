<?php

namespace App\Models;

use App\Enums\DailyTrackerCadence;
use Database\Factories\DailyTrackerItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One deliverable on the Daily Tracker — a question every member of the team
 * answers for the day ("Is your ad spend in ERP updated…?").
 */
class DailyTrackerItem extends Model
{
    /** @use HasFactory<DailyTrackerItemFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'category',
        'label',
        'cadence',
        'tags',
        'position',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'cadence' => DailyTrackerCadence::class,
            'tags' => 'array',
            'position' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(DailyTrackerCompletion::class);
    }

    /**
     * The board's ordering: categories keep the order their first item was
     * given, and items keep theirs within a category.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
