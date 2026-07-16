<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A per-team daily ad-spend goal: the team should hit `daily_target` in ad spend
 * on a given day, judged over the [start_date, end_date] window. The goal is a
 * daily milestone, not a running total — status is measured against the most
 * recent complete day (yesterday) and the team's peak day within the window.
 */
class TeamAdSpendGoal extends Model
{
    protected $fillable = [
        'workspace_id',
        'team_id',
        'daily_target',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'daily_target' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Stepping-stone thresholds under the target, ascending (optional).
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(TeamAdSpendGoalMilestone::class, 'goal_id')
            ->orderBy('amount');
    }

    /**
     * Per-member slices of the daily target (optional).
     */
    public function members(): HasMany
    {
        return $this->hasMany(TeamAdSpendGoalMember::class, 'goal_id');
    }
}
