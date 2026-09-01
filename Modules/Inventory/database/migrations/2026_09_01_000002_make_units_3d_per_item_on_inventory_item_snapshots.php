<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-grain units_3d from the group to the item, and make it the workspace's only
 * record of how fast an item sells.
 *
 * The same three days of the same order feed were being counted twice: once by
 * GencysDemandSync into inventory_items.three_days_average, per item, and once
 * by ItemReportFacts into units_3d, per group. Two counts of one measurement is
 * how the list came to show two different numbers for it — the group total
 * against one SKU's average on the flat list, and the whole group's demand
 * against a narrowed average whenever a filter hid part of a group. Only one of
 * them survives, and it is this column.
 *
 * No column is added or dropped: units_3d already exists, and only what a row
 * means changes. Every row now carries its own item's units over the window
 * rather than its group's, and the roll-up sums it instead of reading it with
 * MAX().
 *
 * That makes existing rows wrong in a specific way — each holds its group's
 * total, so summing a three-SKU group would treble it — which is what the
 * backfill below repairs. three_days_average is the exact per-item split of the
 * same figure (the two agreed to the cent before this change: 3925.33 across
 * the workspace either way), so multiplying it back out by the window
 * reconstructs the per-item number precisely rather than apportioning a guess.
 *
 * units_7d and units_14d stay per group and are untouched. Nothing computes
 * from them — they sit beside the distinct-order counts in the roll-up to show
 * whether demand is accelerating, and those counts genuinely cannot be split
 * between siblings, so the pair is only comparable at one grain.
 */
return new class extends Migration
{
    /** The window units_3d covers, mirroring ItemReportFacts::ITEM_WINDOW. */
    private const WINDOW_DAYS = 3;

    public function up(): void
    {
        // Rows predating the report-facts columns have no three_days_average to
        // rebuild from and no units_3d worth keeping; leaving them null says so.
        DB::table('inventory_item_snapshots')
            ->whereNotNull('three_days_average')
            ->update([
                'units_3d' => DB::raw('ROUND(three_days_average * '.self::WINDOW_DAYS.')'),
            ]);
    }

    /**
     * Put each row's group total back.
     *
     * Recomputed from the rows themselves rather than remembered: the group's
     * units are the sum of its items', which is the very property that made the
     * re-grain safe in the first place.
     */
    public function down(): void
    {
        DB::statement('
            UPDATE inventory_item_snapshots AS s
            JOIN (
                SELECT workspace_id, snapshot_date,
                       COALESCE(parent_id, inventory_item_id) AS grp,
                       SUM(units_3d) AS total
                  FROM inventory_item_snapshots
                 WHERE units_3d IS NOT NULL
                 GROUP BY workspace_id, snapshot_date, grp
            ) AS g
              ON g.workspace_id = s.workspace_id
             AND g.snapshot_date = s.snapshot_date
             AND g.grp = COALESCE(s.parent_id, s.inventory_item_id)
               SET s.units_3d = g.total
             WHERE s.units_3d IS NOT NULL
        ');
    }
};
