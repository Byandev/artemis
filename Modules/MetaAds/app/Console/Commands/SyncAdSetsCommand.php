<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Modules\MetaAds\Jobs\SyncAdSets;
use Modules\MetaAds\Models\AdAccount;

class SyncAdSetsCommand extends Command
{
    protected $signature = 'meta-ads:sync-ad-sets {ad_account? : AdAccount id (defaults to all)}';

    protected $description = 'Sync ad sets from Meta Graph API for one or all ad accounts';

    public function handle(): int
    {
        $query = AdAccount::query();

        if ($id = $this->argument('ad_account')) {
            $query->whereKey($id);
        } else {
            $query->where('active_sync', true);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('No AdAccounts found. Run metaads:sync-ad-accounts first.');

            return self::SUCCESS;
        }

        foreach ($accounts as $index => $account) {
            $this->info("Dispatching ad sets sync for AdAccount #{$account->id} ({$account->meta_account_id})");

            SyncAdSets::dispatch($account)->onQueue('meta-ads')->delay(now()->addSeconds($index * 10));
        }

        return self::SUCCESS;
    }
}
