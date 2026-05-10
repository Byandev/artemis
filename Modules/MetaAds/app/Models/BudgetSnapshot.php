<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetSnapshot extends Model
{
    public const ENTITY_AD_SET = 'ad_set';

    public const ENTITY_CAMPAIGN = 'campaign';

    public const ENTITY_PAGE = 'page';

    protected $table = 'meta_ads_budget_snapshots';

    protected $primaryKey = null;

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'daily_budget' => 'decimal:2',
        'lifetime_budget' => 'decimal:2',
    ];
}
