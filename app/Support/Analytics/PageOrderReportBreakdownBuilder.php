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
 * snapshot Pancake reported at creation time.
 *
 * Cancelled and removed orders (status 6 and 7) are excluded, the same rule the
 * rest of app/Metrics/Orders applies. Because confirmed_at is nullable, an order
 * that was never confirmed has no day to land on and simply does not appear.
 *
 * Every order of the day lands somewhere, in one of two kinds of row:
 *
 *  - total_orders >= 1 — the customer had real prior history. One row per
 *    distinct (order_fail, order_success) pair seen that day.
 *  - total_orders = 0  — no usable history. One row per page, covering both the
 *    numbers Pancake had never seen (no 'initial' report is written at all when
 *    fail, success and warning are all zero) and the numbers it had seen with
 *    nothing on them (a report carrying only a warning). The two are
 *    indistinguishable to a reader of this table, and deliberately so: either
 *    way nothing is known about how that customer's past orders went.
 *
 * Nothing is dropped, so SUM(orders_count) over a day equals that day's confirmed,
 * non-cancelled orders.
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
     * The day's orders in two passes over the same population: those whose
     * customer had prior orders on record, and those who had none.
     *
     * @return list<array<string, mixed>>
     */
    private function aggregate(CarbonImmutable $day, ?int $workspaceId): array
    {
        return [
            ...$this->aggregateWithHistory($day, $workspaceId),
            ...$this->aggregateWithoutUsableHistory($day, $workspaceId),
        ];
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
    private function aggregateWithHistory(CarbonImmutable $day, ?int $workspaceId): array
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

    /**
     * One row per workspace / page / shop for the day's orders with no usable
     * history — no (fail, success) pair to group by, so they collapse together.
     *
     * The anti-join is restricted to reports that actually show prior orders, so
     * a single WHERE pnr.id IS NULL catches both ways an order gets here: no
     * 'initial' report at all, and an 'initial' report sitting at 0/0. An order
     * that has both a 0/0 report and a real one (two phone numbers) still joins
     * on the real one and is counted as history, not here.
     *
     * Deliberately filtered on confirmed_at and status the same way the history
     * pass is: the two slices only add up to the day's orders — which is the
     * point of keeping both — if they cover exactly the same population.
     *
     * @return list<array<string, mixed>>
     */
    private function aggregateWithoutUsableHistory(CarbonImmutable $day, ?int $workspaceId): array
    {
        $records = DB::table('pancake_orders')
            ->leftJoin('pancake_order_phone_number_reports AS pnr', function ($join) {
                $join->on('pnr.order_id', '=', 'pancake_orders.id')
                    ->where('pnr.type', '=', 'initial')
                    ->whereRaw('pnr.order_fail + pnr.order_success >= 1');
            })
            ->whereNull('pnr.id')
            ->selectRaw('
                pancake_orders.workspace_id,
                pancake_orders.page_id,
                pancake_orders.shop_id,
                COUNT(*) AS orders_count
            ')
            ->whereBetween('pancake_orders.confirmed_at', [
                $day->startOfDay(),
                $day->endOfDay(),
            ])
            ->whereNotIn('pancake_orders.status', [6, 7])
            ->when($workspaceId, fn ($q) => $q->where('pancake_orders.workspace_id', $workspaceId))
            ->groupBy(
                'pancake_orders.workspace_id',
                'pancake_orders.page_id',
                'pancake_orders.shop_id',
            )
            ->get();

        return $records->map(fn ($record) => [
            'workspace_id' => (int) $record->workspace_id,
            'page_id' => $record->page_id ? (int) $record->page_id : null,
            'shop_id' => $record->shop_id ? (int) $record->shop_id : null,
            'date' => $day->toDateString(),
            'order_fail' => 0,
            'order_success' => 0,
            'total_orders' => 0,
            'orders_count' => (int) $record->orders_count,
        ])->all();
    }
}
