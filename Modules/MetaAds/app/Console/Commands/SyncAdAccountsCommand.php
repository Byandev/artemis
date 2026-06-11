<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Modules\MetaAds\Jobs\SyncMetaAdAccounts;
use Modules\MetaAds\Models\User as MetaUser;

class SyncAdAccountsCommand extends Command
{
    protected $signature = 'metaads:sync-ad-accounts {meta_user? : MetaUser id (defaults to all)}';

    protected $description = 'Sync ad accounts from Meta Graph API for one or all connected MetaUsers';

    public function handle(): int
    {
        $query = MetaUser::query();

        if ($id = $this->argument('meta_user')) {
            $query->whereKey($id);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('No MetaUsers found.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $this->info("Dispatching sync for MetaUser #{$user->id} ({$user->name})");

            SyncMetaAdAccounts::dispatch($user)->onQueue('meta-ads');
        }

        return self::SUCCESS;
    }
}
