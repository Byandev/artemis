<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptimizationRuleCondition extends Model
{
    protected $table = 'meta_ads_optimization_rule_conditions';

    protected $guarded = [];

    protected $casts = [
        'value' => 'decimal:4',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(OptimizationRule::class, 'meta_ads_optimization_rule_id');
    }
}
