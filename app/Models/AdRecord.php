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

    /**
     * Apply entity filters (teams, products, pages, shops) to the query.
     * Filters ad records through their relationship to orders.
     */
    public function scopeApplyEntityFilters($query, $filters)
    {
        // Normalize filters to arrays
        $pageIds = $filters['page_ids'] ?? null;
        $shopIds = $filters['shop_ids'] ?? null;
        $productIds = $filters['product_ids'] ?? null;
        $teamIds = $filters['team_ids'] ?? null;

        // Only apply filters if at least one is present
        if (! $pageIds && ! $shopIds && ! $productIds && ! $teamIds) {
            return $query;
        }

        // Filter ad records through orders that match the entity filters
        $query->whereHas('orders', function ($q) use ($pageIds, $shopIds, $productIds, $teamIds) {
            // Filter by page IDs
            if ($pageIds) {
                $q->whereIn('orders.page_id', is_array($pageIds) ? $pageIds : explode(',', $pageIds));
            }

            // Filter by shops IDs
            if ($shopIds) {
                $q->whereIn('orders.shop_id', is_array($shopIds) ? $shopIds : explode(',', $shopIds));
            }

            // Filter by product IDs (via pages)
            if ($productIds) {
                $productIdsArray = is_array($productIds) ? $productIds : explode(',', $productIds);
                $q->whereHas('page', function ($pageQuery) use ($productIdsArray) {
                    $pageQuery->whereIn('product_id', $productIdsArray);
                });
            }

            // Filter by team IDs (via page owner's teams)
            if ($teamIds) {
                $teamIdsArray = is_array($teamIds) ? $teamIds : explode(',', $teamIds);
                $q->whereHas('page.owner.teams', function ($teamQuery) use ($teamIdsArray) {
                    $teamQuery->whereIn('teams.id', $teamIdsArray);
                });
            }
        });

        return $query;
    }

    /**
     * Get all orders associated with this ad.
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'ad_id', 'ad_id');
    }
}
