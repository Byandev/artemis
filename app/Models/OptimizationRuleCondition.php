<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptimizationRuleCondition extends Model
{
    protected $table = 'optimization_rule_conditions';

    protected $guarded = [];

    public function optimizationRule(): BelongsTo
    {
        return $this->belongsTo(OptimizationRule::class);
    }
}
