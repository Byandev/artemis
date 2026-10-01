<?php

namespace App\Console\Commands;

use App\Support\PhilippineGeo;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\PageOrdersToLocationDailyRecord;

/**
 * Rebuilds page_orders_to_location_daily_records — the per page, per province,
 * per day rollup the RTS heat map reads.
 *
 * An order lands on the day it reached its outcome: delivered_at when delivered
 * (status 3), returning_at when returned (status 4/5). Because those are two
 * different columns with their own indexes, the aggregation runs as two passes
 * that are merged in PHP rather than as one OR that would scan.
 *
 * Both the count and the value (final_amount) of each side are stored, so the
 * heat map can rate an area by money returned rather than only by order count.
 *
 * Each (workspace, date) is rebuilt wholesale inside a transaction, so re-running
 * over a range is safe and picks up orders whose status changed after the fact.
 *
 * Province id, island group and map polygon all come from Pancake's own province
 * list (public/ph_provinces.json) by way of App\Support\PhilippineGeo.
 */
class BuildOrderLocationDailyRecords extends Command
{
    protected $signature = 'build-order-location-daily-records
        {--date= : Rebuild a single day (YYYY-MM-DD)}
        {--days=3 : Rebuild this many days back from today}
        {--from= : Start date (YYYY-MM-DD), overrides --days}
        {--to= : End date (YYYY-MM-DD), defaults to today}
        {--workspace= : Limit to a single workspace id}';

    protected $description = 'Rebuild the daily rollup of delivered/returned orders by destination';

    public function handle(): int
    {
        if ($this->option('date')) {
            $from = $to = CarbonImmutable::parse($this->option('date'))->startOfDay();
        } else {
            $to = $this->option('to')
                ? CarbonImmutable::parse($this->option('to'))->startOfDay()
                : CarbonImmutable::today();

            $from = $this->option('from')
                ? CarbonImmutable::parse($this->option('from'))->startOfDay()
                : $to->subDays(max(0, (int) $this->option('days') - 1));
        }

        if ($from->gt($to)) {
            $this->error('--from must not be after --to.');

            return self::FAILURE;
        }

        $workspaceId = $this->option('workspace') ? (int) $this->option('workspace') : null;

        $this->info(sprintf(
            'Rebuilding %s → %s%s',
            $from->toDateString(),
            $to->toDateString(),
            $workspaceId ? " (workspace {$workspaceId})" : '',
        ));

        $days = $from->diffInDays($to) + 1;
        $bar = $this->output->createProgressBar($days);
        $bar->start();

        $written = 0;

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $written += $this->rebuildDate($date, $workspaceId);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Wrote {$written} rows.");

        return self::SUCCESS;
    }

    /** @return int rows written for the day */
    private function rebuildDate(CarbonImmutable $date, ?int $workspaceId): int
    {
        $rows = $this->aggregate($date, $workspaceId);

        return DB::transaction(function () use ($date, $workspaceId, $rows) {
            PageOrdersToLocationDailyRecord::query()
                ->whereDate('date', $date)
                ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->delete();

            $now = now();

            foreach (array_chunk($rows, 500) as $chunk) {
                PageOrdersToLocationDailyRecord::insert(array_map(
                    fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now],
                    $chunk,
                ));
            }

            return count($rows);
        });
    }

    /**
     * Delivered and returned orders are counted separately — each pass hits its own
     * (workspace_id, status, <date column>) index — then merged on the row grain.
     *
     * @return list<array<string, mixed>>
     */
    private function aggregate(CarbonImmutable $date, ?int $workspaceId): array
    {
        $merged = [];

        foreach ([['delivered_at', [3]], ['returning_at', [4, 5]]] as [$column, $statuses]) {
            $records = DB::table('pancake_orders')
                ->leftJoin('shipping_addresses', 'shipping_addresses.order_id', '=', 'pancake_orders.id')
                ->selectRaw('
                    pancake_orders.workspace_id,
                    pancake_orders.page_id,
                    pancake_orders.shop_id,
                    shipping_addresses.province_name,
                    COUNT(*) AS orders,
                    COALESCE(SUM(pancake_orders.final_amount), 0) AS amount
                ')
                ->whereIn('pancake_orders.status', $statuses)
                ->whereBetween("pancake_orders.{$column}", [
                    $date->startOfDay(),
                    $date->endOfDay(),
                ])
                ->when($workspaceId, fn ($q) => $q->where('pancake_orders.workspace_id', $workspaceId))
                ->groupBy(
                    'pancake_orders.workspace_id',
                    'pancake_orders.page_id',
                    'pancake_orders.shop_id',
                    'shipping_addresses.province_name',
                )
                ->get();

            [$countColumn, $amountColumn] = $statuses === [3]
                ? ['delivered_count', 'delivered_amount']
                : ['returning_count', 'returning_amount'];

            foreach ($records as $record) {
                $key = implode('|', [
                    $record->workspace_id,
                    $record->page_id ?? '',
                    $record->shop_id ?? '',
                    $record->province_name ?? '',
                ]);

                $province = PhilippineGeo::province($record->province_name);

                $merged[$key] ??= [
                    'workspace_id' => (int) $record->workspace_id,
                    'page_id' => $record->page_id ? (int) $record->page_id : null,
                    'shop_id' => $record->shop_id ? (int) $record->shop_id : null,
                    'date' => $date->toDateString(),
                    'province_id' => $province['id'] ?? null,
                    'province_name' => $record->province_name,
                    'region' => $province['region'] ?? null,
                    'gadm_province_gid' => $province['gid'] ?? null,
                    'orders' => 0,
                    'sales' => 0,
                    'delivered_count' => 0,
                    'delivered_amount' => 0,
                    'returning_count' => 0,
                    'returning_amount' => 0,
                ];

                $merged[$key][$countColumn] += (int) $record->orders;
                $merged[$key][$amountColumn] += (float) $record->amount;
                $merged[$key]['orders'] += (int) $record->orders;
                $merged[$key]['sales'] += (float) $record->amount;
            }
        }

        return array_values($merged);
    }
}
