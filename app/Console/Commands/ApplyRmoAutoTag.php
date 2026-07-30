<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Support\RmoAutoTag;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Drives RMO auto-tagging: for every workspace that switched it on, re-tag the
 * RMO status of orders whose parcel status matches the workspace's map.
 *
 * Scheduled nightly at midnight, once the day's parcel syncs have all landed.
 * Re-running is harmless — rows already carrying the target status are skipped —
 * so an ad-hoc run to catch up a date is always safe.
 */
class ApplyRmoAutoTag extends Command
{
    protected $signature = 'rmo:apply-auto-tag
                            {--date= : Single delivery date in Y-m-d format. Defaults to the last --days days.}
                            {--days=2 : Number of trailing days to cover when --date is not given. Includes today.}
                            {--workspace= : Limit to one workspace, by id or slug. Defaults to every opted-in workspace.}';

    protected $description = 'Re-tag RMO statuses from courier parcel statuses for workspaces with auto-tagging enabled.';

    public function handle(): int
    {
        $workspaces = $this->targetWorkspaces();

        if ($workspaces->isEmpty()) {
            $this->info('No workspaces have RMO auto-tagging enabled.');

            return self::SUCCESS;
        }

        $dates = $this->targetDates();
        $tagged = 0;

        foreach ($workspaces as $workspace) {
            foreach ($dates as $date) {
                $count = RmoAutoTag::apply($workspace, $date);
                $tagged += $count;

                if ($count > 0) {
                    $this->line("  {$workspace->slug} {$date}: re-tagged {$count} order(s).");
                }
            }
        }

        $this->info(sprintf(
            'Auto-tagged %d order(s) across %d workspace(s) over %s.',
            $tagged,
            $workspaces->count(),
            count($dates) === 1 ? $dates[0] : $dates[count($dates) - 1].' → '.$dates[0],
        ));

        return self::SUCCESS;
    }

    /**
     * Opted-in workspaces only — the map is per-workspace, and RmoAutoTag::apply
     * would no-op on the rest anyway.
     *
     * @return Collection<int, Workspace>
     */
    private function targetWorkspaces()
    {
        $query = Workspace::query()
            ->with('rmoSetting')
            ->whereHas('rmoSetting', fn ($q) => $q->where('enable_auto_tag_status', true));

        if ($workspace = $this->option('workspace')) {
            $query->where(fn ($q) => $q->where('slug', $workspace)->orWhere('id', $workspace));
        }

        return $query->get();
    }

    /**
     * Newest first. Yesterday is covered by default too: a parcel delivered late
     * in the day only reports its final status after that day has rolled over.
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
