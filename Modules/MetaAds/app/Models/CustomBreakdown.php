<?php

namespace Modules\MetaAds\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable "custom breakdown" — a named set of rule-defined groups used to
 * bucket ads into comparison buckets (e.g. "Promo Offer" = name contains
 * "PROMO"). Reports reference one to group their data into these buckets.
 */
class CustomBreakdown extends Model
{
    protected $table = 'meta_ads_custom_breakdowns';

    protected $guarded = [];

    protected $casts = [
        'groups' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
