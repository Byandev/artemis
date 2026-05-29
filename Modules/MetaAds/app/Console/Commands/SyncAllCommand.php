<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
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
        {--skip-creatives : Skip the (heavy) creatives sync}
        {--limited : Limited-tier safe mode: 1 insights day, skip creatives, chained per account}';

    protected $description = 'Dispatch a full sync (ad accounts, campaigns, ad sets, ads, creatives, insights) for every connected MetaUser.';

    public function handle(): int
    {
        $metaUsers = MetaUser::all();
        $accounts = AdAccount::where('active_sync', true)->get();

        if ($metaUsers->isEmpty() && $accounts->isEmpty()) {
            $this->warn('Nothing to sync — no MetaUsers or AdAccounts.');

            return self::SUCCESS;
        }

        $limited = (bool) $this->option('limited');
        $skipCreatives = $limited || $this->option('skip-creatives');
        $insightsDays = $limited ? 1 : (int) $this->option('insights-days');

        $jobs = 0;

        foreach ($metaUsers as $user) {
            SyncMetaAdAccounts::dispatch($user);
            $jobs++;
        }
        $this->info("Dispatched {$metaUsers->count()} ad-account syncs.");

        // Chain entity + insights jobs per ad account so that within one
        // account they run strictly sequentially — the next job only fires
        // when the previous one finishes. Pairs with SerializesPerAdAccount
        // (which is now per-MetaUser) to keep us strictly under the dev-tier
        // 60-score-per-300s cap.
        $until = Carbon::today();
        foreach ($accounts as $account) {
            $chain = [
                new SyncCampaigns($account),
                new SyncAdSets($account),
                new SyncAds($account),
            ];

            if (! $skipCreatives) {
                $chain[] = new SyncCreatives($account);
            }

            for ($i = 0; $i < $insightsDays; $i++) {
                $date = $until->copy()->subDays($i)->toDateString();
                $chain[] = new SyncInsights($account, $date);
            }

            Bus::chain($chain)->dispatch();
            $jobs += count($chain);
        }

        $this->info("Chained syncs queued — entity({$accounts->count()}) + insights({$insightsDays} days)"
            .($skipCreatives ? ' (creatives skipped)' : '')
            .($limited ? ' [limited-tier mode]' : ''));

        $this->info("Total jobs queued: {$jobs}");

        return self::SUCCESS;
    }
}
