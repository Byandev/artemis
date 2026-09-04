<?php

namespace App\Console\Commands;

use App\Jobs\BackfillCallLogPersonasForDay;
use App\Models\CallLog;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Fills in the persona, order and delivery a call log was never stamped with.
 *
 * Stamping happens at sync time, against data that may not have arrived yet:
 * a call synced before that day's deliveries land matches nothing and stays
 * null, and rows that predate a stamp being added at all have no way to earn
 * one. This re-runs the match after the fact, when the orders and deliveries
 * it needs are actually in the table.
 *
 * Two rules, applied in that order:
 *
 *   delivery      — the number is on a delivery loaded for that day, as
 *                   customer_phone or rider_phone. The sync-time rule, replayed.
 *   verification  — the number is the shipping-address phone on an order the
 *                   workspace confirmed that same day. Not applied at sync time,
 *                   so this command is the only thing that stamps it.
 *
 * Delivery wins where both would match: a call to a customer whose order was
 * confirmed and dispatched the same day is about the delivery in front of it.
 *
 * The work itself goes to the queue, one job per workspace-day — a backfill
 * reaching back months is not something to hold a terminal open for. Use
 * --dry-run to see the numbers first, or --sync for a range small enough to
 * wait on.
 */
class BackfillCallLogPersonas extends Command
{
    protected $signature = 'call-logs:backfill-personas
        {--date= : Single day to backfill (YYYY-MM-DD).}
        {--since= : Start of a date range (YYYY-MM-DD). Defaults to the earliest unstamped call.}
        {--until= : End of a date range (YYYY-MM-DD). Defaults to today.}
        {--workspace= : Limit to one workspace id or slug.}
        {--rule=all : Which rule to apply — all, delivery, or verification.}
        {--sync : Run here and now instead of queueing, and report the totals.}
        {--dry-run : Report what would be stamped without writing anything. Implies --sync.}';

    protected $description = 'Backfill persona, order_id and order_for_delivery_id on call logs that synced before the data they match against.';

    public function handle(): int
    {
        $rule = $this->option('rule');

        if (! in_array($rule, ['all', 'delivery', 'verification'], true)) {
            $this->error("--rule must be one of: all, delivery, verification. Got '{$rule}'.");

            return self::FAILURE;
        }

        [$since, $until] = $this->dateRange();

        if ($since && $until && $since > $until) {
            $this->error("--since ({$since}) is after --until ({$until}).");

            return self::FAILURE;
        }

        $workspaceId = null;

        if ($option = $this->option('workspace')) {
            $workspace = Workspace::where('id', $option)->orWhere('slug', $option)->first();

            if (! $workspace) {
                $this->error("No workspace matches '{$option}'.");

                return self::FAILURE;
            }

            $workspaceId = $workspace->id;
        }

        $days = $this->daysWithWork($workspaceId, $since, $until);

        if ($days->isEmpty()) {
            $this->info('Nothing to backfill — every call log in range is already stamped.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        // A dry run has nothing to hand a worker: its whole output is the count
        // it would have written, which a queued job could only put in a log.
        return $dryRun || $this->option('sync')
            ? $this->runHere($days, $rule, $dryRun)
            : $this->queue($days, $rule);
    }

    private function queue($days, string $rule): int
    {
        foreach ($days as $day) {
            BackfillCallLogPersonasForDay::dispatch((int) $day->workspace_id, $this->dayOf($day), $rule)
                ->onQueue('analytics');
        }

        $this->info(sprintf(
            'Queued %d job%s (one per workspace-day, rule: %s) on the analytics queue.',
            $days->count(),
            $days->count() === 1 ? '' : 's',
            $rule,
        ));
        $this->line('<fg=gray>Each job logs its totals as "Backfilled call log personas". Watch them in Horizon.</>');

        return self::SUCCESS;
    }

    private function runHere($days, string $rule, bool $dryRun): int
    {
        $this->info(sprintf(
            '%s %d workspace-day%s with unstamped calls (rule: %s).',
            $dryRun ? 'Would scan' : 'Scanning',
            $days->count(),
            $days->count() === 1 ? '' : 's',
            $rule,
        ));

        $totals = ['rider' => 0, 'customer' => 0, 'verification' => 0];

        $bar = $this->output->createProgressBar($days->count());
        $bar->start();

        foreach ($days as $day) {
            $job = new BackfillCallLogPersonasForDay((int) $day->workspace_id, $this->dayOf($day), $rule, $dryRun);

            foreach ($job->handle() as $key => $count) {
                $totals[$key] += $count;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['', 'Calls'], [
            ['Matched to a rider', $totals['rider']],
            ['Matched to a customer', $totals['customer']],
            ['Matched to a confirmed order', $totals['verification']],
            ['Stamped', array_sum($totals)],
        ]);

        if ($dryRun) {
            $this->comment('Dry run — nothing was written.');
        }

        return self::SUCCESS;
    }

    /**
     * The day as Y-m-d.
     *
     * call_date is cast to a date on the model, so casting it to a string hands
     * back a midnight time along with it — which then rides into the job payload
     * and the log line it writes.
     */
    private function dayOf($row): string
    {
        return Carbon::parse($row->call_date)->toDateString();
    }

    /**
     * The (workspace, date) pairs with calls still to stamp.
     *
     * persona IS NULL is the whole test, the same one every UPDATE carries: a
     * row that has a persona is finished, and one that never matched anything
     * costs a no-op statement to look at again.
     */
    private function daysWithWork(?int $workspaceId, ?string $since, ?string $until)
    {
        return CallLog::whereNull('persona')
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->when($since, fn ($q) => $q->whereDate('call_date', '>=', $since))
            ->when($until, fn ($q) => $q->whereDate('call_date', '<=', $until))
            ->select('workspace_id', 'call_date')
            ->groupBy('workspace_id', 'call_date')
            ->orderBy('call_date')
            ->get();
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function dateRange(): array
    {
        if ($date = $this->option('date')) {
            $day = Carbon::parse($date)->toDateString();

            return [$day, $day];
        }

        return [
            $this->option('since') ? Carbon::parse($this->option('since'))->toDateString() : null,
            $this->option('until') ? Carbon::parse($this->option('until'))->toDateString() : null,
        ];
    }
}
