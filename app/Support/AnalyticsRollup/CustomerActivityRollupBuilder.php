<?php

namespace App\Support\AnalyticsRollup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
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

        DB::table('workspace_customer_activity_daily')
            ->where('workspace_id', $workspaceId)
            ->where('date', $date)
            ->delete();

        $rows = DB::select(<<<'SQL'
            SELECT
                po.customer_id,
                po.page_id,
                COUNT(*) AS confirmed_count,
                COALESCE(SUM(po.final_amount), 0) AS confirmed_spend
            FROM pancake_orders po
            WHERE po.workspace_id = ?
              AND po.confirmed_at >= ?
              AND po.confirmed_at < ?
              AND po.customer_id IS NOT NULL
              AND po.page_id IS NOT NULL
              AND po.status NOT IN (6, 7)
            GROUP BY po.customer_id, po.page_id
            SQL, [$workspaceId, $start, $endExclusive]);

        if (empty($rows)) {
            return;
        }

        $now = Carbon::now();
        $payload = array_map(fn ($row) => [
            'workspace_id' => $workspaceId,
            'customer_id' => $row->customer_id,
            'date' => $date,
            'page_id' => $row->page_id,
            'confirmed_count' => $row->confirmed_count,
            'confirmed_spend' => $row->confirmed_spend,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        DB::table('workspace_customer_activity_daily')->upsert(
            $payload,
            ['workspace_id', 'customer_id', 'date', 'page_id'],
            ['confirmed_count', 'confirmed_spend', 'updated_at'],
        );
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
}
