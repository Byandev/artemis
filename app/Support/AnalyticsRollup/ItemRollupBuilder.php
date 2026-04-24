<?php

namespace App\Support\AnalyticsRollup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ItemRollupBuilder
{
    private const COUNT_COLUMNS = ['delivered_count', 'returning_count', 'returned_count'];

    public function forDate(int $workspaceId, string $date): void
    {
        $start = $date.' 00:00:00';
        $endExclusive = CarbonImmutable::parse($date)->addDay()->toDateTimeString();

        // Nested map: item_name => page_id => [column => value]
        // total_quantity accumulates across all status passes.
        $aggregated = [];

        $this->collect($aggregated, $workspaceId, $start, $endExclusive, status: 3, dateColumn: 'delivered_at', column: 'delivered_count');
        $this->collect($aggregated, $workspaceId, $start, $endExclusive, status: 4, dateColumn: 'returning_at', column: 'returning_count');
        $this->collect($aggregated, $workspaceId, $start, $endExclusive, status: 5, dateColumn: 'returning_at', column: 'returned_count');

        DB::table('workspace_daily_metrics_by_item')
            ->where('workspace_id', $workspaceId)
            ->where('date', $date)
            ->delete();

        $this->upsertAggregated($workspaceId, $date, $aggregated);
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

    private function collect(array &$agg, int $workspaceId, string $start, string $endExclusive, int $status, string $dateColumn, string $column): void
    {
        $sql = <<<SQL
            SELECT
                COALESCE(poi.name, '') AS item_name,
                COALESCE(po.page_id, 0) AS page_id,
                COUNT(*) AS count_value,
                COALESCE(SUM(poi.quantity), 0) AS quantity
            FROM pancake_orders po
            INNER JOIN pancake_order_items poi ON poi.order_id = po.id
            WHERE po.workspace_id = ?
              AND po.status = ?
              AND po.{$dateColumn} >= ?
              AND po.{$dateColumn} < ?
            GROUP BY item_name, page_id
            SQL;

        foreach (DB::select($sql, [$workspaceId, $status, $start, $endExclusive]) as $row) {
            $agg[$row->item_name][$row->page_id][$column] = $row->count_value;
            $agg[$row->item_name][$row->page_id]['total_quantity'] =
                ($agg[$row->item_name][$row->page_id]['total_quantity'] ?? 0) + (int) $row->quantity;
        }
    }

    private function upsertAggregated(int $workspaceId, string $date, array $aggregated): void
    {
        if (empty($aggregated)) {
            return;
        }

        $now = Carbon::now();
        $defaults = array_fill_keys([...self::COUNT_COLUMNS, 'total_quantity'], 0);
        $payload = [];

        foreach ($aggregated as $itemName => $perPage) {
            foreach ($perPage as $pageId => $columns) {
                $payload[] = array_merge(
                    [
                        'workspace_id' => $workspaceId,
                        'date' => $date,
                        'item_name' => $itemName,
                        'page_id' => $pageId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $defaults,
                    $columns,
                );
            }
        }

        DB::table('workspace_daily_metrics_by_item')->upsert(
            $payload,
            ['workspace_id', 'date', 'item_name', 'page_id'],
            [...self::COUNT_COLUMNS, 'total_quantity', 'updated_at'],
        );
    }
}
