<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $table = 'campaigns';

    protected $guarded = [];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }

    public function adSets(): HasMany
    {
        return $this->hasMany(AdSet::class, 'campaign_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class, 'campaign_id');
    }

    /**
     * Scope for search filter
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', '%'.$search.'%');
    }

    /**
     * Scope for start date filter
     */
    public function scopeStartDate(Builder $query, $date): Builder
    {
        return $query->whereDate('created_at', '>=', $date);
    }

    /**
     * Scope for end date filter
     */
    public function scopeEndDate(Builder $query, $date): Builder
    {
        return $query->whereDate('created_at', '<=', $date);
    }
}
