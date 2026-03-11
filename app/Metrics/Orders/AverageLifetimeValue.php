<?php

namespace App\Metrics\Orders;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AverageLifetimeValue
{
    /**
     * Average lifetime value up to selected end date
     *
     * Formula:
     * total confirmed sales up to end date / unique confirmed customers up to end date
     *
     * @param  int  $workspaceId
     * @param  array  $dateRange
     * @param  array  $filter
     *
     * @return float
     */
    public function compute(int $workspaceId, array $dateRange, array $filter): float
    {
        $endExclusive = Carbon::parse($dateRange['end_date'])
            ->addDay()
            ->startOfDay()
            ->toDateTimeString();

        $salesQuery = DB::query()
            ->fromSub(
                $this->baseOrdersQuery(
                    $workspaceId,
                    $filter,
                    $endExclusive,
                    'idx_orders_workspace_confirmed_customer_amount'
                )->selectRaw('SUM(pancake_orders.final_amount) as total_sales'),
                's'
            );

        $customersQuery = DB::query()->fromSub(
            $this->baseOrdersQuery(
                $workspaceId,
                $filter,
                $endExclusive,
                'idx_orders_workspace_customer_confirmed'
            )
                ->select('pancake_orders.customer_id')
                ->groupBy('pancake_orders.customer_id'),
            'x'
        )->selectRaw('COUNT(*) as total_customers');

        $row = $salesQuery
            ->crossJoinSub($customersQuery, 'c')
            ->selectRaw('
                COALESCE(
                    s.total_sales * 1.0 / NULLIF(c.total_customers, 0),
                    0
                ) as avg_lifetime_value
            ')
            ->first();

        return (float) ($row->avg_lifetime_value ?? 0);
    }

    private function baseOrdersQuery(
        int $workspaceId,
        array $filter,
        string $endExclusive,
        ?string $forceIndex = null
    ): Builder {
        $table = 'pancake_orders';

        if ($forceIndex) {
            $table .= " FORCE INDEX ({$forceIndex})";
        }

        return DB::table(DB::raw($table))
            ->when($this->needsPagesJoin($filter), function (Builder $query) {
                $query->join('pages', 'pages.id', '=', 'pancake_orders.page_id');
            })
            ->where('pancake_orders.workspace_id', $workspaceId)
            ->where('pancake_orders.confirmed_at', '<', $endExclusive)
            ->whereNotNull('pancake_orders.customer_id')
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when(!empty($filter['page_ids']), function (Builder $query) use ($filter) {
                $query->whereIn('pages.id', explode(',', $filter['page_ids']));
            })
            ->when(!empty($filter['shop_ids']), function (Builder $query) use ($filter) {
                $query->whereIn('pages.shop_id', explode(',', $filter['shop_ids']));
            });
    }

    private function needsPagesJoin(array $filter): bool
    {
        return !empty($filter['page_ids']) || !empty($filter['shop_ids']);
    }
}
