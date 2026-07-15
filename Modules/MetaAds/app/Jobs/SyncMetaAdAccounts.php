<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Modules\MetaAds\Jobs\Concerns\HandlesMetaSyncErrors;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\SyncRun;
use Modules\MetaAds\Models\User as MetaUser;
use Throwable;

class SyncMetaAdAccounts implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    /**
     * @param  bool  $cascade  When true, after the accounts are fetched, kick off
     *                         a full entity + insights backfill for each one.
     * @param  int  $insightsDays  How many days back from today to backfill insights
     *                             for during the cascade (e.g. 30 for "last month").
     */
    public function __construct(
        public MetaUser $metaUser,
        public bool $cascade = false,
        public int $insightsDays = 7,
    ) {}

    public function handle(): void
    {
        $run = SyncRun::start(
            entityType: SyncRun::ENTITY_AD_ACCOUNTS,
            scopeType: MetaUser::class,
            scopeId: $this->metaUser->id,
        );

        try {
            $client = $this->metaUser->graphClient();

            $count = 0;
            $accountIds = [];
            $newAccountIds = [];

            $fields = 'id,account_id,name,currency,timezone_name,business_country_code,account_status,business';

            foreach ($client->paginated('me/adaccounts', ['fields' => $fields]) as $row) {
                // Meta returns id with `act_` prefix; account_id is the bare numeric form.
                $accountId = $row['account_id'] ?? preg_replace('/^act_/', '', (string) $row['id']);

                $account = AdAccount::updateOrCreate(
                    ['id' => $accountId],
                    [
                        'name' => $row['name'] ?? $accountId,
                        'currency' => $row['currency'] ?? null,
                        'timezone_name' => $row['timezone_name'] ?? null,
                        'country_code' => $row['business_country_code'] ?? null,
                        'account_status' => isset($row['account_status']) ? (int) $row['account_status'] : null,
                        'business_id' => $row['business']['id'] ?? null,
                        'business_name' => $row['business']['name'] ?? null,
                        'last_synced_at' => Carbon::now(),
                    ],
                );

                if ($account->wasRecentlyCreated) {
                    $newAccountIds[] = $accountId;
                }

                $accountIds[$accountId] = ['permitted_tasks' => null];
                $count++;
            }

            $this->metaUser->adAccounts()->sync($accountIds);
            $this->metaUser->forceFill(['last_synced_at' => Carbon::now()])->save();

            $run->succeed($count, ['account_count' => $count]);

            // Backfill only the accounts that did not exist before this run, so a
            // reconnect or routine re-sync never re-pulls history we already have.
            if ($this->cascade && $newAccountIds !== []) {
                $this->cascadeBackfill($newAccountIds);
            }
        } catch (Throwable $e) {
            $this->handleSyncError($run, $e);
        }
    }

    /**
     * Fan out a full sync (campaigns → ad sets → ads → creatives → insights) for
     * the given just-created accounts. Mirrors the per-account chaining in
     * metaads:sync-all so each account's jobs run strictly sequentially.
     *
     * @param  array<int, string>  $accountIds  Accounts created during this run.
     */
    private function cascadeBackfill(array $accountIds): void
    {
        $this->metaUser->adAccounts()
            ->whereIn('meta_ads_accounts.id', $accountIds)
            ->where('active_sync', true)
            ->get()
            ->each(function (AdAccount $account, int $index) {
                $chain = [
                    new SyncCampaigns($account),
                    new SyncAdSets($account),
                    new SyncAds($account),
                    new SyncCreatives($account),
                    // Only backfill insights if, once campaigns are synced, the
                    // account actually has at least one campaign.
                    new BackfillInsightsIfActive($account, $this->insightsDays),
                ];

                // Stagger each account's chain by 3 minutes so we don't slam the
                // Graph API (and blow the rate cap) when several accounts connect at once.
                Bus::chain($chain)
                    ->onQueue('meta-ads')
                    ->delay(Carbon::now()->addMinutes($index * 3))
                    ->dispatch();
            });
    }
}
