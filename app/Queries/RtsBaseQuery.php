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
        $this->applyDateFilter();
        $this->applyEntityFilters();
    }

    private function applyWorkspaceFilter(): void
    {
        $this->query->where('pancake_orders.workspace_id', $this->workspace->id);
    }

    private function applyDateFilter(): void
    {
        $start = $this->request->input('start_date');
        $end   = $this->request->input('end_date');

        if ($start && $end) {
            $this->query->where(function ($q) use ($start, $end) {
                $q->whereRaw('DATE(pancake_orders.delivered_at) >= ? AND DATE(pancake_orders.delivered_at) <= ?', [$start, $end])
                  ->orWhereRaw('DATE(pancake_orders.returning_at) >= ? AND DATE(pancake_orders.returning_at) <= ?', [$start, $end]);
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
