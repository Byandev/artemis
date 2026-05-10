<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Modules\MetaAds\Jobs\CaptureBudgetSnapshots;

class CaptureBudgetSnapshotsCommand extends Command
{
    protected $signature = 'metaads:capture-budgets {--date= : Snapshot date (YYYY-MM-DD, defaults to today)}';

    protected $description = 'Snapshot today\'s ad-set and campaign budgets from local DB so we have history Meta does not keep.';

    public function handle(): int
    {
        $date = $this->option('date');

        CaptureBudgetSnapshots::dispatch($date);

        $this->info('Dispatched budget-snapshot job'.($date ? " for {$date}" : ''));

        return self::SUCCESS;
    }
}
