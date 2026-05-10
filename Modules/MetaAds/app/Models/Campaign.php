<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $table = 'meta_ads_campaigns';

    protected $guarded = [];

    protected $casts = [
        'start_time' => 'datetime',
        'stop_time' => 'datetime',
        'created_time' => 'datetime',
        'updated_time' => 'datetime',
        'last_synced_at' => 'datetime',
        'daily_budget' => 'decimal:2',
        'lifetime_budget' => 'decimal:2',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'meta_ads_account_id');
    }

    public function adSets(): HasMany
    {
        return $this->hasMany(AdSet::class, 'meta_ads_campaign_id');
    }
}
