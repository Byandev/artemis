<?php

namespace App\Metrics\Orders;

use Illuminate\Support\Facades\DB;

final class RtsRate
{
    public function compute(int $workspaceId): float
    {
        $row = DB::table('pancake_orders')
            ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
            ->where('pages.workspace_id', $workspaceId)
            ->selectRaw("
                COALESCE(
                    (SUM(CASE WHEN pancake_orders.returning_at IS NOT NULL THEN pancake_orders.final_amount ELSE 0 END))
                    / NULLIF(
                        (SUM(CASE WHEN pancake_orders.returning_at IS NOT NULL THEN pancake_orders.final_amount ELSE 0 END))
                        + (SUM(CASE WHEN pancake_orders.delivered_at IS NOT NULL THEN pancake_orders.final_amount ELSE 0 END)),
                        0
                    ),
                    0
                ) as rts_rate
            ")
            ->first();

        return (float) ($row->rts_rate ?? 0);
    }
}
