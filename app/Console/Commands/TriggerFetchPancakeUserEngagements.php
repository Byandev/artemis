<?php

namespace App\Console\Commands;

use App\Models\Page;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchPageUserEngagements;

class TriggerFetchPancakeUserEngagements extends Command
{
    protected $signature = 'trigger-fetch-pancake-user-engagements {--date= : Date to fetch (Y-m-d), defaults to yesterday}';

    protected $description = 'Dispatch jobs to record pancake user daily engagements per page.';

    public function handle(): void
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::yesterday();

        Page::whereNotNull('pancake_token')
            ->get()
            ->each(function (Page $page) use ($date) {
                dispatch(new FetchPageUserEngagements($page, $date->copy()))->onQueue('pancake');
            });
    }
}
