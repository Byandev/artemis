<?php

namespace App\Support\AnalyticsRollup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Per-(workspace, customer, date, page) activity rollup. Serves range-sensitive
 * distinct-count metrics (UniqueCustomerCount, RepeatCustomerRatio, RepeatOrderRatio,
 * RepeatCustomerOrderCount).
 */
class CustomerActivityRollupBuilder
{
    public function forDate(int $workspaceId, string $date): void
    {
        $start = $date.' 00:00:00';
        $endExclusive = CarbonImmutable::parse($date)->addDay()->toDateTimeString();

        DB::transaction(function () use ($workspaceId, $date, $start, $endExclusive) {
            DB::table('workspace_customer_activity_daily')
                ->where('workspace_id', $workspaceId)
                ->where('date', $date)
                ->delete();

            $this->aggregate($workspaceId, $date, $start, $endExclusive);
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

    private function aggregate(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_customer_activity_daily (
                workspace_id, customer_id, date, page_id,
                confirmed_count, confirmed_spend,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                po.customer_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS confirmed_count,
                COALESCE(SUM(po.final_amount), 0) AS confirmed_spend,
                NOW(), NOW()
            FROM pancake_orders po
            WHERE po.workspace_id = ?
              AND po.confirmed_at >= ?
              AND po.confirmed_at < ?
              AND po.customer_id IS NOT NULL
              AND po.page_id IS NOT NULL
              AND po.status NOT IN (6, 7)
            GROUP BY po.workspace_id, po.customer_id, po.page_id
            ON DUPLICATE KEY UPDATE
                confirmed_count = VALUES(confirmed_count),
                confirmed_spend = VALUES(confirmed_spend),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }
}
