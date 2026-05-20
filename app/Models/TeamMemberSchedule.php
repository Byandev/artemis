<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMemberSchedule extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'date',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
