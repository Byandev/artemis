<?php

namespace App\Queries;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Pancake\Models\Order;

abstract class RtsBaseQuery
{
    protected const METRICS_SQL = '
        SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
        SUM(CASE WHEN pancake_orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
        SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
        ROUND(
            (SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
            NULLIF(SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
            2
        ) AS rts_rate_percentage
    ';

    protected const HAVING_SQL = 'SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) > 0';

    protected Builder $query;

    public function __construct(
        protected readonly Workspace $workspace,
        protected readonly Request $request,
    ) {
        $this->query = Order::query();
        $this->applyWorkspaceFilter();
        $this->applyStatusFilter();
        $this->applyDateFilter();
        $this->applyEntityFilters();
    }

    private function applyWorkspaceFilter(): void
    {
        $this->query->where('pancake_orders.workspace_id', $this->workspace->id);
    }

    /**
     * Pre-filter to delivered/returned statuses only.
     * Reduces rows before GROUP BY since METRICS_SQL only counts these statuses anyway.
     */
    private function applyStatusFilter(): void
    {
        $this->query->whereIn('pancake_orders.status', [3, 4, 5]);
    }

    /**
     * Use direct range comparisons instead of DATE() to keep the filter sargable
     * and allow MySQL to use indexes on delivered_at / returning_at.
     */
    private function applyDateFilter(): void
    {
        $start = $this->request->input('start_date');
        $end = $this->request->input('end_date');

        if ($start && $end) {
            $this->query->where(function ($q) use ($start, $end) {
                $q->whereBetween('pancake_orders.delivered_at', [$start, $end.' 23:59:59'])
                    ->orWhereBetween('pancake_orders.returning_at', [$start, $end.' 23:59:59']);
            });
        }
    }

    private function applyEntityFilters(): void
    {
        if ($this->request->filled('page_ids')) {
            $this->query->whereIn('pancake_orders.page_id', (array) $this->request->input('page_ids'));
        }

        if ($this->request->filled('shop_ids')) {
            $this->query->whereIn('pancake_orders.shop_id', (array) $this->request->input('shop_ids'));
        }
    }
}
