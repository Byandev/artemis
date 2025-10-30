<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelJourneyNotification extends Model
{
    protected $guarded = [];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
