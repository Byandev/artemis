<?php

namespace App\Models;

use App\Jobs\SendParcelUpdateNotification;
use Illuminate\Database\Eloquent\Model;

class ParcelJourneyNotification extends Model
{
    protected $guarded = [];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function (ParcelJourneyNotification $parcelJourneyNotification) {
            dispatch(new SendParcelUpdateNotification($parcelJourneyNotification))->onQueue('parcel-notifications');
        });
    }
}
