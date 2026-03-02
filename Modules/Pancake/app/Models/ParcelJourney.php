<?php

namespace Modules\Pancake\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelJourney extends Model
{
    protected $guarded = [];

    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany|ParcelJourney
    {
        return $this->hasMany(ParcelJourneyNotification::class);
    }
}
