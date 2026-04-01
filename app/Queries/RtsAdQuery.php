<?php

namespace App\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RtsAdQuery extends RtsBaseQuery
{
    private const ALLOWED_SORT_COLUMNS = ['ad_name', 'total_orders', 'delivered_count', 'returned_count', 'rts_rate_percentage'];

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
        return $this->query
            ->selectRaw('pancake_orders.ad_id, ads.name AS ad_name,' . self::METRICS_SQL)
            ->leftJoin('ads', 'ads.id', '=', 'pancake_orders.ad_id')
            ->groupBy('pancake_orders.ad_id', 'ads.name')
            ->havingRaw(self::HAVING_SQL)
            ->orderBy($this->sortColumn, $this->sortDirection)
            ->paginate($perPage);
    }
}
