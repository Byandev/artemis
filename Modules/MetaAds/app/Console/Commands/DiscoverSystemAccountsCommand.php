<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Modules\MetaAds\Jobs\DiscoverSystemUserAdAccounts;

class DiscoverSystemAccountsCommand extends Command
{
    protected $signature = 'metaads:discover-system-accounts';

    protected $description = 'Flag locally-known ad accounts that are also visible to the BM system-user token as uses_system_user=true.';

    public function handle(): int
    {
        if (! config('metaads.system_user_token')) {
            $this->error('META_ADS_SYSTEM_USER_TOKEN is not set.');

            return self::FAILURE;
        }

        DiscoverSystemUserAdAccounts::dispatch();
        $this->info('Discovery job queued.');

        return self::SUCCESS;
    }
}
