<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Modules\MetaAds\Jobs\SyncAdAccountPeople;
use Modules\MetaAds\Models\AdAccount;

class SyncAdAccountPeopleCommand extends Command
{
    protected $signature = 'metaads:sync-ad-account-people
        {ad_account? : AdAccount id (defaults to all active-sync accounts)}
        {--all : Include accounts with active_sync off}';

    protected $description = 'Sync the people who have access to each ad account (Meta Business Manager People list)';

    public function handle(): int
    {
        $query = AdAccount::query();

        if ($id = $this->argument('ad_account')) {
            $query->whereKey($id);
        } elseif (! $this->option('all')) {
            $query->where('active_sync', true);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('No ad accounts found.');

            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            SyncAdAccountPeople::dispatch($account)->onQueue('meta-ads');
        }

        $this->info("Dispatched people sync for {$accounts->count()} ad account(s).");

        return self::SUCCESS;
    }
}
