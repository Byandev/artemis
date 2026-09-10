<?php

namespace App\Console\Commands;

use App\Jobs\BackfillCallLogPersonasForDay;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fills in the persona, order and delivery a call log was never stamped with.
 *
 * Stamping happens at sync time, against data that may not have arrived yet: a
 * call synced before that day's deliveries land matches nothing and stays null,
 * and rows that predate the columns existing at all never had a chance to earn
 * one. This re-runs the same match after the fact, when the orders and
 * deliveries it needs are actually in the table.
 *
 * The matching itself lives in BackfillCallLogPersonasForDay, which this hands
 * the queue one job per workspace-day — a backfill reaching back months is not
 * something to hold a terminal open for. Use --dry-run to see the numbers first,
 * or --sync for a range small enough to wait on.
 */
class BackfillCallLogPersonas extends Command
{
    protected $signature = 'call-logs:backfill-personas
        {--workspace= : Limit to one workspace id or slug. Defaults to every workspace with unstamped calls.}
        {--date= : Single day to backfill (YYYY-MM-DD).}
        {--since= : Start of a date range (YYYY-MM-DD). Defaults to the earliest unstamped call.}
        {--until= : End of a date range (YYYY-MM-DD). Defaults to the latest.}
        {--rule=all : Which rules to apply — all, delivery, or verification.}
        {--sync : Run here and now instead of queueing, and report the totals.}
        {--dry-run : Report what would be stamped without keeping any of it. Implies --sync.}';

    protected $description = 'Backfill persona, order_id and order_for_delivery_id on call logs that synced before the data they match against.';

    public function handle(): int
    {
        $rule = (string) $this->option('rule');

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
            $workspace = Workspace::query()
                ->where('id', $option)
                ->orWhere('slug', $option)
                ->first();

            if (! $workspace) {
                $this->error("No workspace matches '{$option}'.");

                return self::FAILURE;
            }

            $workspaceId = $workspace->id;
        }

        $days = $this->daysWithWork($workspaceId, $since, $until);

        if ($days->isEmpty()) {
            $this->info('Nothing to backfill — every call log in range already carries a persona.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        // A dry run has nothing to hand a worker: its whole output is the count
        // it would have written, which a queued job could only put in a log.
        return $dryRun || $this->option('sync')
            ? $this->runHere($days, $rule, $dryRun, $workspaceId, $since, $until)
            : $this->queue($days, $rule);
    }

    /**
     * @param  Collection<int, object>  $days
     */
    private function queue(Collection $days, string $rule): int
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

    /**
     * @param  Collection<int, object>  $days
     */
    private function runHere(Collection $days, string $rule, bool $dryRun, ?int $workspaceId, ?string $since, ?string $until): int
    {
        $this->info(sprintf(
            '%s %d workspace-day%s with unstamped calls (rule: %s).',
            $dryRun ? 'Would stamp' : 'Stamping',
            $days->count(),
            $days->count() === 1 ? '' : 's',
            $rule,
        ));

        $totals = ['customer' => 0, 'rider' => 0, 'verification' => 0];

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

        $stamped = array_sum($totals);

        $this->table(['Persona', 'Calls'], [
            ['customer', $totals['customer']],
            ['rider', $totals['rider']],
            ['verification', $totals['verification']],
            ['<options=bold>total</>', "<options=bold>{$stamped}</>"],
        ]);

        $left = $this->remaining($workspaceId, $since, $until);

        $this->line(sprintf(
            '<fg=gray>Still unmatched: %d call%s in range.</>',
            $left,
            $left === 1 ? '' : 's',
        ));

        if ($dryRun) {
            $this->comment('Dry run — the updates were rolled back, nothing was kept.');
        }

        return self::SUCCESS;
    }

    /**
     * The (workspace, date) pairs that have anything left to stamp.
     *
     * Reads off call_logs_ws_date_persona_idx, so it stays cheap even when the
     * range is left open and the whole table is in scope.
     *
     * @return Collection<int, object>
     */
    private function daysWithWork(?int $workspaceId, ?string $since, ?string $until): Collection
    {
        return DB::table('call_logs')
            ->whereNull('persona')
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->when($since, fn ($q) => $q->where('call_date', '>=', $since))
            ->when($until, fn ($q) => $q->where('call_date', '<=', $until))
            ->select('workspace_id', 'call_date')
            ->groupBy('workspace_id', 'call_date')
            ->orderBy('workspace_id')
            ->orderBy('call_date')
            ->get();
    }

    /**
     * Calls in range still carrying no persona.
     */
    private function remaining(?int $workspaceId, ?string $since, ?string $until): int
    {
        return DB::table('call_logs')
            ->whereNull('persona')
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->when($since, fn ($q) => $q->where('call_date', '>=', $since))
            ->when($until, fn ($q) => $q->where('call_date', '<=', $until))
            ->count();
    }

    /**
     * The day as Y-m-d.
     *
     * call_date comes back from the query builder as whatever the driver hands
     * over — a datetime string on MySQL — and both the job payload and the log
     * line it writes want the date on its own.
     */
    private function dayOf(object $row): string
    {
        return Carbon::parse($row->call_date)->toDateString();
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
