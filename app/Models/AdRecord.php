<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdRecord extends Model
{
    protected $table = 'ad_records';

    protected $guarded = [];

    public $incrementing = false;

    public $timestamps = false;

    /**
     * Get the ad account that this record belongs to.
     */
    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class);
    }

    /**
     * Get all ad records for a specific workspace.
     */
    public function scopeOfWorkspace($builder, Workspace $workspace)
    {
        return $builder->whereHas('adAccount.facebook_accounts.workspaces', function ($query) use ($workspace) {
            return $query->where('workspace_id', $workspace->id);
        });
    }

    /**
     * Apply date range filter to the query.
     */
    public function scopeApplyDateFilter($query, $startDate, $endDate, $dateColumn = 'date')
    {
        if ($startDate && $endDate) {
            $query->whereRaw("DATE($dateColumn) >= ? AND DATE($dateColumn) <= ?", [$startDate, $endDate]);
        }

        return $query;
    }
}
