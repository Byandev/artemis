<?php

namespace Modules\Pancake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PancakeUserDailyEngagement extends Model
{
    protected $table = 'pancake_user_daily_engagements';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'pancake_user_id' => 'string',
    ];

    public function pancakeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pancake_user_id');
    }
}
