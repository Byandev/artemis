<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campaign extends Model
{
    protected $table = 'campaigns';

    protected $guarded = [];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }

    /**
     * Scope for search filter
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', '%'.$search.'%');
    }

    /**
     * Scope for impressions greater than filter
     */
    public function scopeImpressionsGreaterThan(Builder $query, $value): Builder
    {
        return $query->having('impressions', '>', $value)->groupBy('campaigns.id');
    }

    /**
     * Scope for clicks greater than filter
     */
    public function scopeClicksGreaterThan(Builder $query, $value): Builder
    {
        return $query->having('clicks', '>', $value)->groupBy('campaigns.id');
    }

    /**
     * Scope for spend greater than filter
     */
    public function scopeSpendGreaterThan(Builder $query, $value): Builder
    {
        return $query->having('spend', '>', $value)->groupBy('campaigns.id');
    }

    /**
     * Scope for daily budget greater than filter
     */
    public function scopeDailyBudgetGreaterThan(Builder $query, $value): Builder
    {
        return $query->where('daily_budget', '>', $value);
    }
}
