<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A dated set of per-team sales targets. The date and label live here; the
 * per-team amounts hang off it as {@see SalesTargetTeam} rows.
 */
class SalesTarget extends Model
{
    protected $fillable = [
        'workspace_id',
        'date',
        'name',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function teamTargets(): HasMany
    {
        return $this->hasMany(SalesTargetTeam::class);
    }

    public function scopeOfWorkspace($query, Workspace $workspace)
    {
        return $query->where('workspace_id', $workspace->id);
    }
}
