<?php

namespace App\Queries;

use Illuminate\Support\Facades\DB;

class RtsLocationQuery extends RtsBaseQuery
{
    private array $allowedSortColumns = [];

    private string $mode = 'province';

    private string $sortColumn = 'total_orders';

    private string $sortDirection = 'DESC';

    private string $searchTerm = '';

    public function byProvince(): static
    {
        $this->mode = 'province';
        $this->allowedSortColumns = ['province_name', 'total_orders', 'delivered_count', 'returned_count', 'rts_rate_percentage'];

        return $this;
    }

    public function byCity(): static
    {
        $this->mode = 'city';
        $this->allowedSortColumns = ['city_name', 'province_name', 'total_orders', 'delivered_count', 'returned_count', 'rts_rate_percentage'];

        return $this;
    }

    public function search(string $term): static
    {
        $this->searchTerm = $term;

        return $this;
    }

    public function sort(string $param): static
    {
        $desc = str_starts_with($param, '-');
        $column = ltrim($param, '-');

        $this->sortColumn = in_array($column, $this->allowedSortColumns) ? $column : 'total_orders';
        $this->sortDirection = $desc ? 'DESC' : 'ASC';

        return $this;
    }

    public function paginate(int $perPage = 10)
    {
        $start = $this->request->input('start_date');
        $end = $this->request->input('end_date');
        $pageIds = $this->request->filled('page_ids') ? (array) $this->request->input('page_ids') : null;
        $shopIds = $this->request->filled('shop_ids') ? (array) $this->request->input('shop_ids') : null;

        $query = DB::table('workspace_daily_metrics_by_location')
            ->where('workspace_id', $this->workspace->id)
            ->when($start && $end, fn ($q) => $q->whereBetween('date', [$start, $end]))
            ->when($pageIds, fn ($q) => $q->whereIn('page_id', $pageIds))
            ->when($shopIds, function ($q) use ($shopIds) {
                $q->whereIn('page_id', function ($sub) use ($shopIds) {
                    $sub->from('pages')->whereIn('shop_id', $shopIds)->select('id');
                });
            });

        if ($this->mode === 'province') {
            $query->selectRaw('
                province_name,
                SUM(delivered_count + returning_count + returned_count) AS total_orders,
                SUM(delivered_count) AS delivered_count,
                SUM(returning_count + returned_count) AS returned_count,
                ROUND(
                    (SUM(returning_count + returned_count) * 100.0) /
                    NULLIF(SUM(delivered_count + returning_count + returned_count), 0),
                    2
                ) AS rts_rate_percentage
            ')
                ->groupBy('province_name');
        } else {
            $query->selectRaw('
                district_name AS city_name,
                province_name,
                SUM(delivered_count + returning_count + returned_count) AS total_orders,
                SUM(delivered_count) AS delivered_count,
                SUM(returning_count + returned_count) AS returned_count,
                ROUND(
                    (SUM(returning_count + returned_count) * 100.0) /
                    NULLIF(SUM(delivered_count + returning_count + returned_count), 0),
                    2
                ) AS rts_rate_percentage
            ')
                ->groupBy('district_name', 'province_name');
        }

        if ($this->searchTerm !== '') {
            $query->whereRaw('MATCH(province_name, district_name) AGAINST (? IN BOOLEAN MODE)', [$this->searchTerm.'*']);
        }

        return $query
            ->havingRaw('SUM(delivered_count + returning_count + returned_count) > 0')
            ->orderBy($this->sortColumn, $this->sortDirection)
            ->paginate($perPage);
    }
}
