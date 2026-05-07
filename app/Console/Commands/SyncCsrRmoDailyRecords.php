<?php

namespace App\Console\Commands;

use App\Models\PancakeUserRmoDailyReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCsrRmoDailyRecords extends Command
{
    protected $signature = 'sync:csr-rmo-daily-records
                            {--date= : Target date in Y-m-d format (defaults to yesterday)}';

    protected $description = 'Aggregate per-CSR RMO call metrics for a given date and upsert into pancake_user_rmo_daily_reports.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))->toDateString()
            : CarbonImmutable::yesterday()->toDateString();

        $this->info("Syncing RMO CSR daily records for {$date}...");

        // total_called: pancake_order_for_delivery rows where status != 'PENDING'
        //   (matches the SyncCsrDailyRecords definition).
        // total_rmo_call_attempts: count of matching call_logs entries — same
        //   matching predicate as before, but counts every call instead of
        //   collapsing to 0/1 per delivery row.
        $rows = DB::table('pancake_order_for_delivery AS ofd')
            ->whereNotNull('ofd.assignee_id')
            ->where('ofd.delivery_date', $date)
            ->groupBy('ofd.workspace_id', 'ofd.assignee_id')
            ->selectRaw("
                ofd.workspace_id,
                ofd.assignee_id AS pancake_user_id,
                SUM(CASE WHEN ofd.status != 'PENDING' THEN 1 ELSE 0 END) AS total_called,
                COALESCE(SUM(
                    (SELECT COUNT(*)
                     FROM call_logs cl
                     WHERE cl.workspace_id = ofd.workspace_id
                       AND cl.user_id = ofd.assignee_id
                       AND cl.call_date = ofd.delivery_date
                       AND cl.phone_number IN (ofd.rider_phone, ofd.customer_phone))
                ), 0) AS total_rmo_call_attempts,
                COALESCE(SUM(ofd.customer_call_duration), 0) + COALESCE(SUM(ofd.rider_call_duration), 0) AS total_call_time
            ")
            ->get();

        $count = $rows->each(function ($row) use ($date) {
            PancakeUserRmoDailyReport::updateOrCreate(
                [
                    'workspace_id' => $row->workspace_id,
                    'pancake_user_id' => $row->pancake_user_id,
                    'date' => $date,
                ],
                [
                    'total_called' => (int) $row->total_called,
                    'total_call_time' => (int) $row->total_call_time,
                    'total_rmo_call_attempts' => (int) $row->total_rmo_call_attempts,
                ]
            );
        })->count();

        $this->info("Synced {$count} RMO CSR daily record(s) for {$date}.");

        return self::SUCCESS;
    }
}
