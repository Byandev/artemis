<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;

class MonitorEvaluation extends Model
{
    protected $table = 'meta_ads_monitor_evaluations';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'metrics' => 'array',
        'reason' => 'array',
        'evaluated_at' => 'datetime',
    ];
}
