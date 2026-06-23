<?php

namespace Modules\MetaAds\Models;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved Ads Manager "report" — a named, reusable configuration (accounts,
 * date range, breakdown, metrics, sort, filters) that the report builder
 * restores and renders against the existing /ads-manager/data engine.
 */
class Report extends Model
{
    protected $table = 'meta_ads_reports';

    protected $guarded = [];

    protected $casts = [
        'config' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
