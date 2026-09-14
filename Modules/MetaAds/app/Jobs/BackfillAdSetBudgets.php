<?php

namespace Modules\MetaAds\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MetaAds\Exceptions\MetaGraphException;
use Modules\MetaAds\Jobs\Concerns\SerializesPerAdAccount;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\BudgetSnapshot;

/**
 * Rebuild one ad account's past daily-budget history from Meta's activity log.
 *
 * `metaads:capture-budgets` only records what it sees from the day it starts
 * running, and meta_ads_sets is overwritten in place by every sync, so dates
 * before that are simply gone locally. Meta keeps a change log per ad account
 * (`act_X/activities`), and every budget edit lands there as an
 * `update_ad_set_budget` event carrying the old and new value. Walking those
 * events backwards from the ad set's *current* budget reconstructs what the
 * budget was on each past date.
 *
 * Two things about that reconstruction are worth being honest about:
 *
 *  - It is inference, not observation. Rows are written with source=backfill and
 *    never overwrite a `capture` row, so live data always wins.
 *  - An ad set with no events in the window is assumed to have held its current
 *    budget the whole time. That is usually right and never verifiable.
 *
 * One job per ad account, so a slow or rate-limited account neither blocks nor
 * re-runs the others. SerializesPerAdAccount keeps accounts that share a Meta
 * user token from spending that token's quota concurrently.
 */
