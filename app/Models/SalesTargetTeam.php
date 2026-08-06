<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One team's amount within a dated {@see SalesTarget}. The date is the parent's
 * — deliberately not duplicated here.
 */
class SalesTargetTeam extends Model
{
    protected $fillable = [
        'sales_target_id',
        'team_id',
        'sales_target',
    ];

    protected $casts = [
        'sales_target' => 'decimal:2',
    ];

    public function salesTarget(): BelongsTo
    {
        return $this->belongsTo(SalesTarget::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
