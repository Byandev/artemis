<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Support\RmoAutoAssign;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Sweeps up RMO rows that auto-assignment should have taken but didn't: rows
 * that predate the switch being turned on, rows created by a sync pass that
 * failed, and rows un-assigned after the fact.
 *
 * The parcel sync assigns each row as it touches it, so on a healthy day this
 * command finds nothing. Re-running is harmless — assigned rows are skipped.
 */
class ApplyRmoAutoAssign extends Command
{
    protected $signature = 'rmo:apply-auto-assign
                            {--date= : Single delivery date in Y-m-d format. Defaults to the last --days days.}
                            {--days=2 : Number of trailing days to cover when --date is not given. Includes today.}
                            {--workspace= : Limit to one workspace, by id or slug. Defaults to every opted-in workspace.}';

    protected $description = 'Assign unassigned RMO orders to the configured CSR pool for workspaces with auto-assignment enabled.';

    public function handle(): int
    {
        $workspaces = $this->targetWorkspaces();

        if ($workspaces->isEmpty()) {
            $this->info('No workspaces have RMO auto-assignment enabled.');

            return self::SUCCESS;
        }

        $dates = $this->targetDates();
        $assigned = 0;

        foreach ($workspaces as $workspace) {
            // Switched on with nobody in the pool is a real configuration, and a
            // silent no-op reads as a bug. Say so once per workspace.
            if (RmoAutoAssign::pool($workspace) === []) {
                $this->warn("  {$workspace->slug}: auto-assignment is on but no CSR is in the pool — nothing to assign to.");

                continue;
            }

            foreach ($dates as $date) {
                $count = RmoAutoAssign::apply($workspace, $date);
                $assigned += $count;

                if ($count > 0) {
                    $this->line("  {$workspace->slug} {$date}: assigned {$count} order(s).");
                }
            }
        }

        $this->info(sprintf(
            'Auto-assigned %d order(s) across %d workspace(s) over %s.',
            $assigned,
            $workspaces->count(),
            count($dates) === 1 ? $dates[0] : $dates[count($dates) - 1].' → '.$dates[0],
        ));

        return self::SUCCESS;
    }

    /**
     * Opted-in workspaces only — RmoAutoAssign::apply would no-op on the rest.
     *
     * @return Collection<int, Workspace>
     */
    private function targetWorkspaces()
    {
        $query = Workspace::query()
            ->with('rmoSetting')
            ->whereHas('rmoSetting', fn ($q) => $q->where('enable_auto_assign', true));

        if ($workspace = $this->option('workspace')) {
            $query->where(fn ($q) => $q->where('slug', $workspace)->orWhere('id', $workspace));
        }

        return $query->get();
    }

    /**
     * Newest first. Yesterday is covered by default too: a parcel that only went
     * out late in the day lands its RMO row after the day has rolled over.
     *
     * @return list<string>
     */
    private function targetDates(): array
    {
        if ($date = $this->option('date')) {
            return [CarbonImmutable::parse($date)->toDateString()];
        }

        $days = max(1, (int) $this->option('days'));
        $today = CarbonImmutable::today();

        return array_map(
            fn (int $i) => $today->subDays($i)->toDateString(),
            range(0, $days - 1),
        );
    }
}
