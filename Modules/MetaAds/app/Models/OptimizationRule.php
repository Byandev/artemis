<?php

namespace Modules\MetaAds\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptimizationRule extends Model
{
    protected $table = 'meta_ads_optimization_rules';

    protected $guarded = [];

    protected $casts = [
        // Cast to string so large Meta account ids survive JSON without losing
        // precision on the frontend.
        'meta_ads_account_id' => 'string',
        'adjustment_value' => 'decimal:4',
        'max_adjustment_amount' => 'decimal:4',
        'budget_min' => 'decimal:4',
        'budget_max' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'meta_ads_account_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(OptimizationRuleCondition::class, 'meta_ads_optimization_rule_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OptimizationRuleLog::class, 'meta_ads_optimization_rule_id');
    }
}
