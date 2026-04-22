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

        $rows = DB::table('pancake_order_for_delivery')
            ->whereNotNull('caller_id')
            ->where('delivery_date', $date)
            ->groupBy('workspace_id', 'caller_id')
            ->selectRaw("
                workspace_id,
                caller_id AS pancake_user_id,
                SUM(CASE WHEN status <> 'PENDING' THEN 1 ELSE 0 END) AS total_called,
                COALESCE(SUM(customer_call_duration), 0) + COALESCE(SUM(rider_call_duration), 0) AS total_call_time
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
                ]
            );
        })->count();

        $this->info("Synced {$count} RMO CSR daily record(s) for {$date}.");

        return self::SUCCESS;
    }
}
