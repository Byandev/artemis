<?php

namespace Modules\Creatives\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An Expo push token for one phone running the Creatives Tracker app. */
class PushToken extends Model
{
    protected $table = 'creatives_tracker_push_tokens';

    protected $guarded = [];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
