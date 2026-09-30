<?php

namespace Modules\MetaAds\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\MetaAds\Jobs\BackfillAdSetBudgets;
use Modules\MetaAds\Models\AdAccount;
use Throwable;

/**
 * Queue a past-daily-budget rebuild for one or every ad account.
 *
 * The work itself lives in {@see BackfillAdSetBudgets} — one job per ad
 * account, so a slow or rate-limited account neither blocks nor re-runs the
 * others. Dispatches are staggered rather than fired at once: a single account
 * can page through hundreds of activity-log requests, and accounts sharing a
 * Meta user token share that token's rate limit.
 *
 * Meta does not document how far back activities go, nor the exact shape of
 * `extra_data`. Run with --probe first against a real account: it stays inline
 * (so you actually see the output), writes nothing, and dumps raw payloads plus
 * the oldest event reachable.
 */
class BackfillAdSetBudgetHistoryCommand extends Command
{
    protected $signature = 'metaads:backfill-adset-budgets
        {ad_account? : AdAccount id (defaults to every account with active_sync)}
        {--days=90 : How many days back to reconstruct}
        {--probe : Run inline, write nothing, and dump raw activity payloads}
        {--dry-run : Reconstruct and report, but do not write any rows}
        {--max-pages=200 : Safety cap on activity-log pages fetched per account}
        {--delay=300 : Seconds to stagger each account job by}';

    protected $description = "Queue a rebuild of past ad-set daily budgets from Meta's activity log.";

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $maxPages = max(1, (int) $this->option('max-pages'));
        $delay = max(0, (int) $this->option('delay'));

        $accounts = $this->resolveAccounts();

        if ($accounts->isEmpty()) {
            $this->warn('No ad accounts found. Run metaads:sync-ad-accounts first.');

            return self::SUCCESS;
        }

        if ($this->option('probe')) {
            return $this->probe($accounts, $days, $maxPages);
        }

        foreach ($accounts as $index => $account) {
            // Stagger rather than fire at once: one account can be hundreds of
            // activity-log requests, and accounts sharing a Meta user token
            // share its rate limit.
            $offset = $index * $delay;

            BackfillAdSetBudgets::dispatch($account, $days, $maxPages, (bool) $this->option('dry-run'))
                ->onQueue('meta-ads')
                ->delay(now()->addSeconds($offset));

            $this->info("Queued backfill for AdAccount #{$account->id} ({$account->meta_account_id}) — starting in {$offset}s");
        }

        $this->line('Each job logs what it wrote for its account when it finishes.');

        return self::SUCCESS;
    }

    /**
     * --probe: report what the activity log actually gives us, so the shape of
     * `extra_data` and the real retention window can be settled from live data
     * instead of guessed from the docs. Runs inline, because the whole point is
     * to read the output.
     *
     * @param  Collection<int, AdAccount>  $accounts
     */
    private function probe(Collection $accounts, int $days, int $maxPages): int
    {
        $until = Carbon::now();
        $since = $until->copy()->subDays($days)->startOfDay();

        foreach ($accounts as $account) {
            $this->info("Ad account #{$account->id} ({$account->meta_account_id}) — {$since->toDateString()} → {$until->toDateString()}");

            try {
                $fetched = (new BackfillAdSetBudgets($account, $days, $maxPages))
                    ->fetchBudgetActivities($since, $until);
            } catch (Throwable $e) {
                $this->error("  Failed to read activities: {$e->getMessage()}");

                continue;
            }

            $this->probeReport($fetched);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{events: array<int, array<string, mixed>>, truncated: bool, oldest: int|null}  $fetched
     */
    private function probeReport(array $fetched): void
    {
        $events = $fetched['events'];

        if ($events === []) {
            $this->warn('  No update_ad_set_budget events returned for this window.');

            return;
        }

        if ($fetched['truncated']) {
            $this->warn('  Page cap reached — the window goes back further than this.');
        }

        $times = array_map(
            fn (array $e) => Carbon::parse($e['event_time'])->toDateTimeString(),
            $events,
        );
        sort($times);

        $this->line('  Budget events: '.count($events));
        $this->line('  Oldest event:  '.$times[0].'  ← how far back Meta actually goes');
        $this->line('  Newest event:  '.end($times));
        $this->line('  Sample payloads:');

        foreach (array_slice($events, 0, 5) as $event) {
            $this->line('    '.json_encode($event, JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * @return Collection<int, AdAccount>
     */
    private function resolveAccounts(): Collection
    {
        $query = AdAccount::query();

        if ($id = $this->argument('ad_account')) {
            $query->whereKey($id);
        } else {
            $query->where('active_sync', true);
        }

        return $query->get();
    }
}
