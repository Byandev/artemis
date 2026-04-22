<?php

namespace App\Support\AnalyticsRollup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LocationRollupBuilder
{
    public function forDate(int $workspaceId, string $date): void
    {
        $start = $date.' 00:00:00';
        $endExclusive = CarbonImmutable::parse($date)->addDay()->toDateTimeString();

        DB::transaction(function () use ($workspaceId, $date, $start, $endExclusive) {
            DB::table('workspace_daily_metrics_by_location')
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
     * LEFT JOIN shipping_addresses to include orders without a known address
     * (bucketed as empty-string location). Matches live RtsLocationQuery behavior.
     */
    private function aggregate(int $workspaceId, string $date, string $start, string $endExclusive, int $status, string $dateColumn, string $column): void
    {
        $sql = "
            INSERT INTO workspace_daily_metrics_by_location (
                workspace_id, date, province_name, district_name, page_id,
                {$column},
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                COALESCE(sa.province_name, '') AS province_name,
                COALESCE(sa.district_name, '') AS district_name,
                COALESCE(po.page_id, 0) AS page_id,
                COUNT(*) AS {$column},
                NOW(), NOW()
            FROM pancake_orders po
            LEFT JOIN shipping_addresses sa ON sa.order_id = po.id
            WHERE po.workspace_id = ?
              AND po.status = ?
              AND po.{$dateColumn} >= ?
              AND po.{$dateColumn} < ?
            GROUP BY po.workspace_id, COALESCE(sa.province_name, ''), COALESCE(sa.district_name, ''), page_id
            ON DUPLICATE KEY UPDATE
                {$column} = VALUES({$column}),
                updated_at = NOW()
        ";

        DB::statement($sql, [$date, $workspaceId, $status, $start, $endExclusive]);
    }
}
