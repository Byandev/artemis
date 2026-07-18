<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-member slice of a team goal's daily target. The members' targets are
 * expected to sum to at least the goal's daily target. Members have no
 * milestones of their own.
 */
class TeamAdSpendGoalMember extends Model
{
    protected $fillable = [
        'goal_id',
        'user_id',
        'daily_target',
    ];

    protected $casts = [
        'daily_target' => 'decimal:2',
    ];

    public function goal(): BelongsTo
    {
        return $this->belongsTo(TeamAdSpendGoal::class, 'goal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
