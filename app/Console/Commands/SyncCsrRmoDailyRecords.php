<?php

namespace App\Console\Commands;

use App\Jobs\SyncCsrRmoDailyRecord;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SyncCsrRmoDailyRecords extends Command
{
    protected $signature = 'sync:csr-rmo-daily-records
                            {--date= : Single target date in Y-m-d format. If omitted, the last --days days are dispatched (one job per day).}
                            {--days=7 : Number of trailing days to backfill when --date is not provided. Defaults to 7.}';

    protected $description = 'Dispatch SyncCsrRmoDailyRecord jobs to aggregate per-CSR RMO call metrics. Backfills the last 7 days by default.';

    public function handle(): int
    {
        if ($this->option('date')) {
            $date = CarbonImmutable::parse($this->option('date'))->toDateString();
            SyncCsrRmoDailyRecord::dispatch($date);
            $this->info("Dispatched SyncCsrRmoDailyRecord for {$date}.");

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $start = CarbonImmutable::yesterday();

        for ($i = 0; $i < $days; $i++) {
            $date = $start->subDays($i)->toDateString();
            SyncCsrRmoDailyRecord::dispatch($date);
        }

        $this->info("Dispatched {$days} SyncCsrRmoDailyRecord job(s) covering {$start->subDays($days - 1)->toDateString()} → {$start->toDateString()}.");

        return self::SUCCESS;
    }
}
