<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Run-scoped lock that ensures a campaign / ad set is changed by at most one
 * rule per run. See the table migration for the guarantee.
 */
class OptimizationTargetClaim extends Model
{
    protected $table = 'meta_ads_optimization_target_claims';

    protected $guarded = [];

    protected $casts = [
        'meta_ads_optimization_rule_id' => 'integer',
        'workspace_id' => 'integer',
        'claimed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(OptimizationRule::class, 'meta_ads_optimization_rule_id');
    }
}
