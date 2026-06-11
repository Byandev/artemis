<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptimizationRuleLog extends Model
{
    protected $table = 'meta_ads_optimization_rule_logs';

    protected $guarded = [];

    protected $casts = [
        'conditions_snapshot' => 'array',
        'previous_value' => 'decimal:4',
        'new_value' => 'decimal:4',
        'triggered_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(OptimizationRule::class, 'meta_ads_optimization_rule_id');
    }
}
