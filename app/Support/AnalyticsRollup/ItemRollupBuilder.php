<?php

namespace App\Support\AnalyticsRollup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ItemRollupBuilder
{
    public function forDate(int $workspaceId, string $date): void
    {
        $start = $date.' 00:00:00';
        $endExclusive = CarbonImmutable::parse($date)->addDay()->toDateTimeString();

        DB::transaction(function () use ($workspaceId, $date, $start, $endExclusive) {
            DB::table('workspace_daily_metrics_by_item')
                ->where('workspace_id', $workspaceId)
                ->where('date', $date)
                ->delete();

            $this->aggregate($workspaceId, $date, $start, $endExclusive, status: 3, dateColumn: 'delivered_at', column: 'delivered_count');
            $this->aggregate($workspaceId, $date, $start, $endExclusive, status: 4, dateColumn: 'returning_at', column: 'returning_count');
            $this->aggregate($workspaceId, $date, $start, $endExclusive, status: 5, dateColumn: 'returning_at', column: 'returned_count');
        });
    }

    public function forDateRange(int $workspaceId, string $from, string $to): void
    {
        $cursor = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);

        while ($cursor->lte($end)) {
            $this->forDate($workspaceId, $cursor->toDateString());
            $cursor = $cursor->addDay();
        }
    }

    /**
     * One row per (workspace, date, item_name). One order with multiple items
     * contributes to multiple item rows — matches live RtsOrderItemQuery behavior.
     */
    private function aggregate(int $workspaceId, string $date, string $start, string $endExclusive, int $status, string $dateColumn, string $column): void
    {
        $sql = "
            INSERT INTO workspace_daily_metrics_by_item (
                workspace_id, date, item_name,
                {$column}, total_quantity,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                COALESCE(poi.name, '') AS item_name,
                COUNT(*) AS {$column},
                COALESCE(SUM(poi.quantity), 0) AS total_quantity,
                NOW(), NOW()
            FROM pancake_orders po
            INNER JOIN pancake_order_items poi ON poi.order_id = po.id
            WHERE po.workspace_id = ?
              AND po.status = ?
              AND po.{$dateColumn} >= ?
              AND po.{$dateColumn} < ?
            GROUP BY po.workspace_id, COALESCE(poi.name, '')
            ON DUPLICATE KEY UPDATE
                {$column} = VALUES({$column}),
                total_quantity = total_quantity + VALUES(total_quantity),
                updated_at = NOW()
        ";

        DB::statement($sql, [$date, $workspaceId, $status, $start, $endExclusive]);
    }
}
