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
        $latestRider = DB::table('parcel_journeys as pj')
            ->select('pj.order_id', 'pj.rider_name')
            ->whereNotNull('pj.rider_name')
            ->whereIn('pj.id', function ($q) {
                $q->from('parcel_journeys as pj2')
                    ->join('pancake_orders as po', 'po.id', '=', 'pj2.order_id')
                    ->selectRaw('MAX(pj2.id)')
                    ->where('pj2.status', 'On Delivery')
                    // Exclude the return leg: once an order is marked returning, any
                    // later "On Delivery" entry is the rider carrying the parcel back
                    // to the warehouse, not a delivery attempt. Attribute the order to
                    // the last rider who tried to deliver it (on/before returning_at).
                    // Delivered orders have a null returning_at, so all entries count.
                    ->where(function ($w) {
                        $w->whereNull('po.returning_at')
                            ->orWhereColumn('pj2.created_at', '<=', 'po.returning_at');
                    })
                    ->groupBy('pj2.order_id');
            });

        return $this->query
            ->selectRaw('
                lr.rider_name,'.self::METRICS_SQL)
            ->joinSub($latestRider, 'lr', 'lr.order_id', '=', 'pancake_orders.id')
            ->groupBy('lr.rider_name')
            ->havingRaw(self::HAVING_SQL)
            ->orderBy($this->sortColumn, $this->sortDirection)
            ->paginate($perPage);
    }
}
