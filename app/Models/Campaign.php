<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campaign extends Model
{
    protected $table = 'campaigns';

    protected $guarded = [];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }
}
