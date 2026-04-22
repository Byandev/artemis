<?php

namespace App\Console\Commands;

use App\Models\PancakeUserPosDailyReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCsrDailyRecords extends Command
{
    protected $signature = 'sync:csr-daily-records
                            {--date= : Target date in Y-m-d format (defaults to yesterday)}';

    protected $description = 'Aggregate per-CSR POS order metrics for a given date and upsert into pancake_user_pos_daily_reports.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))->toDateString()
            : CarbonImmutable::yesterday()->toDateString();

        $start = $date.' 00:00:00';
        $end = $date.' 23:59:59';

        $this->info("Syncing POS CSR daily records for {$date}...");

        $rows = DB::table('pancake_orders as po')
            ->join('pancake_users as pu', 'pu.id', '=', 'po.confirmed_by')
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
                ROUND(
                    SUM(po.status IN (4,5) AND po.returning_at BETWEEN ? AND ?) * 100.0
                    / NULLIF(
                        SUM(po.status = 3   AND po.delivered_at  BETWEEN ? AND ?)
                      + SUM(po.status IN (4,5) AND po.returning_at BETWEEN ? AND ?), 0),
                2)                                                                                        AS rts_rate
            ', [
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
            ])
            ->get();

        $count = $rows->each(function ($row) use ($date) {
            PancakeUserPosDailyReport::updateOrCreate(
                [
                    'workspace_id' => $row->workspace_id,
                    'pancake_user_id' => $row->pancake_user_id,
                    'date' => $date,
                ],
                [
                    'total_orders' => (int) $row->total_orders,
                    'total_sales' => (float) $row->total_sales,
                    'returning' => (float) $row->returning,
                    'delivered' => (float) $row->delivered,
                    'rts_rate' => (float) $row->rts_rate,
                ]
            );
        })->count();

        $this->info("Synced {$count} POS CSR daily record(s) for {$date}.");

        return self::SUCCESS;
    }
}
