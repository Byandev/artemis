<?php

namespace App\Models;

use App\Jobs\SendParcelUpdateNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ParcelJourneyNotification extends Model
{
    protected $guarded = [];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope a query to filter by page name.
     */
    public function scopeFilterByPageName(Builder $query, string $pageName): Builder
    {
        return $query->whereHas('order.page', function ($q) use ($pageName) {
            $q->where('name', 'like', '%'.$pageName.'%');
        });
    }
}
