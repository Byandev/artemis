<?php

namespace Modules\MetaAds\Models;

use App\Models\User as AppUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Ad extends Model
{
    protected $table = 'meta_ads_ads';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'created_time' => 'datetime',
        'updated_time' => 'datetime',
        'start_time' => 'datetime',
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

    /**
     * An ad's start time from the values Meta returns for it: the later of its
     * created_time and its ad set's start_time. Meta has no ad-level start
     * time, but an ad can't deliver before it exists, nor before its ad set
     * starts. Both are in the account's timezone.
     */
    public static function deriveStartTime(?string $createdTime, ?string $adSetStartTime): ?string
    {
        if ($createdTime === null || $adSetStartTime === null) {
            return $createdTime ?? $adSetStartTime;
        }

        return Carbon::parse($adSetStartTime)->greaterThan(Carbon::parse($createdTime))
            ? $adSetStartTime
            : $createdTime;
    }
}
