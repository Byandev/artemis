<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class Aov
{
    public function compute(int $workspaceId, array $date_range): float
    {
        $row = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->whereNotNull('pancake_orders.confirmed_at')
            ->whereDate('pancake_orders.confirmed_at', '<=', $date_range['end_date'])
            ->whereDate('pancake_orders.confirmed_at', '>=', $date_range['start_date'])
            ->selectRaw('COALESCE(SUM(pancake_orders.final_amount) / NULLIF(COUNT(*),0), 0) as aov')
            ->first();

        return (float) ($row->aov ?? 0);
    }
}
