<?php

namespace Modules\MetaAds\Models;

use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdSet extends Model
{
    protected $table = 'meta_ads_sets';

    public $incrementing = false;

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

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class, 'meta_ads_set_id');
    }

    /**
     * Daily budget history, newest date first — one row per date, written
     * forward by `metaads:capture-budgets` and backwards (for dates before this
     * ad set was first captured) by `metaads:backfill-adset-budgets`.
     *
     * meta_ads_budget_snapshots is shared with campaigns and pages, so the
     * relation pins entity_type itself rather than going through a morph map.
     */
    public function budgetSnapshots(): HasMany
    {
        return $this->hasMany(BudgetSnapshot::class, 'entity_id')
            ->where('entity_type', BudgetSnapshot::ENTITY_AD_SET)
            ->orderByDesc('date');
    }

    public function page()
    {
        return $this->belongsTo(Page::class, 'meta_page_id');
    }
}
