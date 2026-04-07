<?php

namespace App\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RtsRiderQuery extends RtsBaseQuery
{
    private const ALLOWED_SORT_COLUMNS = ['rider_name', 'total_orders', 'delivered_count', 'returned_count', 'rts_rate_percentage'];

    private string $sortColumn    = 'total_orders';
    private string $sortDirection = 'DESC';

    public function sort(string $param): static
    {
        $desc   = str_starts_with($param, '-');
        $column = ltrim($param, '-');

        $this->sortColumn    = in_array($column, self::ALLOWED_SORT_COLUMNS) ? $column : 'total_orders';
        $this->sortDirection = $desc ? 'DESC' : 'ASC';

        return $this;
    }

    public function get(int $perPage = 15): LengthAwarePaginator
    {
        // The inner subquery already constrains to 'On Delivery' IDs,
        // so the outer status check is redundant.
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
            ->selectRaw('
                lr.rider_name,' . self::METRICS_SQL)
            ->joinSub($latestRider, 'lr', 'lr.order_id', '=', 'pancake_orders.id')
            ->groupBy('lr.rider_name')
            ->havingRaw(self::HAVING_SQL)
            ->orderBy($this->sortColumn, $this->sortDirection)
            ->paginate($perPage);
    }
}
