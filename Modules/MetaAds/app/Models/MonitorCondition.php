<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorCondition extends Model
{
    protected $table = 'meta_ads_monitor_conditions';

    protected $guarded = [];

    protected $casts = [
        'value' => 'decimal:4',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(MonitorStatusRule::class, 'meta_ads_monitor_status_rule_id');
    }
}
