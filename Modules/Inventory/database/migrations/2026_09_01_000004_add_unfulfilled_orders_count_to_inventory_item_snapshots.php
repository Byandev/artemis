<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Count the orders behind the unfulfilled units, not just the units.
 *
 * unfulfilled_count says how much stock is owed; this says how many orders are
 * waiting on it. Both answer "what do we already owe" and the items list shows
 * whichever the Unit/Order toggle is set to, so both have to be frozen — the
 * order basis was deriving this one by dividing the units through the
 * units-per-order the last three days averaged, which estimates a number the
 * feed can simply be asked for and drifts on any group whose bundle mix has
 * moved since.
 *
 * A group figure, stamped on every row of the group like the other order
 * counts: one order carrying two siblings is still one order to pick, and
 * cannot be split between them. The roll-up reads it back with MAX().
 *
 * Nullable with no backfill. Unfulfilled is whatever is sitting in the open
 * statuses right now rather than a window that can be recomputed, so a day that
 * did not record it cannot be told what it would have said. It fills on the
 * next snapshot run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_item_snapshots', function (Blueprint $table) {
            $table->unsignedInteger('unfulfilled_orders_count')->nullable()->after('unfulfilled_count');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_item_snapshots', function (Blueprint $table) {
            $table->dropColumn('unfulfilled_orders_count');
        });
    }
};
