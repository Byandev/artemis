<?php

namespace App\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RtsOrderItemQuery extends RtsBaseQuery
{
    private const ALLOWED_SORT_COLUMNS = ['item_name', 'total_orders', 'delivered_count', 'returned_count', 'rts_rate_percentage'];

    private string $sortColumn = 'total_orders';
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
        return $this->query
            ->selectRaw('
                pancake_order_items.name AS item_name,
                SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
                SUM(CASE WHEN pancake_orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
                ROUND(
                    (SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
                    NULLIF(SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
                    2
                ) AS rts_rate_percentage
            ')
            ->join('pancake_order_items', 'pancake_order_items.order_id', '=', 'pancake_orders.id')
            ->groupBy('pancake_order_items.name')
            ->havingRaw(self::HAVING_SQL)
            ->orderBy($this->sortColumn, $this->sortDirection)
            ->paginate($perPage);
    }
}
