<?php

namespace Modules\MetaAds\Models;

use App\Models\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptimizationRule extends Model
{
    protected $table = 'meta_ads_optimization_rules';

    protected $guarded = [];

    protected $casts = [
        'adjustment_value' => 'decimal:4',
        'max_adjustment_amount' => 'decimal:4',
        'min_adjustment_amount' => 'decimal:4',
        'budget_min' => 'decimal:4',
        'budget_max' => 'decimal:4',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'run_at_hour' => 'integer',
        'last_evaluated_at' => 'datetime',
    ];

    /**
     * Should this rule be evaluated at the given time? Schedules are aligned to
     * the clock (the evaluator command runs hourly), and a rule never runs more
     * than once within the same hour.
     */
    public function isDue(CarbonInterface $now): bool
    {
        if ($this->last_evaluated_at && $this->last_evaluated_at->isSameHour($now)) {
            return false;
        }

        return match ($this->frequency) {
            'hourly' => true,
            'every_3_hours' => $now->hour % 3 === 0,
            'every_6_hours' => $now->hour % 6 === 0,
            'every_12_hours' => $now->hour % 12 === 0,
            'daily' => $now->hour === (int) ($this->run_at_hour ?? 0),
            default => false,
        };
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function adAccounts(): BelongsToMany
    {
        return $this->belongsToMany(
            AdAccount::class,
            'meta_ads_optimization_rule_ad_account',
            'meta_ads_optimization_rule_id',
            'meta_ads_account_id',
        );
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
