<?php

namespace App\Console\Commands;

use App\Models\PancakeUserDailyReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCsrDailyRecords extends Command
{
    protected $signature = 'sync:csr-daily-records
                            {--date= : Target date in Y-m-d format (defaults to yesterday)}
                            {--type=POS : Record type label}';

    protected $description = 'Aggregate per-CSR order metrics for a given date and upsert into pancake_user_daily_reports.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))->toDateString()
            : CarbonImmutable::yesterday()->toDateString();

        $type = (string) $this->option('type');
        $start = $date . ' 00:00:00';
        $end   = $date . ' 23:59:59';

        $this->info("Syncing CSR daily records for {$date}...");

        $rows = DB::table('pancake_orders as po')
            ->join('pancake_users as pu', 'pu.id', '=', 'po.confirmed_by')
            ->leftJoinSub(
                DB::table('pancake_order_for_delivery as pofd')
                    ->join('pancake_users as pu2', 'pu2.id', '=', 'pofd.assignee_id')
                    ->where('pofd.delivery_date', $date)
                    ->where('pofd.status', '!=', 'PENDING')
                    ->groupBy('pofd.workspace_id', 'pu2.id')
                    ->selectRaw('pofd.workspace_id, pu2.id as pancake_user_id, COUNT(*) as rmo_called'),
                'rmo',
                fn ($join) => $join
                    ->on('rmo.workspace_id', '=', 'po.workspace_id')
                    ->on('rmo.pancake_user_id', '=', 'pu.id')
            )
            ->where(function ($q) use ($start, $end) {
                $q->where(fn ($q) => $q->where('po.status', 3)->whereBetween('po.delivered_at', [$start, $end]))
                  ->orWhere(fn ($q) => $q->whereIn('po.status', [4, 5])->whereBetween('po.returning_at', [$start, $end]))
                  ->orWhereBetween('po.confirmed_at', [$start, $end]);
            })
            ->groupBy('po.workspace_id', 'pu.id')
            ->selectRaw('
                po.workspace_id,
                pu.id                                                                                    AS pancake_user_id,
                SUM(po.confirmed_at BETWEEN ? AND ?)                                                     AS total_orders,
                SUM(CASE WHEN po.confirmed_at BETWEEN ? AND ? THEN po.final_amount ELSE 0 END)           AS total_sales,
                SUM(po.status IN (4,5) AND po.returning_at BETWEEN ? AND ?)                              AS returning_count,
                SUM(CASE WHEN po.status IN (4,5) AND po.returning_at BETWEEN ? AND ? THEN po.final_amount ELSE 0 END) AS returning,
                SUM(po.status = 3 AND po.delivered_at BETWEEN ? AND ?)                                   AS delivered_count,
                SUM(CASE WHEN po.status = 3 AND po.delivered_at BETWEEN ? AND ? THEN po.final_amount ELSE 0 END)      AS delivered,
                COALESCE(MAX(rmo.rmo_called), 0)                                                         AS rmo_called,
                ROUND(
                    SUM(po.status IN (4,5) AND po.returning_at BETWEEN ? AND ?) * 100.0
                    / NULLIF(
                        SUM(po.status = 3   AND po.delivered_at  BETWEEN ? AND ?)
                      + SUM(po.status IN (4,5) AND po.returning_at BETWEEN ? AND ?), 0),
                2)                                                                                        AS rts_rate
            ', [
                $start, $end,  // total_orders
                $start, $end,  // total_sales
                $start, $end,  // returning_count
                $start, $end,  // returning amount
                $start, $end,  // delivered_count
                $start, $end,  // delivered amount
                $start, $end,  // rts: numerator
                $start, $end,  // rts: denominator delivered
                $start, $end,  // rts: denominator returning
            ])
            ->get();

        $count = $rows->each(function ($row) use ($date, $type) {
            PancakeUserDailyReport::updateOrCreate(
                [
                    'workspace_id'    => $row->workspace_id,
                    'pancake_user_id' => $row->pancake_user_id,
                    'date'            => $date,
                    'type'            => $type,
                ],
                [
                    'total_orders' => (int)   $row->total_orders,
                    'total_sales'  => (float) $row->total_sales,
                    'returning'    => (float) $row->returning,
                    'delivered'    => (float) $row->delivered,
                    'rts_rate'     => (float) $row->rts_rate,
                    'rmo_called'   => (int)   $row->rmo_called,
                ]
            );
        })->count();

        $this->info("Synced {$count} CSR daily record(s) for {$date}.");

        return self::SUCCESS;
    }
}
