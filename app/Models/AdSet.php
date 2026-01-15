<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdSet extends Model
{
    protected $guarded = [];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function adAccount()
    {
        return $this->belongsTo(AdAccount::class);
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
        return $query->having('impressions', '>', $value)->groupBy('ad_sets.id');
    }

    /**
     * Scope for clicks greater than filter
     */
    public function scopeClicksGreaterThan(Builder $query, $value): Builder
    {
        return $query->having('clicks', '>', $value)->groupBy('ad_sets.id');
    }

    /**
     * Scope for spend greater than filter
     */
    public function scopeSpendGreaterThan(Builder $query, $value): Builder
    {
        return $query->having('spend', '>', $value)->groupBy('ad_sets.id');
    }

    /**
     * Scope for daily budget greater than filter
     */
    public function scopeDailyBudgetGreaterThan(Builder $query, $value): Builder
    {
        return $query->where('daily_budget', '>', $value);
    }
}
