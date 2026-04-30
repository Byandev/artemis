<?php

namespace App\Console\Commands;

use App\Models\Page;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchPageCustomersDaily;

class BackfillPageCustomers extends Command
{
    protected $signature = 'backfill-page-customers
        {--since=2026-01-01 : Start date (Y-m-d, Asia/Manila). Defaults to 2026-01-01.}
        {--until= : End date (Y-m-d, Asia/Manila). Defaults to yesterday.}';

    protected $description = 'Backfill daily customer counts (new/all/old) per page over a date range.';

    public function handle(): int
    {
        $since = CarbonImmutable::parse($this->option('since'), 'Asia/Manila')->startOfDay();
        $until = $this->option('until')
            ? CarbonImmutable::parse($this->option('until'), 'Asia/Manila')->startOfDay()
            : CarbonImmutable::yesterday('Asia/Manila');

        if ($since->greaterThan($until)) {
            $this->error("--since ({$since->toDateString()}) is after --until ({$until->toDateString()}).");
            return self::FAILURE;
        }

        $pages = Page::whereNotNull('pancake_token')->orderBy('id')->get();

        $dispatched = 0;
        foreach (CarbonPeriod::create($since, $until) as $day) {
            foreach ($pages as $page) {
                dispatch(new FetchPageCustomersDaily($page, $day->toDateString()))->onQueue('pancake');
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} jobs ({$pages->count()} pages × dates {$since->toDateString()} to {$until->toDateString()}).");

        return self::SUCCESS;
    }
}
