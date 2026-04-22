<?php

namespace App\Support\AnalyticsRollup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CsrPosRollupBuilder
{
    public function forDate(int $workspaceId, string $date): void
    {
        $start = $date.' 00:00:00';
        $endExclusive = CarbonImmutable::parse($date)->addDay()->toDateTimeString();

        DB::transaction(function () use ($workspaceId, $date, $start, $endExclusive) {
            DB::table('pancake_user_pos_daily_reports')
                ->where('workspace_id', $workspaceId)
                ->where('date', $date)
                ->delete();

            $this->aggregateConfirmed($workspaceId, $date, $start, $endExclusive);
            $this->aggregateDelivered($workspaceId, $date, $start, $endExclusive);
            $this->aggregateReturning($workspaceId, $date, $start, $endExclusive);
            $this->aggregateReturned($workspaceId, $date, $start, $endExclusive);
            $this->syncLegacyColumns($workspaceId, $date);
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
            INSERT INTO pancake_user_pos_daily_reports (
                workspace_id, pancake_user_id, date,
                confirmed_count, total_sales,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                pu.id,
                ? AS date,
                COUNT(*) AS confirmed_count,
                COALESCE(SUM(po.final_amount), 0) AS total_sales,
                NOW(), NOW()
            FROM pancake_orders po
            INNER JOIN pancake_users pu ON pu.id = po.confirmed_by
            WHERE po.workspace_id = ?
              AND po.confirmed_at >= ?
              AND po.confirmed_at < ?
              AND po.confirmed_by IS NOT NULL
            GROUP BY po.workspace_id, pu.id
            ON DUPLICATE KEY UPDATE
                confirmed_count = VALUES(confirmed_count),
                total_sales = VALUES(total_sales),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    private function aggregateDelivered(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO pancake_user_pos_daily_reports (
                workspace_id, pancake_user_id, date,
                delivered_count, delivered_amount, sum_delivery_attempts_delivered,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                pu.id,
                ? AS date,
                COUNT(*) AS delivered_count,
                COALESCE(SUM(po.final_amount), 0) AS delivered_amount,
                COALESCE(SUM(po.delivery_attempts), 0) AS sum_delivery_attempts_delivered,
                NOW(), NOW()
            FROM pancake_orders po
            INNER JOIN pancake_users pu ON pu.id = po.confirmed_by
            WHERE po.workspace_id = ?
              AND po.status = 3
              AND po.delivered_at >= ?
              AND po.delivered_at < ?
              AND po.confirmed_by IS NOT NULL
            GROUP BY po.workspace_id, pu.id
            ON DUPLICATE KEY UPDATE
                delivered_count = VALUES(delivered_count),
                delivered_amount = VALUES(delivered_amount),
                sum_delivery_attempts_delivered = VALUES(sum_delivery_attempts_delivered),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    private function aggregateReturning(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO pancake_user_pos_daily_reports (
                workspace_id, pancake_user_id, date,
                returning_count, returning_amount, sum_delivery_attempts_returned,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                pu.id,
                ? AS date,
                COUNT(*) AS returning_count,
                COALESCE(SUM(po.final_amount), 0) AS returning_amount,
                COALESCE(SUM(po.delivery_attempts), 0) AS sum_delivery_attempts_returned,
                NOW(), NOW()
            FROM pancake_orders po
            INNER JOIN pancake_users pu ON pu.id = po.confirmed_by
            WHERE po.workspace_id = ?
              AND po.status = 4
              AND po.returning_at >= ?
              AND po.returning_at < ?
              AND po.confirmed_by IS NOT NULL
            GROUP BY po.workspace_id, pu.id
            ON DUPLICATE KEY UPDATE
                returning_count = VALUES(returning_count),
                returning_amount = VALUES(returning_amount),
                sum_delivery_attempts_returned = sum_delivery_attempts_returned + VALUES(sum_delivery_attempts_returned),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    private function aggregateReturned(int $workspaceId, string $date, string $start, string $endExclusive): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO pancake_user_pos_daily_reports (
                workspace_id, pancake_user_id, date,
                returned_count, returned_amount, sum_delivery_attempts_returned,
                created_at, updated_at
            )
            SELECT
                po.workspace_id,
                pu.id,
                ? AS date,
                COUNT(*) AS returned_count,
                COALESCE(SUM(po.final_amount), 0) AS returned_amount,
                COALESCE(SUM(po.delivery_attempts), 0) AS sum_delivery_attempts_returned,
                NOW(), NOW()
            FROM pancake_orders po
            INNER JOIN pancake_users pu ON pu.id = po.confirmed_by
            WHERE po.workspace_id = ?
              AND po.status = 5
              AND po.returning_at >= ?
              AND po.returning_at < ?
              AND po.confirmed_by IS NOT NULL
            GROUP BY po.workspace_id, pu.id
            ON DUPLICATE KEY UPDATE
                returned_count = VALUES(returned_count),
                returned_amount = VALUES(returned_amount),
                sum_delivery_attempts_returned = sum_delivery_attempts_returned + VALUES(sum_delivery_attempts_returned),
                updated_at = NOW()
            SQL, [$date, $workspaceId, $start, $endExclusive]);
    }

    /**
     * Mirror the new count/amount columns into the legacy columns the CSR dashboard reads.
     */
    private function syncLegacyColumns(int $workspaceId, string $date): void
    {
        DB::table('pancake_user_pos_daily_reports')
            ->where('workspace_id', $workspaceId)
            ->where('date', $date)
            ->update([
                'total_orders' => DB::raw('confirmed_count'),
                'delivered' => DB::raw('delivered_amount'),
                'returning' => DB::raw('returning_amount + returned_amount'),
            ]);
    }
}
