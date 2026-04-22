<?php

namespace App\Support\AnalyticsRollup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MainRollupBuilder
{
    public function forDate(int $workspaceId, string $date): void
    {
        $start = $date.' 00:00:00';
        $endExclusive = CarbonImmutable::parse($date)->addDay()->toDateTimeString();

        DB::transaction(function () use ($workspaceId, $date, $start, $endExclusive) {
            DB::table('workspace_daily_metrics')
                ->where('workspace_id', $workspaceId)
                ->where('date', $date)
                ->delete();

            $this->aggregateConfirmed($workspaceId, $date, $start, $endExclusive);
            $this->aggregateDelivered($workspaceId, $date, $start, $endExclusive);
            $this->aggregateReturningInTransit($workspaceId, $date, $start, $endExclusive);
            $this->aggregateReturnedFinal($workspaceId, $date, $start, $endExclusive);
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

    private function aggregateConfirmed(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                confirmed_count, total_sales,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS confirmed_count,
                COALESCE(SUM(po.final_amount), 0) AS total_sales,
                NOW(), NOW()
            FROM pancake_orders po
            WHERE po.workspace_id = ?
              AND po.confirmed_at >= ?
              AND po.confirmed_at < ?
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                confirmed_count = VALUES(confirmed_count),
                total_sales = VALUES(total_sales),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    private function aggregateDelivered(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                delivered_count, delivered_amount,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS delivered_count,
                COALESCE(SUM(po.final_amount), 0) AS delivered_amount,
                NOW(), NOW()
            FROM pancake_orders po
            WHERE po.workspace_id = ?
              AND po.status = 3
              AND po.delivered_at >= ?
              AND po.delivered_at < ?
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                delivered_count = VALUES(delivered_count),
                delivered_amount = VALUES(delivered_amount),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    private function aggregateReturningInTransit(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                returning_in_transit_count, returning_amount,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS returning_in_transit_count,
                COALESCE(SUM(po.final_amount), 0) AS returning_amount,
                NOW(), NOW()
            FROM pancake_orders po
            WHERE po.workspace_id = ?
              AND po.status = 4
              AND po.returning_at >= ?
              AND po.returning_at < ?
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                returning_in_transit_count = VALUES(returning_in_transit_count),
                returning_amount = VALUES(returning_amount),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    private function aggregateReturnedFinal(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO workspace_daily_metrics (
                workspace_id, date, page_id,
                returned_final_count, returned_amount,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                ? AS date,
                po.page_id,
                COUNT(*) AS returned_final_count,
                COALESCE(SUM(po.final_amount), 0) AS returned_amount,
                NOW(), NOW()
            FROM pancake_orders po
            WHERE po.workspace_id = ?
              AND po.status = 5
              AND po.returning_at >= ?
              AND po.returning_at < ?
              AND po.page_id IS NOT NULL
            GROUP BY po.workspace_id, po.page_id
            ON DUPLICATE KEY UPDATE
                returned_final_count = VALUES(returned_final_count),
                returned_amount = VALUES(returned_amount),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }
}
