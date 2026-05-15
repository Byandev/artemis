<?php

namespace App\Models;

use App\Traits\LogsActivityForWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptimizationRuleCondition extends Model
{
    use LogsActivityForWorkspace;

    protected $table = 'optimization_rule_conditions';

    protected $guarded = [];

    public function optimizationRule(): BelongsTo
    {
        return $this->belongsTo(OptimizationRule::class);
    }
}
