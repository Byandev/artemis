<?php

namespace App\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RtsRiderQuery extends RtsBaseQuery
{
    private const ALLOWED_SORT_COLUMNS = ['rider_name', 'total_orders', 'delivered_count', 'returned_count', 'rts_rate_percentage'];

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
        if ($this->hasEntityFilter()) {
            return $this->getFromLive($perPage);
        }

        return $this->getFromRollup($perPage);
    }

    private function hasEntityFilter(): bool
    {
        return $this->request->filled('page_ids') || $this->request->filled('shop_ids');
    }

    private function getFromRollup(int $perPage): LengthAwarePaginator
    {
        $start = $this->request->input('start_date');
        $end = $this->request->input('end_date');

        return DB::table('workspace_daily_metrics_by_rider')
            ->where('workspace_id', $this->workspace->id)
            ->when($start && $end, fn ($q) => $q->whereBetween('date', [$start, $end]))
            ->selectRaw('
                rider_name,
                SUM(delivered_count + returning_count + returned_count) AS total_orders,
                SUM(delivered_count) AS delivered_count,
                SUM(returning_count + returned_count) AS returned_count,
                ROUND(
                    (SUM(returning_count + returned_count) * 100.0) /
                    NULLIF(SUM(delivered_count + returning_count + returned_count), 0),
                    2
                ) AS rts_rate_percentage
            ')
            ->groupBy('rider_name')
            ->havingRaw('SUM(delivered_count + returning_count + returned_count) > 0')
            ->orderBy($this->sortColumn, $this->sortDirection)
            ->paginate($perPage);
    }

    private function getFromLive(int $perPage): LengthAwarePaginator
    {
        $latestRider = DB::table('parcel_journeys as pj')
            ->select('pj.order_id', 'pj.rider_name')
            ->whereNotNull('pj.rider_name')
            ->whereIn('pj.id', function ($q) {
                $q->from('parcel_journeys')
                    ->selectRaw('MAX(id)')
                    ->where('status', 'On Delivery')
                    ->groupBy('order_id');
            });

        return $this->query
            ->selectRaw('lr.rider_name,'.self::METRICS_SQL)
            ->joinSub($latestRider, 'lr', 'lr.order_id', '=', 'pancake_orders.id')
            ->groupBy('lr.rider_name')
            ->havingRaw(self::HAVING_SQL)
            ->orderBy($this->sortColumn, $this->sortDirection)
            ->paginate($perPage);
    }
}
