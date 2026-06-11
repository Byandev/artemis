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

    /**
     * meta_ads_budget_snapshots has a composite natural key
     * (entity_type, entity_id, date) and no surrogate `id`, so $primaryKey is
     * null. Eloquent's default save query keys on getKeyName() — null here —
     * producing an UPDATE with NO WHERE clause that rewrites EVERY row. Scope
     * save/update (and delete) queries to the composite key instead.
     */
    protected function setKeysForSaveQuery($query)
    {
        return $this->scopeToCompositeKey($query);
    }

    protected function setKeysForSelectQuery($query)
    {
        return $this->scopeToCompositeKey($query);
    }

    private function scopeToCompositeKey($query)
    {
        return $query
            ->where('entity_type', $this->getRawOriginal('entity_type', $this->entity_type))
            ->where('entity_id', $this->getRawOriginal('entity_id', $this->entity_id))
            ->where('date', $this->getRawOriginal('date', $this->getAttributeFromArray('date')));
    }
}
