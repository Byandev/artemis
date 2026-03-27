<?php

namespace App\Queries;

class RtsLocationQuery extends RtsBaseQuery
{
    private array $allowedSortColumns = [];
    private string $mode = 'province';

    private const METRICS_SQL = '
        SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
        SUM(CASE WHEN pancake_orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
        SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
        ROUND(
            (SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
            NULLIF(SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
            2
        ) AS rts_rate_percentage
    ';

    public function byProvince(): static
    {
        $this->mode = 'province';
        $this->allowedSortColumns = ['province_name', 'total_orders', 'delivered_count', 'returned_count', 'rts_rate_percentage'];

        $this->query
            ->leftJoin('shipping_addresses', 'shipping_addresses.order_id', '=', 'pancake_orders.id')
            ->selectRaw('shipping_addresses.province_name AS province_name, ' . self::METRICS_SQL)
            ->groupBy('shipping_addresses.province_name')
            ->havingRaw('SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) > 0');

        return $this;
    }

    public function byCity(): static
    {
        $this->mode = 'city';
        $this->allowedSortColumns = ['city_name', 'province_name', 'total_orders', 'delivered_count', 'returned_count', 'rts_rate_percentage'];

        $this->query
            ->leftJoin('shipping_addresses', 'shipping_addresses.order_id', '=', 'pancake_orders.id')
            ->selectRaw('shipping_addresses.district_name AS city_name, shipping_addresses.province_name AS province_name, ' . self::METRICS_SQL)
            ->groupBy('shipping_addresses.district_name', 'shipping_addresses.province_name')
            ->havingRaw('SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) > 0');

        return $this;
    }

    public function search(string $term): static
    {
        if (! $term) return $this;

        if ($this->mode === 'province') {
            $this->query->where('shipping_addresses.province_name', 'LIKE', "%{$term}%");
        } else {
            $this->query->where(fn ($q) => $q
                ->where('shipping_addresses.district_name', 'LIKE', "%{$term}%")
                ->orWhere('shipping_addresses.province_name', 'LIKE', "%{$term}%")
            );
        }

        return $this;
    }

    public function sort(string $param): static
    {
        $desc   = str_starts_with($param, '-');
        $column = ltrim($param, '-');

        $this->query->orderBy(
            in_array($column, $this->allowedSortColumns) ? $column : 'total_orders',
            $desc ? 'DESC' : 'ASC'
        );

        return $this;
    }

    public function paginate(int $perPage = 10)
    {
        return $this->query->paginate($perPage);
    }
}
