<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\MetaAds\Jobs\SyncAds;
use Modules\MetaAds\Jobs\SyncAdSets;
use Modules\MetaAds\Jobs\SyncCampaigns;
use Modules\MetaAds\Jobs\SyncCreatives;
use Modules\MetaAds\Jobs\SyncInsights;
use Modules\MetaAds\Jobs\SyncMetaAdAccounts;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\User as MetaUser;

class SyncAllCommand extends Command
{
    protected $signature = 'metaads:sync-all
        {--insights-days=7 : Number of days back from today to sync insights for}
        {--skip-creatives : Skip the (heavy) creatives sync}';

    protected $description = 'Dispatch a full sync (ad accounts, campaigns, ad sets, ads, creatives, insights) for every connected MetaUser.';

    public function handle(): int
    {
        $metaUsers = MetaUser::all();
        $accounts = AdAccount::all();

        if ($metaUsers->isEmpty() && $accounts->isEmpty()) {
            $this->warn('Nothing to sync — no MetaUsers or AdAccounts.');

            return self::SUCCESS;
        }

        $jobs = 0;

        foreach ($metaUsers as $user) {
            SyncMetaAdAccounts::dispatch($user);
            $jobs++;
        }
        $this->info("Dispatched {$metaUsers->count()} ad-account syncs.");

        $perAccount = 0;
        foreach ($accounts as $account) {
            SyncCampaigns::dispatch($account);
            SyncAdSets::dispatch($account);
            SyncAds::dispatch($account);

            if (! $this->option('skip-creatives')) {
                SyncCreatives::dispatch($account);
            }

            $perAccount++;
        }
        $entityJobsPerAccount = $this->option('skip-creatives') ? 3 : 4;
        $jobs += $perAccount * $entityJobsPerAccount;
        $this->info("Dispatched {$entityJobsPerAccount} entity syncs × {$perAccount} accounts.");

        $days = (int) $this->option('insights-days');
        $until = Carbon::today();
        $insightJobs = 0;

        for ($i = 0; $i < $days; $i++) {
            $date = $until->copy()->subDays($i)->toDateString();
            foreach ($accounts as $account) {
                SyncInsights::dispatch($account, $date);
                $insightJobs++;
            }
        }
        $jobs += $insightJobs;
        $this->info("Dispatched {$insightJobs} insights syncs ({$days} days × {$accounts->count()} accounts).");

        $this->info("Total jobs queued: {$jobs}");

        return self::SUCCESS;
    }
}
