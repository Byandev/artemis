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
}
