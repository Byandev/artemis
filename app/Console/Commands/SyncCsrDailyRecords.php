<?php

namespace App\Console\Commands;

use App\Jobs\SyncCsrDailyRecord;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SyncCsrDailyRecords extends Command
{
    protected $signature = 'sync:csr-daily-records
                            {--date= : Single target date in Y-m-d format. If omitted, the last --days days are dispatched (one job per day).}
                            {--days=7 : Number of trailing days to backfill when --date is not provided. Defaults to 7.}
                            {--type=POS : Record type label}';

    protected $description = 'Dispatch SyncCsrDailyRecord jobs to aggregate per-CSR order metrics. Backfills the last 7 days by default.';

    public function handle(): int
    {
        $type = (string) $this->option('type');

        if ($this->option('date')) {
            $date = CarbonImmutable::parse($this->option('date'))->toDateString();
            SyncCsrDailyRecord::dispatch($date, $type);
            $this->info("Dispatched SyncCsrDailyRecord for {$date} (type={$type}).");

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $start = CarbonImmutable::yesterday();

        for ($i = 0; $i < $days; $i++) {
            $date = $start->subDays($i)->toDateString();
            SyncCsrDailyRecord::dispatch($date, $type);
        }

        $this->info("Dispatched {$days} SyncCsrDailyRecord job(s) (type={$type}) covering {$start->subDays($days - 1)->toDateString()} → {$start->toDateString()}.");

        return self::SUCCESS;
    }
}