class BackfillAdSetBudgets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SerializesPerAdAccount;

    /** Paging a busy account through months of activity is slow. */
    public int $timeout = 1800;

    /** Generous, because a rate-limit release burns an attempt. */
    public int $tries = 5;

    /** Meta's event for an ad-set budget edit. */
    private const EVENT_BUDGET_UPDATE = 'update_ad_set_budget';

    public function __construct(
        public AdAccount $adAccount,
        public int $days = 90,
        public int $maxPages = 200,
        public bool $dryRun = false,
    ) {}

    public function handle(): void
    {
        $until = Carbon::now();
        $since = $until->copy()->subDays(max(1, $this->days))->startOfDay();

        try {
            $fetched = $this->fetchBudgetActivities($since, $until);
        } catch (MetaGraphException $e) {
            // Backfill is never urgent — when Meta pushes back, wait it out
            // rather than failing the account and losing the window.
            if ($e->isRateLimited()) {
                $backoff = $e->resetSeconds ?? min(1800, 300 * (2 ** max(0, $this->attempts() - 1)));

                Log::warning('Ad-set budget backfill rate limited, will retry', [
                    'ad_account_id' => $this->adAccount->id,
                    'backoff_seconds' => $backoff,
                    'attempt' => $this->attempts(),
                ]);

                $this->release($backoff);

                return;
            }

            throw $e;
        }

        $from = $since;

        // The fetch stopped early, so edits older than what we got were never
        // seen. Reconstructing past that point would invent budgets, so pull
        // the window forward to where the data actually starts.
        if ($fetched['truncated'] && $fetched['oldest'] !== null) {
            // Explicit timezone: Carbon::createFromTimestamp() defaults to UTC,
            // and every other date here is in the app timezone — left alone the
            // window lands hours off and loses a day.
            $from = Carbon::createFromTimestamp($fetched['oldest'], config('app.timezone'))->startOfDay();

            Log::warning('Ad-set budget backfill hit the page cap', [
                'ad_account_id' => $this->adAccount->id,
                'max_pages' => $this->maxPages,
                'reconstructed_from' => $from->toDateString(),
            ]);
        }

        $summary = $this->backfillAccount($fetched['events'], $from);

        Log::info('Ad-set budget backfill finished', array_merge($summary, [
            'ad_account_id' => $this->adAccount->id,
            'from' => $from->toDateString(),
            'to' => $until->toDateString(),
            'dry_run' => $this->dryRun,
        ]));
    }

    /**
     * Page the account's activity log and keep only ad-set budget edits.
     *
     * A busy account logs a lot — one real account showed ~50 ad-set events a
     * day, of which only a third are budget edits — so paging is capped. Meta
     * returns newest first, so hitting the cap means the *oldest* edits are
     * missing; `oldest` reports how far back the fetch actually got so the
     * caller can refuse to reconstruct past that point.
     *
     * Public so `--probe` can inspect a live payload without writing anything.
     *
     * @return array{events: array<int, array<string, mixed>>, truncated: bool, oldest: int|null}
     */
    public function fetchBudgetActivities(Carbon $since, Carbon $until): array
    {
        $client = $this->adAccount->graphClient();
        $maxPages = max(1, $this->maxPages);

        $events = [];
        $after = null;
        $hasNext = false;
        $pages = 0;
        $oldest = null;

        do {
            $query = [
                'fields' => 'event_type,event_time,object_id,object_name,extra_data,actor_name',
                'since' => $since->getTimestamp(),
                'until' => $until->getTimestamp(),
                'category' => 'AD_SET',
            ];

            if ($after !== null) {
                $query['after'] = $after;
            }

            $page = $client->getPage("{$this->adAccount->graphAccountId()}/activities", $query);

            foreach ($page['data'] ?? [] as $row) {
                if (isset($row['event_time'])) {
                    $at = Carbon::parse($row['event_time'])->getTimestamp();
                    $oldest = $oldest === null ? $at : min($oldest, $at);
                }

                if (($row['event_type'] ?? null) === self::EVENT_BUDGET_UPDATE) {
                    $events[] = $row;
                }
            }

            $after = $page['paging']['cursors']['after'] ?? null;
            $pages++;

            // The cursor is echoed back even on the last page; `next` is what
            // actually says there is more to fetch.
            $hasNext = isset($page['paging']['next']);
        } while ($hasNext && $after !== null && $pages < $maxPages);

        return [
            'events' => $events,
            'truncated' => $hasNext && $pages >= $maxPages,
            'oldest' => $oldest,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array{written: int, ad_sets: int, skipped: int}
     */
    private function backfillAccount(array $events, Carbon $since): array
    {
        // Group the account's budget edits by the ad set they touched.
        $eventsByAdSet = [];

        foreach ($events as $event) {
            $adSetId = (int) ($event['object_id'] ?? 0);

            if ($adSetId === 0) {
                continue;
            }

            $eventsByAdSet[$adSetId][] = $event;
        }

        $written = 0;
        $skipped = 0;
        $adSetCount = 0;

        // Only ABO ad sets carry a daily budget of their own; under CBO it lives
        // on the campaign and there is nothing per-ad-set to reconstruct.
        AdSet::query()
            ->where('meta_ads_account_id', $this->adAccount->id)
            ->whereNotNull('daily_budget')
            ->orderBy('id')
            ->chunkById(500, function ($adSets) use (&$written, &$skipped, &$adSetCount, $eventsByAdSet, $since) {
                foreach ($adSets as $adSet) {
                    $rows = $this->reconstruct($adSet, $eventsByAdSet[$adSet->id] ?? [], $since);

                    if ($rows === null) {
                        $skipped++;

                        continue;
                    }

                    $adSetCount++;

                    if ($rows !== [] && ! $this->dryRun) {
                        // insertOrIgnore against the (entity_type, entity_id,
                        // date) primary key, so a `capture` row already on that
                        // date is never replaced by a reconstructed one.
                        DB::table('meta_ads_budget_snapshots')->insertOrIgnore($rows);
                    }

                    $written += count($rows);
                }
            });

        return ['written' => $written, 'ad_sets' => $adSetCount, 'skipped' => $skipped];
    }

    /**
     * Reconstruct one ad set's daily budget for every date in the window.
     *
     * Returns null when the ad set's events can't be trusted, so the caller can
     * skip it rather than write money we can't stand behind.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>|null
     */
    private function reconstruct(AdSet $adSet, array $events, Carbon $since): ?array
    {
        $current = (float) $adSet->daily_budget;

        $changes = [];

        foreach ($events as $event) {
            $parsed = $this->parseBudgetEvent($event);

            if ($parsed !== null) {
                $changes[] = $parsed;
            }
        }

        // Oldest first, so "the value in effect at time T" is the last change at
        // or before T.
        usort($changes, fn (array $a, array $b) => $a['at'] <=> $b['at']);

        $scale = $this->resolveScale($adSet, $changes, $current);

        if ($scale === null) {
            return null;
        }

        $rows = [];
        $now = Carbon::now();

        // History starts when the ad set started running, not when it was
        // created: a budget on a date the ad set wasn't delivering yet never
        // bought anything, and would skew any spend-vs-budget comparison on
        // that date. start_time is nullable, so fall back to created_time.
        $startedOn = ($adSet->start_time ?? $adSet->created_time)?->copy()->startOfDay();

        // Today belongs to the live capture — it observes the real value, we'd
        // only be guessing at it.
        $date = $since->copy()->startOfDay();
        $lastDate = Carbon::today()->subDay();

        for (; $date->lte($lastDate); $date->addDay()) {
            // Nothing to say about days before the ad set started running.
            if ($startedOn !== null && $date->lt($startedOn)) {
                continue;
            }

            $budget = $this->budgetAt($date->copy()->endOfDay(), $changes, $current, $scale);

            if ($budget === null) {
                continue;
            }

            $rows[] = [
                'entity_type' => BudgetSnapshot::ENTITY_AD_SET,
                'entity_id' => $adSet->id,
                'date' => $date->toDateString(),
                'daily_budget' => $budget,
                'lifetime_budget' => null,
                // Neither past status nor past bid strategy is in the budget
                // events, so leave them unknown rather than stamping today's
                // values onto old dates.
                'bid_strategy' => null,
                'status' => null,
                'effective_status' => null,
                'source' => BudgetSnapshot::SOURCE_BACKFILL,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * The budget in effect at a moment: the value set by the most recent change
     * at or before it, or — if every change came later — the value that change
     * replaced. With no changes at all the budget never moved, so it's current.
     *
     * @param  array<int, array{at: int, old: float, new: float}>  $changes
     */
    private function budgetAt(Carbon $moment, array $changes, float $current, float $scale): ?float
    {
        if ($changes === []) {
            return $current;
        }

        $timestamp = $moment->getTimestamp();
        $value = null;

        foreach ($changes as $change) {
            if ($change['at'] <= $timestamp) {
                $value = $change['new'];
            } else {
                // Changes are oldest-first, so the first one after this moment
                // holds the value that was in effect up to it.
                $value ??= $change['old'];

                break;
            }
        }

        return $value === null ? null : round($value / $scale, 2);
    }

    /**
     * Meta's docs describe extra_data only as "JSON encoded extra information",
     * and don't say what units budget values use. Rather than assume, settle it
     * per ad set against a fact we already know: the newest change's new value
     * IS the current budget, because nothing changed after it. Whichever scale
     * makes that true is the scale the payload uses.
     *
     * Returns null when neither scale fits — the events and the current budget
     * disagree, so this ad set is left alone.
     *
     * This relies on the fetch window ending now, which it always does: the
     * job only takes a number of days back from the present.
     *
     * @param  array<int, array{at: int, old: float, new: float}>  $changes
     */
    private function resolveScale(AdSet $adSet, array $changes, float $current): ?float
    {
        // No changes to calibrate against; nothing is written from events
        // anyway, the whole window just holds the current budget.
        if ($changes === []) {
            return 1.0;
        }

        $last = end($changes);
        $latest = (float) $last['new'];

        foreach ([1.0, 100.0] as $scale) {
            if (abs(($latest / $scale) - $current) < 0.01) {
                return $scale;
            }
        }

        Log::warning('Ad-set budget backfill skipped an ad set: activity log disagrees with the current budget', [
            'ad_account_id' => $this->adAccount->id,
            'ad_set_id' => $adSet->id,
            'latest_event_value' => $latest,
            'current_budget' => $current,
        ]);

        return null;
    }

    /**
     * @return array{at: int, old: float, new: float}|null
     */
    private function parseBudgetEvent(array $event): ?array
    {
        $extra = $event['extra_data'] ?? null;

        if (is_string($extra)) {
            $extra = json_decode($extra, true);
        }

        if (! is_array($extra)) {
            return null;
        }

        $old = $this->extractAmount($extra['old_value'] ?? null, 'old_value');
        $new = $this->extractAmount($extra['new_value'] ?? null, 'new_value');

        if ($old === null || $new === null) {
            return null;
        }

        return [
            'at' => Carbon::parse($event['event_time'])->getTimestamp(),
            'old' => $old,
            'new' => $new,
        ];
    }

    /**
     * Pull the amount out of Meta's budget envelope. Live payloads wrap it and
     * repeat the outer key inside:
     *
     *   "old_value": {"type":"payment_amount","currency":"PHP","old_value":10000,
     *                 "additional_type":"status_string","additional_value":"Per day"}
     *
     * Accept a bare number on the outer key too, so a flatter payload from some
     * other event shape still parses instead of being silently dropped.
     */
    private function extractAmount(mixed $value, string $key): ?float
    {
        if (is_array($value)) {
            $value = $value[$key] ?? null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
