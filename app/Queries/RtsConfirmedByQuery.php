<?php

namespace App\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RtsConfirmedByQuery extends RtsBaseQuery
{
    private const ALLOWED_SORT_COLUMNS = ['confirmed_by_name', 'total_orders', 'delivered_count', 'returned_count', 'rts_rate_percentage'];

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

        return DB::table('pancake_user_pos_daily_reports as r')
            ->join('pancake_users as pu', 'pu.id', '=', 'r.pancake_user_id')
            ->where('r.workspace_id', $this->workspace->id)
            ->when($start && $end, fn ($q) => $q->whereBetween('r.date', [$start, $end]))
            ->selectRaw('
                pu.name AS confirmed_by_name,
                SUM(r.delivered_count + r.returning_count + r.returned_count) AS total_orders,
                SUM(r.delivered_count) AS delivered_count,
                SUM(r.returning_count + r.returned_count) AS returned_count,
                ROUND(
                    (SUM(r.returning_count + r.returned_count) * 100.0) /
                    NULLIF(SUM(r.delivered_count + r.returning_count + r.returned_count), 0),
                    2
                ) AS rts_rate_percentage
            ')
            ->groupBy('r.pancake_user_id', 'pu.name')
            ->havingRaw('SUM(r.delivered_count + r.returning_count + r.returned_count) > 0')
            ->orderBy($this->sortColumn, $this->sortDirection)
            ->paginate($perPage);
    }

    private function getFromLive(int $perPage): LengthAwarePaginator
    {
        return $this->query
            ->selectRaw('pancake_users.name AS confirmed_by_name,'.self::METRICS_SQL)
            ->join('pancake_users', 'pancake_users.id', '=', 'pancake_orders.confirmed_by')
            ->groupBy('pancake_orders.confirmed_by', 'pancake_users.name')
            ->havingRaw(self::HAVING_SQL)
            ->orderBy($this->sortColumn, $this->sortDirection)
            ->paginate($perPage);
    }
}
