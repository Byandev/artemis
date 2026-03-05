<?php

namespace App\Metrics\Orders;

use App\Metrics\Orders\Concerns\OrdersMetricBase;
use Illuminate\Support\Facades\DB;

final class TotalOrders
{
    use OrdersMetricBase;

    public function compute(int $workspaceId, array $date_range): int
    {
        return (int) DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereDate('pancake_orders.confirmed_at', '<=', $date_range['end_date'])
            ->whereDate('pancake_orders.confirmed_at', '>=', $date_range['start_date'])
            ->count();
    }
}
