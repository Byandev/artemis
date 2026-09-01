<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the two demand figures a second time, counted in orders rather than in
 * the units those orders expand into.
 *
 * An order line names a unit code, which the snapshot expands into its component
 * items — so one order for a three-item bundle is one order and three units. The
 * items list can show demand either way, and the toggle that switches between
 * them needs both denominations frozen rather than one frozen and the other
 * derived on every page load.
 *
 * units_per_order belongs to the group. It is measured over the same three days
 * as orders_3d and units_3d and stamped on every row of the group the way the
 * rest of the report facts are — the roll-up reads it back with MAX(), which is
 * exact because it is identical across the group.
 *
 * The other two are per item, each the unit figure divided by that group
 * constant. Dividing per item rather than storing the group's total is what
 * keeps them summable: the group's order figure is the sum of its items', exactly
 * as it is for units, so the roll-up needs no second formula.
 *
 * Only demand converts. Stock on hand, incoming stock and the reorder plan built
 * on them stay in units in both bases, deliberately — stock is bought, counted
 * and reconciled in units, and a plan that measured unit stock against an order
 * rate would buy a bundled SKU short by the bundle size.
 *
 * Nullable with no backfill, like every other demand column here. A group that
 * took no orders over the window has no units-per-order to divide by, and the
 * honest answer is "we cannot express this in orders" rather than a zero, which
 * would read as no demand at all. Rows written before this existed are in the
 * same position and stay null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_item_snapshots', function (Blueprint $table) {
            // The group's: units_3d / orders_3d over the 3-day window.
            $table->decimal('units_per_order', 14, 4)->nullable()->after('units_14d');
            // Per item: each demand figure over units_per_order.
            $table->decimal('three_days_average_orders', 14, 4)->nullable()->after('three_days_average');
            $table->decimal('unfulfilled_count_orders', 14, 4)->nullable()->after('unfulfilled_count');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_item_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'units_per_order',
                'three_days_average_orders',
                'unfulfilled_count_orders',
            ]);
        });
    }
};
