<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelJourney extends Model
{
    protected $guarded = [];

    public function notifications(): ParcelJourney|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ParcelJourneyNotification::class);
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function rider(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }
}
