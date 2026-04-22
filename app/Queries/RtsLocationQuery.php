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
        if ($this->hasEntityFilter()) {
            return $this->paginateFromLive($perPage);
        }

        return $this->paginateFromRollup($perPage);
    }

    private function hasEntityFilter(): bool
    {
        return $this->request->filled('page_ids') || $this->request->filled('shop_ids');
    }

    private function paginateFromRollup(int $perPage)
    {
        $start = $this->request->input('start_date');
        $end = $this->request->input('end_date');

        $query = DB::table('workspace_daily_metrics_by_location')
            ->where('workspace_id', $this->workspace->id)
            ->when($start && $end, fn ($q) => $q->whereBetween('date', [$start, $end]));

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

    private function paginateFromLive(int $perPage)
    {
        $this->query->leftJoin('shipping_addresses', 'shipping_addresses.order_id', '=', 'pancake_orders.id');

        if ($this->mode === 'province') {
            $this->query
                ->selectRaw('shipping_addresses.province_name AS province_name, '.self::METRICS_SQL)
                ->groupBy('shipping_addresses.province_name')
                ->havingRaw(self::HAVING_SQL);
        } else {
            $this->query
                ->selectRaw('shipping_addresses.district_name AS city_name, shipping_addresses.province_name AS province_name, '.self::METRICS_SQL)
                ->groupBy('shipping_addresses.district_name', 'shipping_addresses.province_name')
                ->havingRaw(self::HAVING_SQL);
        }

        if ($this->searchTerm !== '') {
            $term = $this->searchTerm;
            if ($this->mode === 'province') {
                $this->query->where('shipping_addresses.province_name', 'LIKE', "%{$term}%");
            } else {
                $this->query->where(fn ($q) => $q
                    ->where('shipping_addresses.district_name', 'LIKE', "%{$term}%")
                    ->orWhere('shipping_addresses.province_name', 'LIKE', "%{$term}%")
                );
            }
        }

        return $this->query
            ->orderBy($this->sortColumn, $this->sortDirection)
            ->paginate($perPage);
    }
}
