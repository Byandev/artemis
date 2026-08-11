<?php

namespace Modules\Inventory\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Inventory\Support\InventoryItemSnapshotter;

/**
 * Freeze every inventory item — stored columns, computed metrics and the
 * report's group figures — against a date.
 *
 * This is what the items list reads, so these runs are the page rather than a
 * history sidecar; see routes/console.php for the schedule. Re-running a date is
 * safe: rows are upserted on (inventory_item_id, snapshot_date), so a manual
 * backfill overwrites rather than duplicates.
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
                            {--skip-demand : Freeze what is already stored, without recomputing demand first}';

    protected $description = 'Store a snapshot of every inventory item, its computed stock metrics and its report figures';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        $workspaces = Workspace::query()
            ->when($this->option('workspace'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        $written = 0;
        $synced = 0;

        foreach ($workspaces as $workspace) {
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

        return self::SUCCESS;
    }
}
