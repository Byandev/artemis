<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $guarded = [];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function adSet()
    {
        return $this->belongsTo(AdSet::class);
    }

    public function adAccount()
    {
        return $this->belongsTo(AdAccount::class);
    }
}
