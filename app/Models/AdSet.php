<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdSet extends Model
{
    protected $guarded = [];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function adAccount()
    {
        return $this->belongsTo(AdAccount::class);
    }
}
