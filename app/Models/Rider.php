<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rider extends Model
{
    protected $guarded = [];

    public function parcelJourneys(): HasMany
    {
        return $this->hasMany(ParcelJourney::class);
    }
}
