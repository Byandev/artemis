<?php

namespace Modules\MetaAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptimizationProposal extends Model
{
    protected $table = 'meta_ads_optimization_proposals';

    protected $guarded = [];

    protected $casts = [
        // Cast to string so large Meta ids survive JSON without losing precision.
        'meta_ads_account_id' => 'string',
        'target_id' => 'string',
        'current_value' => 'decimal:4',
        'new_value' => 'decimal:4',
        'conditions_snapshot' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(OptimizationRule::class, 'meta_ads_optimization_rule_id');
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'meta_ads_account_id');
    }
}
