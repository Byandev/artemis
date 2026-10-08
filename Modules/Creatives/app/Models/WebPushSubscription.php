<?php

namespace Modules\Creatives\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A browser's Web Push subscription, for the Creatives Tracker web app (PWA). */
class WebPushSubscription extends Model
{
    protected $table = 'creatives_tracker_web_push_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function scopeForEndpoint(Builder $query, string $endpoint): Builder
    {
        return $query->where('endpoint_hash', static::hashEndpoint($endpoint));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
