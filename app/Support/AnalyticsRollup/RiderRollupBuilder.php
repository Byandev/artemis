<?php

namespace App\Support\AnalyticsRollup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RiderRollupBuilder
{
    public function forDate(int $workspaceId, string $date): void
    {
        $start = $date.' 00:00:00';
        $endExclusive = CarbonImmutable::parse($date)->addDay()->toDateTimeString();

        DB::transaction(function () use ($workspaceId, $date, $start, $endExclusive) {
            DB::table('workspace_daily_metrics_by_rider')
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
     * Attribute each order to the latest parcel_journey rider_name.
     * Matches the subquery used by RtsRiderQuery.
     */
    private function aggregate(int $workspaceId, string $date, string $start, string $endExclusive, int $status, string $dateColumn, string $column): void
    {
        $sql = "
            INSERT INTO workspace_daily_metrics_by_rider (
                workspace_id, date, rider_name,
                {$column},
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                SUBSTRING(lpj.rider_name, 1, 255) AS rider_name,
                COUNT(*) AS {$column},
                NOW(), NOW()
            FROM pancake_orders po
            INNER JOIN (
                SELECT pj.order_id, pj.rider_name
                FROM parcel_journeys pj
                INNER JOIN (
                    SELECT order_id, MAX(id) AS latest_id
                    FROM parcel_journeys
                    WHERE rider_name IS NOT NULL
                    GROUP BY order_id
                ) latest ON latest.latest_id = pj.id
                WHERE pj.rider_name IS NOT NULL
            ) lpj ON lpj.order_id = po.id
            WHERE po.workspace_id = ?
              AND po.status = ?
              AND po.{$dateColumn} >= ?
              AND po.{$dateColumn} < ?
            GROUP BY po.workspace_id, rider_name
            ON DUPLICATE KEY UPDATE
                {$column} = VALUES({$column}),
                updated_at = NOW()
        ";

        DB::statement($sql, [$date, $workspaceId, $status, $start, $endExclusive]);
    }
}
