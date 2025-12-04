<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdRecord extends Model
{
    protected $table = 'ad_records';

    protected $guarded = [];

    public $incrementing = false;

    public $timestamps = false;
}
