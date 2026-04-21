<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'called_at' => 'datetime',
    ];
}