<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Modules\MetaAds\Jobs\SyncCreatives;
use Modules\MetaAds\Models\AdAccount;

class SyncCreativesCommand extends Command
{
    protected $signature = 'metaads:sync-creatives {ad_account? : AdAccount id (defaults to all)}';

    protected $description = 'Sync ad creatives from Meta Graph API for one or all ad accounts';

    public function handle(): int
    {
        $query = AdAccount::query();

        if ($id = $this->argument('ad_account')) {
            $query->whereKey($id);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('No AdAccounts found. Run metaads:sync-ad-accounts first.');

            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            $this->info("Dispatching creatives sync for AdAccount #{$account->id}");

            SyncCreatives::dispatch($account);
        }

        return self::SUCCESS;
    }
}
