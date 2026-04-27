<?php

namespace Modules\Pancake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParcelJourney extends Model
{
    protected $guarded = [];

    public function notifications(): HasMany|ParcelJourney
    {
        return $this->hasMany(ParcelJourneyNotification::class);
    }
}
