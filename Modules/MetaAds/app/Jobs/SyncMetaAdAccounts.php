<?php

namespace Modules\MetaAds\Jobs;

use App\Services\DiscordNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\MetaAds\Jobs\Concerns\HandlesMetaSyncErrors;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\SyncRun;
use Modules\MetaAds\Models\User as MetaUser;
use Throwable;

class SyncMetaAdAccounts implements ShouldQueue
{
    use Dispatchable, HandlesMetaSyncErrors, InteractsWithQueue, Queueable, SerializesModels;

    /** Meta's account_status for a healthy, spend-capable account. */
    private const STATUS_ACTIVE = 1;

    /** Meta's account_status codes, mirroring the badge on the ad-accounts page. */
    private const STATUS_LABELS = [
        2 => 'Disabled',
        3 => 'Unsettled',
        7 => 'Pending Risk Review',
        8 => 'Pending Settlement',
        9 => 'In Grace Period',
        100 => 'Pending Closure',
        101 => 'Closed',
    ];

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

            // `tasks` = what THIS token's user may do on the account. It's the only
            // permission signal Meta still gives for accounts outside a business
            // portfolio, so it backs the People column there (see SyncAdAccountPeople).
            $fields = 'id,account_id,name,currency,timezone_name,business_country_code,account_status,business,tasks';

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

                $tasks = array_values(array_filter((array) ($row['tasks'] ?? [])));

                // sync() writes pivot values straight through, so encode here —
                // the column is json and there's no cast on the pivot.
                $accountIds[$accountId] = [
                    'permitted_tasks' => $tasks ? json_encode($tasks) : null,
                ];
                $count++;
            }

            $this->metaUser->adAccounts()->sync($accountIds);
            $this->metaUser->forceFill(['last_synced_at' => Carbon::now()])->save();

            $run->succeed($count, ['account_count' => $count]);

            // An account we're still actively syncing but that Meta no longer
            // reports as active can't spend — flag it so someone can fix the
            // billing / review issue instead of finding out from flat numbers.
            $this->notifyInactiveAccounts(array_keys($accountIds));

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
     * Post a Discord alert for accounts that are still flagged active_sync but
     * whose Meta account_status is anything other than active (disabled,
     * unsettled, in review, closed, ...). Batched into one message per run so a
     * user with several bad accounts doesn't produce a burst of pings.
     *
     * @param  array<int, string>  $accountIds  Accounts seen during this run.
     */
    private function notifyInactiveAccounts(array $accountIds): void
    {
        if ($accountIds === []) {
            return;
        }

        $accounts = AdAccount::query()
            ->whereIn('id', $accountIds)
            ->where('active_sync', true)
            // Null means Meta didn't report a status this run — not a problem
            // signal, so only alert on a status we actually know is not active.
            ->whereNotNull('account_status')
            ->where('account_status', '!=', self::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'account_status', 'business_name']);

        if ($accounts->isEmpty()) {
            return;
        }

        $lines = $accounts
            ->map(fn (AdAccount $account) => sprintf(
                '• **%s** (`act_%s`)%s — %s',
                $account->name,
                $account->id,
                $account->business_name ? ' · '.$account->business_name : '',
                self::STATUS_LABELS[$account->account_status] ?? "Status {$account->account_status}",
            ))
            ->implode("\n");

        $description = "Connected via **{$this->metaUser->name}** (#{$this->metaUser->id}). "
            ."These accounts still have sync turned on but Meta doesn't report them as active:\n\n{$lines}";

        try {
            app(DiscordNotifier::class)->send(
                '⚠️ Theres a problem with this ad accounts',
                [
                    'title' => $accounts->count().' ad account(s) need attention',
                    // Discord caps embed descriptions at 4096 characters.
                    'description' => Str::limit($description, 4000),
                    'color' => 0xE67E22,
                    'footer' => ['text' => 'SyncMetaAdAccounts'],
                ],
                // Meta ads alerts go to their own channel; null falls back to
                // the app-wide DISCORD_WEBHOOK_URL inside the notifier.
                config('services.discord.meta_ads_webhook_url'),
            );
        } catch (Throwable $e) {
            // A failed ping must not fail a sync that already succeeded.
            Log::warning('Failed to send inactive ad account alert to Discord', [
                'exception' => $e,
                'meta_user_id' => $this->metaUser->id,
                'account_ids' => $accounts->pluck('id')->all(),
            ]);
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
