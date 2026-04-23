<?php

namespace App\Support\AnalyticsRollup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CsrPosRollupBuilder
{
    /**
     * All columns maintained by this builder (excluding the unique key and timestamps).
     * Legacy columns are written by syncLegacyColumns() from these values.
     */
    private const COLUMNS = [
        'confirmed_count',
        'delivered_count',
        'returning_count',
        'returned_count',
        'total_sales',
        'delivered_amount',
        'returning_amount',
        'returned_amount',
        'sum_delivery_attempts_delivered',
        'sum_delivery_attempts_returned',
    ];

    public function forDate(int $workspaceId, string $date): void
    {
        $start = $date.' 00:00:00';
        $endExclusive = CarbonImmutable::parse($date)->addDay()->toDateTimeString();

        DB::table('pancake_user_pos_daily_reports')
            ->where('workspace_id', $workspaceId)
            ->where('date', $date)
            ->delete();

        // Map: pancake_user_id => [column => value]
        $aggregated = [];

        $this->collectConfirmed($aggregated, $workspaceId, $start, $endExclusive);
        $this->collectDelivered($aggregated, $workspaceId, $start, $endExclusive);
        $this->collectReturning($aggregated, $workspaceId, $start, $endExclusive);
        $this->collectReturned($aggregated, $workspaceId, $start, $endExclusive);

        $this->upsertAggregated($workspaceId, $date, $aggregated);
        $this->syncLegacyColumns($workspaceId, $date);
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

    private function collectConfirmed(array &$agg, int $workspaceId, string $start, string $endExclusive): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT
                pu.id AS pancake_user_id,
                COUNT(*) AS confirmed_count,
                COALESCE(SUM(po.final_amount), 0) AS total_sales
            FROM pancake_orders po
            INNER JOIN pancake_users pu ON pu.id = po.confirmed_by
            WHERE po.workspace_id = ?
              AND po.confirmed_at >= ?
              AND po.confirmed_at < ?
              AND po.confirmed_by IS NOT NULL
            GROUP BY pu.id
            SQL, [$workspaceId, $start, $endExclusive]);

        foreach ($rows as $row) {
            $agg[$row->pancake_user_id]['confirmed_count'] = $row->confirmed_count;
            $agg[$row->pancake_user_id]['total_sales'] = $row->total_sales;
        }
    }

    private function collectDelivered(array &$agg, int $workspaceId, string $start, string $endExclusive): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT
                pu.id AS pancake_user_id,
                COUNT(*) AS delivered_count,
                COALESCE(SUM(po.final_amount), 0) AS delivered_amount,
                COALESCE(SUM(po.delivery_attempts), 0) AS sum_delivery_attempts_delivered
            FROM pancake_orders po
            INNER JOIN pancake_users pu ON pu.id = po.confirmed_by
            WHERE po.workspace_id = ?
              AND po.status = 3
              AND po.delivered_at >= ?
              AND po.delivered_at < ?
              AND po.confirmed_by IS NOT NULL
            GROUP BY pu.id
            SQL, [$workspaceId, $start, $endExclusive]);

        foreach ($rows as $row) {
            $agg[$row->pancake_user_id]['delivered_count'] = $row->delivered_count;
            $agg[$row->pancake_user_id]['delivered_amount'] = $row->delivered_amount;
            $agg[$row->pancake_user_id]['sum_delivery_attempts_delivered'] = $row->sum_delivery_attempts_delivered;
        }
    }

    private function collectReturning(array &$agg, int $workspaceId, string $start, string $endExclusive): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT
                pu.id AS pancake_user_id,
                COUNT(*) AS returning_count,
                COALESCE(SUM(po.final_amount), 0) AS returning_amount,
                COALESCE(SUM(po.delivery_attempts), 0) AS sum_delivery_attempts_returned
            FROM pancake_orders po
            INNER JOIN pancake_users pu ON pu.id = po.confirmed_by
            WHERE po.workspace_id = ?
              AND po.status = 4
              AND po.returning_at >= ?
              AND po.returning_at < ?
              AND po.confirmed_by IS NOT NULL
            GROUP BY pu.id
            SQL, [$workspaceId, $start, $endExclusive]);

        foreach ($rows as $row) {
            $agg[$row->pancake_user_id]['returning_count'] = $row->returning_count;
            $agg[$row->pancake_user_id]['returning_amount'] = $row->returning_amount;
            // status 4 + 5 both contribute to sum_delivery_attempts_returned
            $agg[$row->pancake_user_id]['sum_delivery_attempts_returned'] =
                ($agg[$row->pancake_user_id]['sum_delivery_attempts_returned'] ?? 0) + (int) $row->sum_delivery_attempts_returned;
        }
    }

    private function collectReturned(array &$agg, int $workspaceId, string $start, string $endExclusive): void
    {
        $rows = DB::select(<<<'SQL'
            SELECT
                pu.id AS pancake_user_id,
                COUNT(*) AS returned_count,
                COALESCE(SUM(po.final_amount), 0) AS returned_amount,
                COALESCE(SUM(po.delivery_attempts), 0) AS sum_delivery_attempts_returned
            FROM pancake_orders po
            INNER JOIN pancake_users pu ON pu.id = po.confirmed_by
            WHERE po.workspace_id = ?
              AND po.status = 5
              AND po.returning_at >= ?
              AND po.returning_at < ?
              AND po.confirmed_by IS NOT NULL
            GROUP BY pu.id
            SQL, [$workspaceId, $start, $endExclusive]);

        foreach ($rows as $row) {
            $agg[$row->pancake_user_id]['returned_count'] = $row->returned_count;
            $agg[$row->pancake_user_id]['returned_amount'] = $row->returned_amount;
            $agg[$row->pancake_user_id]['sum_delivery_attempts_returned'] =
                ($agg[$row->pancake_user_id]['sum_delivery_attempts_returned'] ?? 0) + (int) $row->sum_delivery_attempts_returned;
        }
    }

    private function upsertAggregated(int $workspaceId, string $date, array $aggregated): void
    {
        if (empty($aggregated)) {
            return;
        }

        $now = Carbon::now();
        $defaults = array_fill_keys(self::COLUMNS, 0);
        $payload = [];

        foreach ($aggregated as $pancakeUserId => $columns) {
            $payload[] = array_merge(
                [
                    'workspace_id' => $workspaceId,
                    'pancake_user_id' => $pancakeUserId,
                    'date' => $date,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $defaults,
                $columns,
            );
        }

        DB::table('pancake_user_pos_daily_reports')->upsert(
            $payload,
            ['workspace_id', 'pancake_user_id', 'date'],
            [...self::COLUMNS, 'updated_at'],
        );
    }

    /**
     * Mirror the new count/amount columns into legacy columns read by existing CSR dashboards.
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
