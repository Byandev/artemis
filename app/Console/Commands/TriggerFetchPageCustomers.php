<?php

namespace App\Console\Commands;

use App\Models\Page;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchPageCustomersDaily;

class TriggerFetchPageCustomers extends Command
{
    protected $signature = 'trigger-fetch-page-customers {date? : Date in Y-m-d (Asia/Manila). Defaults to yesterday.}';

    protected $description = 'Dispatch a job per page to fetch the daily customer count from the Pancake public API.';

    public function handle(): int
    {
        $date = $this->argument('date')
            ?? CarbonImmutable::yesterday('Asia/Manila')->toDateString();

        Page::whereNotNull('pancake_token')
            ->orderBy('id')
            ->get()
            ->each(function (Page $page) use ($date) {
                dispatch(new FetchPageCustomersDaily($page, $date))->onQueue('pancake');
            });

        return self::SUCCESS;
    }
}
