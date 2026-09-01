<?php

namespace Modules\Inventory\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Inventory\Support\InventoryItemSnapshotter;
use Modules\Inventory\Support\SnapshotReadiness;

/**
 * Freeze every inventory item of every Gencys-partner workspace — stored columns,
 * computed metrics and the report's group figures — against a date.
 *
 * This is what a partner's items list reads, so these runs are the page rather
 * than a history sidecar; see routes/console.php for the schedule. Non-partners
 * are skipped outright: their list computes live, so a frozen row would be a copy
 * nobody reads. Re-running today is safe: rows are upserted on
 * (inventory_item_id, snapshot_date), so a re-run overwrites rather than
 * duplicates.
 *
 * There is no backfill. Every figure is computed from the present — the current
 * ledger, the purchase orders as they stand, the latest order feed — so a past
 * --date would stamp today's state with that date rather than reconstruct it.
 * The command refuses unless --force says that is genuinely what you want.
 *
 * The writing itself lives in InventoryItemSnapshotter, which an edit to a
 * displayed field also calls so today's row catches up without waiting for the
 * next scheduled run.
 */
class SnapshotInventoryItemsCommand extends Command
{
    protected $signature = 'inventory:snapshot-items
                            {--date= : Date to tag the snapshot with (Y-m-d), defaults to today}
                            {--workspace= : Limit to a single workspace id}
                            {--skip-demand : Freeze what is already stored, without recomputing demand first}
                            {--force : Stamp a past date with today\'s figures anyway (see the warning below)}
                            {--ignore-sync : Freeze even where an upstream ERP sync has failed or is still running}';

    protected $description = 'Store a snapshot of every inventory item, its computed stock metrics and its report figures';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        // Every figure here is computed from the present: stock from the current
        // ledger, waiting stock from the purchase orders as they stand now,
        // demand from the latest order feed. The date is a label, not an as-of.
        //
        // That was harmless while the snapshot was history nobody read. The
        // items list now reads it, so writing today's numbers under last
        // Tuesday's date does not record last Tuesday — it invents it, and
        // there is no way to tell the invented day from a real one afterwards.
        if ($date !== Carbon::today()->toDateString() && ! $this->option('force')) {
            $this->error("Refusing to write {$date}: these figures are computed from today, so this would record today's state under that date rather than what was true then.");
            $this->line('Pass --force if you knowingly want today\'s figures stamped with that date.');

            return self::FAILURE;
        }

        // Gencys partners only. Their figures arrive by batch sync, so a frozen
        // day is as current as the data gets. Every other workspace keeps its
        // inventory in Artemis directly and its list computes live — freezing
        // those rows wrote a copy nothing read, and one that a receipt saved a
        // minute later already contradicted. --workspace does not override this;
        // the flag is what makes a snapshot mean anything.
        $workspaces = Workspace::query()
            ->where('is_gencys_partner', true)
            ->when($this->option('workspace'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($workspaces->isEmpty()) {
            $this->info('No Gencys-partner workspaces to snapshot.');

            return self::SUCCESS;
        }

        $written = 0;
        $synced = 0;
        $skipped = 0;

        foreach ($workspaces as $workspace) {
            // A half-arrived day frozen into the snapshot is indistinguishable
            // from a complete one afterwards, and the list reads it as fact.
            // Skipping leaves yesterday's figures on screen, which is visibly
            // stale and self-corrects on the next clean run.
            $blockers = $this->option('ignore-sync') ? [] : SnapshotReadiness::blockers($workspace);

            if ($blockers) {
                $skipped++;
                $this->warn("Skipping {$workspace->name}: upstream syncs have not landed cleanly.");

                foreach ($blockers as $blocker) {
                    $this->line("  - {$blocker}");
                }

                continue;
            }

            // Recomputing demand and freezing it is one step, ordered inside the
            // snapshotter — see InventoryItemSnapshotter::refresh().
            $snapshotter = new InventoryItemSnapshotter(
                $workspace,
                $date,
                syncDemand: ! $this->option('skip-demand'),
            );

            $written += $snapshotter->refresh();
            $synced += $snapshotter->demandSynced();
        }

        $this->info("Recomputed demand for {$synced} item(s); snapshotted {$written} item(s) for {$date}.");

        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} workspace(s) waiting on an ERP sync. Re-run once those land, or pass --ignore-sync.");
        }

        // Fail only when nothing at all was written and something was held back,
        // so a scheduler that reports non-zero exits surfaces a feed that has
        // stopped rather than a partial run that did its job.
        return $written === 0 && $skipped > 0 ? self::FAILURE : self::SUCCESS;
    }
}
