<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stepping-stone daily-spend threshold under a goal's target (e.g. ₱300k on
 * the way to a ₱500k/day goal). Optional — a goal may have zero or several.
 */
class TeamAdSpendGoalMilestone extends Model
{
    protected $fillable = [
        'goal_id',
        'amount',
        'label',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function goal(): BelongsTo
    {
        return $this->belongsTo(TeamAdSpendGoal::class, 'goal_id');
    }
}
