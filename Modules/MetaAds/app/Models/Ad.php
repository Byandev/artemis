<?php

namespace Modules\MetaAds\Models;

use App\Models\User as AppUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ad extends Model
{
    protected $table = 'meta_ads_ads';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'created_time' => 'datetime',
        'updated_time' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'meta_ads_account_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'meta_ads_campaign_id');
    }

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(AdSet::class, 'meta_ads_set_id');
    }

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class, 'meta_ads_creative_id');
    }

    /**
     * The app user tagged as this ad's internal creator. Nullable and assigned
     * manually — Meta doesn't provide it.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'creator_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(Insight::class, 'meta_ads_ad_id');
    }
}
