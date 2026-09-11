<?php

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\PageOrderReportBreakdownDailyRecord;

/**
 * Builds page_order_report_breakdown_daily_records — the per page, per day
 * breakdown of orders by the customer history they arrived with.
 *
 * An order lands on the day it was confirmed (confirmed_at) and is grouped by the
 * (order_fail, order_success) pair of its 'initial' phone-number report, the
 * snapshot Pancake reported at creation time. The join is an inner one, so an
 * order whose phone carried no report at all is not represented — matching the
 * base query this rollup was cut from.
 *
 * Cancelled and removed orders (status 6 and 7) are excluded, the same rule the
 * rest of app/Metrics/Orders applies. Because confirmed_at is nullable, an order
 * that was never confirmed has no day to land on and simply does not appear.
 *
 * Orders whose customer had no prior history at all (fail + success = 0) are
 * excluded too. That bucket reads like "first-time customers" but is not:
 * SyncPhoneNumberReportsAction writes no report when fail, success and warning
 * are all zero, so a genuinely new customer has no report row and the inner join
 * has already dropped them. What reaches 0/0 is only the sliver carrying a
 * warning and no order history — a partial slice that would be misread as the
 * new-customer count. Every row here therefore describes real prior history.
 *
 * Shared by BuildPageOrderReportBreakdownJob and the command that dispatches it,
 * so a queued rebuild and an inline `--sync` one cannot drift apart.
 */
class PageOrderReportBreakdownBuilder
{
    /**
     * Rebuild one day, wholesale, inside a transaction — so re-running is safe
     * and picks up orders whose report was synced after the order row itself.
     *
     * @param  string  $date  Y-m-d
     * @param  int|null  $workspaceId  Limit the rebuild to one workspace; null covers every one.
     * @return int rows written for the day
     */
    public function rebuild(string $date, ?int $workspaceId = null): int
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        $rows = $this->aggregate($day, $workspaceId);

        return DB::transaction(function () use ($day, $workspaceId, $rows) {
            PageOrderReportBreakdownDailyRecord::query()
                ->whereDate('date', $day)
                ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->delete();

            $now = now();

            foreach (array_chunk($rows, 500) as $chunk) {
                PageOrderReportBreakdownDailyRecord::insert(array_map(
                    fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now],
                    $chunk,
                ));
            }

            return count($rows);
        });
    }

    /**
     * One row per workspace / page / shop / (order_fail, order_success) pair.
     *
     * The day is filtered as a range rather than DATE(confirmed_at) = ... so
     * idx_orders_workspace_confirmed_status (workspace_id, confirmed_at, status)
     * is usable; the grouping still collapses to the one day. That index is why
     * the rebuild is fanned out per workspace — each job supplies the
     * workspace_id equality the index needs to lead with.
     *
     * @return list<array<string, mixed>>
     */
    private function aggregate(CarbonImmutable $day, ?int $workspaceId): array
    {
        $records = DB::table('pancake_orders')
            ->join('pancake_order_phone_number_reports AS pnr', function ($join) {
                $join->on('pnr.order_id', '=', 'pancake_orders.id')
                    ->where('pnr.type', '=', 'initial');
            })
            ->selectRaw('
                pancake_orders.workspace_id,
                pancake_orders.page_id,
                pancake_orders.shop_id,
                pnr.order_fail,
                pnr.order_success,
                COUNT(*) AS orders_count
            ')
            ->whereBetween('pancake_orders.confirmed_at', [
                $day->startOfDay(),
                $day->endOfDay(),
            ])
            // Cancelled / removed. Excluded everywhere else orders are counted.
            ->whereNotIn('pancake_orders.status', [6, 7])
            // Drop the zero-history bucket. Expressed as a WHERE rather than the
            // HAVING it was specified as: order_fail and order_success are both
            // in the GROUP BY, so every row of a group shares them and the two
            // are equivalent — this just filters before aggregating instead of after.
            ->whereRaw('pnr.order_fail + pnr.order_success >= 1')
            ->when($workspaceId, fn ($q) => $q->where('pancake_orders.workspace_id', $workspaceId))
            ->groupBy(
                'pancake_orders.workspace_id',
                'pancake_orders.page_id',
                'pancake_orders.shop_id',
                'pnr.order_fail',
                'pnr.order_success',
            )
            ->get();

        return $records->map(fn ($record) => [
            'workspace_id' => (int) $record->workspace_id,
            'page_id' => $record->page_id ? (int) $record->page_id : null,
            'shop_id' => $record->shop_id ? (int) $record->shop_id : null,
            'date' => $day->toDateString(),
            'order_fail' => (int) $record->order_fail,
            'order_success' => (int) $record->order_success,
            'total_orders' => (int) $record->order_fail + (int) $record->order_success,
            'orders_count' => (int) $record->orders_count,
        ])->all();
    }
}
