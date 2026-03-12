<?php

namespace Modules\Pancake\Models;

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

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function (
            ParcelJourneyNotification $parcelJourneyNotification) {
            if (config('settings.parcel_journey_notification_enabled')) {
                dispatch(new SendParcelUpdateNotification($parcelJourneyNotification))->onQueue('parcel-notifications');
            }
        });
    }
}
