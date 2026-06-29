<?php

namespace App\Queries;

use Illuminate\Support\Collection;

class RtsOrderSourceQuery extends RtsBaseQuery
{
    // The order sources we surface on the analytics page.
    private const SOURCES = ['Facebook', 'Webcake'];

    public function get(): Collection
    {
        return $this->query
            ->whereIn('pancake_orders.order_source_name', self::SOURCES)
            ->selectRaw('pancake_orders.order_source_name AS order_source_name, '.self::METRICS_SQL)
            ->groupBy('pancake_orders.order_source_name')
            ->havingRaw(self::HAVING_SQL)
            ->orderByRaw("FIELD(pancake_orders.order_source_name, 'Facebook', 'Webcake')")
            ->get();
    }
}
