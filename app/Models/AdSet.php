<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class, 'ad_set_id');
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
