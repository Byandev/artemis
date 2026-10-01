<?php

namespace Modules\MetaAds\Models;

use App\Models\User as AppUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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
     * Re-derive `start_time` for ads (all of them, or those $scope narrows the
     * update query to). Meta has no ad start time: an ad can't deliver before it
     * exists, nor before its ad set is scheduled to start, so it's the later of
     * the ad's created_time and its ad set's start_time — whichever is known
     * when the other is missing. Both come from Meta in the account's timezone,
     * so the comparison is like-for-like.
     *
     * @param  (callable(Builder): mixed)|null  $scope
     */
    public static function refreshStartTimes(?callable $scope = null): int
    {
        $query = DB::table('meta_ads_ads')
            ->leftJoin('meta_ads_sets', 'meta_ads_sets.id', '=', 'meta_ads_ads.meta_ads_set_id');

        if ($scope) {
            $scope($query);
        }

        return $query->update([
            'meta_ads_ads.start_time' => DB::raw(
                'CASE'
                .' WHEN meta_ads_sets.start_time IS NULL THEN meta_ads_ads.created_time'
                .' WHEN meta_ads_ads.created_time IS NULL THEN meta_ads_sets.start_time'
                .' ELSE GREATEST(meta_ads_ads.created_time, meta_ads_sets.start_time)'
                .' END'
            ),
        ]);
    }
}
