<?php

namespace App\Support\AnalyticsRollup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LocationRollupBuilder
{
    private const COLUMNS = ['delivered_count', 'returning_count', 'returned_count'];

    public function forDate(int $workspaceId, string $date): void
    {
        $start = $date.' 00:00:00';
        $endExclusive = CarbonImmutable::parse($date)->addDay()->toDateTimeString();

        // Nested map: province_name => district_name => page_id => [column => value]
        $aggregated = [];

        $this->collect($aggregated, $workspaceId, $start, $endExclusive, status: 3, dateColumn: 'delivered_at', column: 'delivered_count');
        $this->collect($aggregated, $workspaceId, $start, $endExclusive, status: 4, dateColumn: 'returning_at', column: 'returning_count');
        $this->collect($aggregated, $workspaceId, $start, $endExclusive, status: 5, dateColumn: 'returning_at', column: 'returned_count');

        DB::table('workspace_daily_metrics_by_location')
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
                COALESCE(sa.province_name, '') AS province_name,
                COALESCE(sa.district_name, '') AS district_name,
                COALESCE(po.page_id, 0) AS page_id,
                COUNT(*) AS value
            FROM pancake_orders po
            LEFT JOIN shipping_addresses sa ON sa.order_id = po.id
            WHERE po.workspace_id = ?
              AND po.status = ?
              AND po.{$dateColumn} >= ?
              AND po.{$dateColumn} < ?
            GROUP BY province_name, district_name, page_id
            SQL;

        foreach (DB::select($sql, [$workspaceId, $status, $start, $endExclusive]) as $row) {
            $agg[$row->province_name][$row->district_name][$row->page_id][$column] = $row->value;
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

        foreach ($aggregated as $province => $perDistrict) {
            foreach ($perDistrict as $district => $perPage) {
                foreach ($perPage as $pageId => $columns) {
                    $payload[] = array_merge(
                        [
                            'workspace_id' => $workspaceId,
                            'date' => $date,
                            'province_name' => $province,
                            'district_name' => $district,
                            'page_id' => $pageId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        $defaults,
                        $columns,
                    );
                }
            }
        }

        DB::table('workspace_daily_metrics_by_location')->upsert(
            $payload,
            ['workspace_id', 'date', 'province_name', 'district_name', 'page_id'],
            [...self::COLUMNS, 'updated_at'],
        );
    }
}
