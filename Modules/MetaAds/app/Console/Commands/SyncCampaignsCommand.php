<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Modules\MetaAds\Jobs\SyncCampaigns;
use Modules\MetaAds\Models\AdAccount;

class SyncCampaignsCommand extends Command
{
    protected $signature = 'metaads:sync-campaigns {ad_account? : AdAccount id (defaults to all)}';

    protected $description = 'Sync campaigns from Meta Graph API for one or all ad accounts';

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
            $this->info("Dispatching campaigns sync for AdAccount #{$account->id} ({$account->meta_account_id})");

            SyncCampaigns::dispatch($account)->delay(now()->addSeconds($index * 10));
        }

        return self::SUCCESS;
    }
}
