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
}
