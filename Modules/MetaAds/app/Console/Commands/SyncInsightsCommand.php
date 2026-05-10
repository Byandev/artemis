<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Jobs\SyncInsights;
use Modules\MetaAds\Models\AdAccount;

class SyncInsightsCommand extends Command
{
    protected $signature = 'metaads:sync-insights
        {ad_account? : AdAccount id (defaults to all)}
        {--days=7 : Number of days back from --until (default 7)}
        {--since= : Override start date (YYYY-MM-DD)}
        {--until= : End date (YYYY-MM-DD, defaults to today)}';

    protected $description = 'Dispatch one SyncInsights job per (AdAccount, date) over the given window. Defaults to last 7 days.';

    public function handle(): int
    {
        $until = Carbon::parse($this->option('until') ?: 'today');

        $since = $this->option('since')
            ? Carbon::parse($this->option('since'))
            : $until->copy()->subDays((int) $this->option('days') - 1);

        $query = AdAccount::query();

        if ($id = $this->argument('ad_account')) {
            $query->whereKey($id);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('No AdAccounts found. Run metaads:sync-ad-accounts first.');

            return self::SUCCESS;
        }

        $dates = [];
        for ($d = $since->copy(); $d->lte($until); $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        $totalJobs = $accounts->count() * count($dates);
        $this->info("Dispatching {$totalJobs} jobs ({$accounts->count()} accounts × ".count($dates)." days: {$since->toDateString()} → {$until->toDateString()})");

        foreach ($accounts as $account) {
            foreach ($dates as $date) {
                SyncInsights::dispatch($account, $date);
            }
        }

        return self::SUCCESS;
    }
}
