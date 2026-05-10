<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdSet extends Model
{
    protected $table = 'meta_ads_sets';

    protected $guarded = [];

    protected $casts = [
        'targeting' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
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

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'meta_ads_campaign_id');
    }
}
