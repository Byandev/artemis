<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelJourneyNotificationLog extends Model
{
    protected $guarded = [];

    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
