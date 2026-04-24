<?php

namespace App\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RtsOrderItemQuery extends RtsBaseQuery
{
    private const ALLOWED_SORT_COLUMNS = ['item_name', 'total_orders', 'delivered_count', 'returned_count', 'rts_rate_percentage'];

    private string $sortColumn = 'total_orders';

    private string $sortDirection = 'DESC';

    public function sort(string $param): static
    {
        $desc = str_starts_with($param, '-');
        $column = ltrim($param, '-');

        $this->sortColumn = in_array($column, self::ALLOWED_SORT_COLUMNS) ? $column : 'total_orders';
        $this->sortDirection = $desc ? 'DESC' : 'ASC';

        return $this;
    }

    public function get(int $perPage = 15): LengthAwarePaginator
    {
        $start = $this->request->input('start_date');
        $end = $this->request->input('end_date');
        $pageIds = $this->request->filled('page_ids') ? (array) $this->request->input('page_ids') : null;
        $shopIds = $this->request->filled('shop_ids') ? (array) $this->request->input('shop_ids') : null;

        return DB::table('workspace_daily_metrics_by_item')
            ->where('workspace_id', $this->workspace->id)
            ->when($start && $end, fn ($q) => $q->whereBetween('date', [$start, $end]))
            ->when($pageIds, fn ($q) => $q->whereIn('page_id', $pageIds))
            ->when($shopIds, function ($q) use ($shopIds) {
                $q->whereIn('page_id', function ($sub) use ($shopIds) {
                    $sub->from('pages')->whereIn('shop_id', $shopIds)->select('id');
                });
            })
            ->selectRaw('
                item_name,
                SUM(delivered_count + returning_count + returned_count) AS total_orders,
                SUM(delivered_count) AS delivered_count,
                SUM(returning_count + returned_count) AS returned_count,
                ROUND(
                    (SUM(returning_count + returned_count) * 100.0) /
                    NULLIF(SUM(delivered_count + returning_count + returned_count), 0),
                    2
                ) AS rts_rate_percentage
            ')
            ->groupBy('item_name')
            ->havingRaw('SUM(delivered_count + returning_count + returned_count) > 0')
            ->orderBy($this->sortColumn, $this->sortDirection)
            ->paginate($perPage);
    }
}
